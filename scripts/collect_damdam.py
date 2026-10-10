"""Read-only public DAMDAM collector; curated staging archive, never database/checkout.
Run: python3 scripts/collect_damdam.py --collect (resume valid local details/media).
Network hosts are restricted. No source HTML, reviews, stock, clients or secrets saved.
"""
import argparse, concurrent.futures, datetime, hashlib, html, json, math, re, time
import urllib.parse, urllib.request, xml.etree.ElementTree as ET
from pathlib import Path
from html.parser import HTMLParser

ROOT=Path(__file__).resolve().parents[1]
ARCHIVE=ROOT/'storage/app/catalogue'
MEDIA=ROOT/'public/media/catalogue'
BASE='https://www.damdam.fr'
DECODER=json.JSONDecoder()

class Parser(HTMLParser):
    def __init__(self):
        super().__init__();self.links=[];self.scripts=[];self.active=False;self.buf=''
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='a' and attrs.get('href'):self.links.append(attrs['href'])
        if tag=='script':self.active=True;self.buf=''
    def handle_data(self,s):
        if self.active:self.buf+=s
    def handle_endtag(self,tag):
        if tag=='script' and self.active:self.scripts.append(self.buf);self.active=False

def payloads(body,key):
    parser=Parser();parser.feed(body);chunks=[]
    for script in parser.scripts:
        for match in re.finditer(r'self\.__next_f\.push\(',script):
            try:
                arr,_=DECODER.raw_decode(script,match.end())
                if len(arr)>1 and isinstance(arr[1],str):chunks.append(arr[1])
            except (ValueError,TypeError):pass
    flight=''.join(chunks);out=[]
    for match in re.finditer('"'+re.escape(key)+'":',flight):
        try:out.append(DECODER.raw_decode(flight,match.end())[0])
        except ValueError:pass
    return out,parser

def now():return datetime.datetime.now(datetime.timezone.utc).isoformat()

def atomic(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    temp=path.with_suffix(path.suffix+'.tmp');temp.write_text(json.dumps(data,ensure_ascii=False,separators=(',',':')))
    temp.replace(path)

def fetch(url,media=False):
    allowed={'www.damdam.fr'} if not media else {'damdam.b-cdn.net','res.cloudinary.com'}
    if urllib.parse.urlparse(url).scheme!='https' or urllib.parse.urlparse(url).hostname not in allowed:
        raise ValueError('Unapproved public source host')
    for attempt in range(3):
        try:
            request=urllib.request.Request(url,headers={'User-Agent':'HOMYA authorized catalogue collection','Accept':'image/*' if media else 'text/html'})
            with urllib.request.urlopen(request,timeout=25) as r:
                if urllib.parse.urlparse(r.url).hostname not in allowed:raise ValueError('Unapproved redirect')
                limit=15000000 if media else 4000000
                data=r.read(limit+1)
                if len(data)>limit:raise ValueError('Response exceeds inspection limit')
                return data,r.headers.get_content_type()
        except Exception:
            if attempt==2:raise
            time.sleep(1+attempt)

def text(value):
    if not isinstance(value,str):return ''
    return re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',value))).strip()

def universe(product):
    category=product.get('category') or {}
    slug=category.get('slug','') if isinstance(category,dict) else ''
    label=(slug+' '+(category.get('name','') if isinstance(category,dict) else '')).lower()
    if re.search(r'granules-de-bois|combustible|buches|bois-densifi|pellets',label):return 'firewood'
    if slug.startswith('chauffage') or re.search(r'chemin|po[eê]le|chaudi[eè]re|foyer|conduit',label):return 'heating'
    if re.search(r'mobilier|table|chaise|canape|fauteuil|commode|buffet|lit-|meuble',label):return 'furniture'
    if re.search(r'tv-|televis|televise|télévis',label):return 'televisions'
    if re.search(r'froid|lavage|sechage|électroménager|electromenager|cuisson|cafe|philips|aspir|thermomix|kobold|samsung|refriger|lave-|congel|hotte|four|micro',label):return 'appliances'
    return 'other'

def curate(product,url,listing=None):
    slug=product.get('slug','')
    if not re.fullmatch('[a-z0-9][a-z0-9-]{0,220}',slug) or not text(product.get('title')):raise ValueError('Invalid product identity')
    issues=[];price=product.get('price')
    if not isinstance(price,int) or isinstance(price,bool) or price<=0:issues.append('missing_price');price=None
    if listing and 'price' in listing and price!=listing['price']:issues.append('listing_price_changed')
    images=[]
    for image in product.get('images') or []:
        if not isinstance(image,dict):continue
        src=image.get('url','')
        if urllib.parse.urlparse(src).hostname in {'damdam.b-cdn.net','res.cloudinary.com'} and src.startswith('https://'):
            if src not in [x['source_url'] for x in images]:images.append({'source_url':src,'alt':text(image.get('alt')) or text(product.get('title'))})
    if not images:issues.append('missing_images')
    specs=[];seen={}
    for spec in product.get('specs') or []:
        if not isinstance(spec,dict):continue
        if spec.get('value') is None:continue
        label=text(spec.get('label'));value=text(str(spec['value']))
        if not label or not value:continue
        key=label.casefold()
        if key in seen and seen[key]!=value:issues.append('conflicting_specification')
        if (label,value) not in [(x['label'],x['value']) for x in specs]:specs.append({'label':label,'value':value})
        seen[key]=value
    if not specs:issues.append('missing_specifications')
    short=text(product.get('shortDescription') or '')
    # Keep source sales promises out of HOMYA. Full descriptions are not rewritten blindly.
    description=text(product.get('description') or '')
    sentences=re.split(r'(?<=[.!?])\s+',short or description)
    safe=[s for s in sentences if not re.search(r'damdam|livraison|garantie|retour|rembourse|paiement|virement|assurance|installation|installateur|\bSAV\b|https?://|@',s,re.I)]
    description=' '.join(safe)[:6000]
    if (short or description) and not safe:issues.append('description_requires_review')
    # Flag numeric contradictions without deciding which published measurement is correct.
    surfaces=[str(x['value']).replace(',','.') for x in specs if 'surface de chauffe' in x['label'].lower()]
    mentions=re.findall(r'(\d+(?:[.,]\d+)?)\s*m[²2]',text(product.get('description','')))
    if surfaces and any(m.replace(',','.') not in surfaces for m in mentions):issues.append('conflicting_heating_surface')
    compare=product.get('compareAtPrice')
    if not isinstance(compare,int) or not price or compare<=price:compare=None
    variants=[]
    for v in product.get('variants') or []:
        if isinstance(v,dict):
            variants.append({k:v[k] for k in ['title','sku','options','price','compareAtPrice','images'] if k in v})
    category=product.get('category') if isinstance(product.get('category'),dict) else {}
    return {'slug':slug,'name':text(product.get('title')),'brand':text(product.get('brand')),'reference':text(product.get('sku')),
        'category':{'slug':category.get('slug',''),'name':text(category.get('name'))},'universe':universe(product),
        'condition':text(product.get('condition')),'price_cents':price,'previous_price_cents':compare,'currency':'EUR',
        'description':description,'specifications':specs,'images':images,'variants':variants,
        'has_variants':bool(product.get('hasVariants')),'source_url':url,'captured_at':now(),'issues':sorted(set(issues))}

def capture_detail(listing):
    slug=listing['slug'];path=ARCHIVE/'products'/(slug+'.json');url=BASE+'/produit/'+slug
    try:
        if path.exists():
            record=json.loads(path.read_text())
            if record.get('source_url')!=url:raise ValueError('Archive identity mismatch')
        else:
            body,_=fetch(url);values,_=payloads(body.decode('utf-8'),'product')
            found=next((v for v in values if isinstance(v,dict) and v.get('slug')==slug),None)
            if not found:raise ValueError('Missing product payload (including HTTP 200 not-found page)')
            record=curate(found,url,listing)
        # First image stored locally. All gallery origins retained, remaining images served from authorized source.
        if record['images']:
            first=record['images'][0];key=hashlib.sha256(first['source_url'].encode()).hexdigest()[:24]
            existing=list(MEDIA.glob(key+'.*')) if MEDIA.exists() else []
            if existing:first['local_url']='/media/catalogue/'+existing[0].name
            else:
                try:
                    data,mime=fetch(first['source_url'],True)
                    ext={'image/jpeg':'.jpg','image/png':'.png','image/webp':'.webp','image/avif':'.avif','image/gif':'.gif'}.get(mime)
                    signatures=(data.startswith(b'\xff\xd8\xff') or data.startswith(b'\x89PNG\r\n\x1a\n') or data.startswith(b'GIF8') or (data.startswith(b'RIFF') and data[8:12]==b'WEBP') or b'ftypavif' in data[:32])
                    if not ext or not signatures:raise ValueError('Unexpected media type/content')
                    MEDIA.mkdir(parents=True,exist_ok=True);target=MEDIA/(key+ext);target.write_bytes(data)
                    first.update({'local_url':'/media/catalogue/'+target.name,'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'mime':mime})
                except Exception:record['issues']=sorted(set(record['issues']+['primary_image_unavailable']))
        atomic(path,record)
        index={k:record[k] for k in ['slug','name','brand','reference','category','universe','condition','price_cents','previous_price_cents','currency','has_variants','source_url','captured_at','issues']}
        index['image']=record['images'][0] if record['images'] else None
        return index,None
    except Exception as e:return None,{'slug':slug,'source_url':url,'error':type(e).__name__+': '+str(e)[:180]}

def local_media(url, existing_files=None):
    """Download authorized media once; expose only opaque local filenames."""
    key=hashlib.sha256(url.encode()).hexdigest()[:24]
    types={'.jpg':'image/jpeg','.png':'image/png','.webp':'image/webp','.avif':'image/avif','.gif':'image/gif'}
    existing=existing_files.get(key) if existing_files is not None else next((p for p in MEDIA.glob(key+'.*') if p.suffix in types),None)
    if existing:
        data=existing.read_bytes();mime=types[existing.suffix];target=existing
    else:
        data,mime=fetch(url,True)
        ext=next((k for k,v in types.items() if v==mime),None)
        if not ext:raise ValueError('Unexpected media type')
        target=MEDIA/(key+ext)
    signatures={'image/jpeg':data.startswith(b'\xff\xd8\xff'),'image/png':data.startswith(b'\x89PNG\r\n\x1a\n'),
        'image/gif':data.startswith(b'GIF8'),'image/webp':data.startswith(b'RIFF') and data[8:12]==b'WEBP',
        'image/avif':b'ftypavif' in data[:32]}
    if not signatures.get(mime):raise ValueError('Unexpected media content')
    if not existing:
        MEDIA.mkdir(parents=True,exist_ok=True)
        temporary=target.with_suffix(target.suffix+'.tmp');temporary.write_bytes(data);temporary.replace(target)
    return {'local_url':'/media/catalogue/'+target.name,'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'mime':mime}

def localize_media():
    """Resume all galleries without changing source business data or using a database."""
    records=[(p,json.loads(p.read_text())) for p in sorted((ARCHIVE/'products').glob('*.json'))]
    urls=sorted({image['source_url'] for _,record in records for image in record['images']})
    # Index originals once; globbing the growing directory for every image is quadratic.
    existing_files={p.stem:p for p in MEDIA.iterdir() if p.suffix in {'.jpg','.png','.webp','.avif','.gif'}}
    results={};errors=[]
    with concurrent.futures.ThreadPoolExecutor(max_workers=16) as pool:
        pending={pool.submit(local_media,url,existing_files):url for url in urls}
        for future in concurrent.futures.as_completed(pending):
            url=pending[future]
            try:results[url]=future.result()
            except Exception as e:errors.append({'source_url':url,'error':type(e).__name__+': '+str(e)[:180]})
            done=len(results)+len(errors)
            if done%100==0 or done==len(urls):print(json.dumps({'processed':done,'total':len(urls),'local':len(results),'errors':len(errors)}),flush=True)
    for path,record in records:
        for image in record['images']:
            if image['source_url'] in results:image.update(results[image['source_url']])
        atomic(path,record)
    index=json.loads((ARCHIVE/'index.json').read_text())
    by_slug={record['slug']:record for _,record in records}
    for product in index['products']:
        images=by_slug[product['slug']]['images'];product['image']=images[0] if images else None
    report={'captured_at':now(),'total':len(urls),'local':len(results),'complete':not errors,'bytes':sum(x['bytes'] for x in results.values()),'errors':errors}
    index['meta']['local_media']={k:v for k,v in report.items() if k!='errors'}
    atomic(ARCHIVE/'index.json',index);atomic(ARCHIVE/'media-report.json',report)
    print(json.dumps(report,ensure_ascii=False),flush=True)

def collect():
    visited=set();pending={BASE+'/catalogue'};listings={};reported=None;page_errors=[]
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        while pending:
            batch=sorted(pending)[:3];pending.difference_update(batch)
            visited.update(batch)
            for url,future in [(u,pool.submit(catalogue_page,u)) for u in batch]:
                try:
                    body,values,parser=future.result()
                    count=re.search(r'(\d+) références disponibles',body)
                    if count:reported=int(count.group(1))
                    for value in values:
                        if isinstance(value,list):
                            for p in value:
                                if isinstance(p,dict) and re.fullmatch('[a-z0-9][a-z0-9-]{0,220}',p.get('slug','')):listings[p['slug']]=p
                    for href in parser.links:
                        u=urllib.parse.urljoin(url,href);parts=urllib.parse.urlparse(u);q=urllib.parse.parse_qs(parts.query)
                        if parts.hostname=='www.damdam.fr' and parts.path=='/catalogue' and set(q)=={'page'} and q['page'][0].isdigit() and 1<int(q['page'][0])<2000:
                            canonical=BASE+'/catalogue?page='+str(int(q['page'][0]))
                            if canonical not in visited:pending.add(canonical)
                except Exception as e:page_errors.append({'url':url,'error':type(e).__name__+': '+str(e)[:180]})
            if len(visited)%15<3:print('Catalogue pages:',len(visited),'references:',len(listings),'reported:',reported,flush=True)
    records=[];errors=[];started=now()
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        futures=[pool.submit(capture_detail,p) for p in listings.values()]
        for i,future in enumerate(concurrent.futures.as_completed(futures),1):
            record,error=future.result()
            if record:records.append(record)
            if error:errors.append(error)
            if i%100==0 or i==len(futures):
                meta={'source':BASE+'/catalogue','started_at':started,'updated_at':now(),'reported_total':reported,'discovered_total':len(listings),'collected_total':len(records),'complete':i==len(futures) and not errors and not page_errors and len(records)==reported,'page_count':len(visited),'errors':errors,'page_errors':page_errors}
                atomic(ARCHIVE/'index.json',{'meta':meta,'products':sorted(records,key=lambda x:x['slug'])})
                print('Details:',i,'/',len(futures),'valid:',len(records),'failed:',len(errors),flush=True)
    if not records:
        meta={'source':BASE+'/catalogue','updated_at':now(),'reported_total':reported,'discovered_total':len(listings),'collected_total':0,'complete':False,'page_count':len(visited),'errors':errors,'page_errors':page_errors}
    atomic(ARCHIVE/'report.json',meta)
    print(json.dumps(meta,ensure_ascii=False),flush=True)

def collect_sitemap():
    robots,_=fetch(BASE+'/robots.txt')
    urls=re.findall(r'^Sitemap:\s*(https://\S+)',robots.decode('utf-8'),re.M|re.I)
    products={}
    for url in urls:
        body,_=fetch(url);root=ET.fromstring(body)
        for node in root.iter():
            if not node.tag.endswith('}loc') or not node.text:continue
            parts=urllib.parse.urlparse(node.text)
            if parts.hostname!='www.damdam.fr' or not parts.path.startswith('/produit/'):continue
            slug=parts.path.removeprefix('/produit/')
            if not re.fullmatch('[a-z0-9][a-z0-9-]{0,220}',slug):raise ValueError('Unexpected public sitemap identity: '+slug[:80])
            products[slug]={'slug':slug}
    if not products:raise ValueError('No public sitemap products found')
    body,values,_=catalogue_page(BASE+'/catalogue')
    count=re.search(r'(\d+) références disponibles',body);reported=int(count.group(1)) if count else None
    if ARCHIVE.joinpath('index.json').exists():
        previous=json.loads(ARCHIVE.joinpath('index.json').read_text()).get('products',[])
    else:previous=[]
    records={p['slug']:p for p in previous if p['slug'] in products};valid=set();errors=[];started=now()
    print('Sitemap:',len(products),'unique products; catalogue reported:',reported,flush=True)
    # Bound concurrency; no authenticated API, no mutation of the source.
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
        futures=[pool.submit(capture_detail,p) for p in products.values()]
        for i,future in enumerate(concurrent.futures.as_completed(futures),1):
            record,error=future.result()
            if record:records[record['slug']]=record;valid.add(record['slug'])
            if error:errors.append(error)
            if i%100==0 or i==len(futures):
                meta={'source':BASE+'/catalogue','inventory_sources':urls,'started_at':started,'updated_at':now(),'reported_total':reported,'discovered_total':len(products),'collected_total':len(records),'verified_total':len(valid),'complete':i==len(futures) and not errors and len(valid)==len(products)==reported,'errors':errors}
                atomic(ARCHIVE/'index.json',{'meta':meta,'products':sorted(records.values(),key=lambda x:x['slug'])})
                print('Sitemap details:',i,'/',len(futures),'valid:',len(valid),'failed:',len(errors),flush=True)
    atomic(ARCHIVE/'report.json',meta);print(json.dumps(meta,ensure_ascii=False),flush=True)

def catalogue_page(url):
    # An HTTP 200 without the expected catalogue payload is not a successful page.
    for attempt in range(3):
        body,_=fetch(url);body=body.decode('utf-8');values,parser=payloads(body,'products')
        if any(isinstance(v,list) and v for v in values):return body,values,parser
        if attempt<2:time.sleep(2)
    raise ValueError('Missing catalogue payload after three inspections')

def normalize_universes():
    path=ARCHIVE/'index.json';archive=json.loads(path.read_text());changes=[]
    for entry in archive['products']:
        product_path=ARCHIVE/'products'/(entry['slug']+'.json');record=json.loads(product_path.read_text())
        target=universe({'category':record['category']})
        if record['universe']!=target:
            changes.append({'slug':record['slug'],'previous':record['universe'],'current':target})
            record['universe']=target;entry['universe']=target;atomic(product_path,record)
    if not changes:
        print(json.dumps({'normalized_products':len(archive['products']),'regrouped_products':0}),flush=True)
        return
    normalization={'processed_at':now(),'rule_version':2,'changed_universes':changes}
    archive['meta']['normalization']=normalization;atomic(path,archive);atomic(ARCHIVE/'report.json',archive['meta'])
    print(json.dumps({'normalized_products':len(archive['products']),'regrouped_products':len(changes)},ensure_ascii=False),flush=True)

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--collect',action='store_true');parser.add_argument('--collect-sitemap',action='store_true');parser.add_argument('--normalize',action='store_true');parser.add_argument('--media',action='store_true');parser.add_argument('--inspect',action='store_true');parser.add_argument('--sitemap',action='store_true');args=parser.parse_args()
    if args.sitemap:
        robots,_=fetch(BASE+'/robots.txt');print(robots.decode('utf-8'),flush=True)
        urls=re.findall(r'^Sitemap:\s*(https://\S+)',robots.decode('utf-8'),re.M|re.I)
        for url in urls:
            body,_=fetch(url);root=ET.fromstring(body)
            locations=[node.text for node in root.iter() if node.tag.endswith('}loc')]
            atomic(ARCHIVE/'inventory.json',{'source_url':url,'captured_at':now(),'product_urls':sorted(x for x in locations if '/produit/' in x)})
            print(json.dumps({'sitemap':url,'root':root.tag,'locations':len(locations),'products':len([x for x in locations if '/produit/' in x]),'first':locations[:5],'children':[x for x in locations if '/produit/' not in x][:20]},ensure_ascii=False),flush=True)
    elif args.inspect:
        for page in [1,2,3,134,135,136,155]:
            url=BASE+'/catalogue'+('' if page==1 else '?page='+str(page))
            body,_=fetch(url);values,parser=payloads(body.decode('utf-8'),'products')
            print(json.dumps({'page':page,'bytes':len(body),'title':re.findall('<title>(.*?)</title>',body.decode('utf-8')),'lists':[len(v) for v in values if isinstance(v,list)],'slugs':[p.get('slug') for v in values if isinstance(v,list) for p in v], 'metadata':{k:payloads(body.decode('utf-8'),k)[0] for k in ['total','totalPages','pageSize','currentPage','page','totalProducts']},'pagination':sorted(set(x for x in parser.links if 'page=' in x))},ensure_ascii=False),flush=True)
    elif args.media:localize_media()
    elif args.collect_sitemap:collect_sitemap()
    elif args.normalize:normalize_universes()
    elif args.collect:collect()
    else:parser.print_help()
