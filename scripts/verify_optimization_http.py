"""Local read-only before/after HTTP parity and timing. Never persists cookies."""
import argparse
import gzip
import hashlib
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://127.0.0.1:8080'


class PublicPage(HTMLParser):
    def __init__(self):
        super().__init__()
        self.text = []
        self.images = []
        self.links = []
        self.srcsets = []
        self.assets = []

    def handle_data(self, data):
        self.text.append(data)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'img':
            self.images.append(attrs.get('src'))
            if attrs.get('srcset'): self.srcsets.append(attrs['srcset'])
        if tag == 'a': self.links.append(attrs.get('href'))
        for value in [attrs.get('src'), attrs.get('href')]:
            if value and '/build/assets/' in value: self.assets.append(value)

    def signature(self):
        return {'text_sha256':hashlib.sha256(' '.join(' '.join(self.text).split()).encode()).hexdigest(),
                'original_images':self.images, 'links':self.links}


def get(path, compress=False):
    if not path.startswith('/') or path.startswith('//'): raise ValueError('Local paths only')
    request = urllib.request.Request(BASE+path, headers={'Accept-Encoding':'gzip' if compress else 'identity'})
    start = time.perf_counter()
    try:
        response = urllib.request.urlopen(request, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    ttfb = time.perf_counter()-start
    data = response.read()
    # Header selection deliberately excludes Set-Cookie and other session data.
    headers = {key:response.headers.get(key) for key in ['Content-Type','Content-Encoding','Cache-Control','Vary','ETag']}
    return response.status, data, headers, ttfb


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('phase', choices=['before','after'])
    args = parser.parse_args()
    reportdir = ROOT/'output/qa-optimization'
    paths = []
    products = json.loads((ROOT/'storage/app/catalogue/index.json').read_text())['products']
    slug = next(p['slug'] for p in products if p.get('image') and p['universe']=='appliances')
    for locale in ['fr','en','de']:
        paths += [f'/{locale}', f'/{locale}/catalogue', f'/{locale}/catalogue?brand=Bosch&sort=price_asc',
                  f'/{locale}/promotions', f'/{locale}/produit/{slug}', f'/{locale}/catalogue?q=homya-no-result',
                  f'/{locale}/catalogue?min=oops']
    pages = []
    for path in paths:
        status, data, headers, seconds = get(path)
        expected = 422 if 'min=oops' in path else 200
        if status != expected: raise ValueError(f'Unexpected status for {path}: {status}')
        page = PublicPage()
        page.feed(data.decode())
        pages.append({'path':path,'status':status,'ttfb_seconds':seconds,'bytes':len(data),
                      'signature':page.signature(),'srcsets':len(page.srcsets),'assets':page.assets})
    timings = {}
    for path in ['/fr/catalogue','/en/catalogue','/de/catalogue']:
        timings[path] = [get(path)[3] for _ in range(3)]
    if args.phase=='after':
        previous = json.loads((reportdir/'http-before.json').read_text())
        for before,after in zip(previous['pages'],pages):
            if before['path']!=after['path'] or before['signature']!=after['signature']:
                raise ValueError(f'Public page parity failed: {after["path"]}')
    assets = {}
    for url in pages[0]['assets']:
        path = urllib.parse.urlparse(url).path
        status,data,headers,seconds = get(path, compress=True)
        decoded = gzip.decompress(data) if headers['Content-Encoding']=='gzip' else data
        if status!=200 or decoded!=(ROOT/'public'/path.lstrip('/')).read_bytes(): raise ValueError('Asset mismatch')
        assets[path] = {'wire_bytes':len(data),'decoded_bytes':len(decoded),'headers':headers}
    for path in ['/.env','/storage/app/private/database-access','/vendor/autoload.php']:
        status,_,_,_=get(path)
        if status not in [403,404]: raise ValueError('Private path accessible')
    report = {'phase':args.phase,'pages':pages,'timings':timings,'assets':assets,'private_paths_denied':True,'sql_writes':False}
    (reportdir/f'http-{args.phase}.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps({'phase':args.phase,'pages':len(pages),'parity':args.phase=='after','timings':timings,'assets':assets}))


if __name__=='__main__': main()
