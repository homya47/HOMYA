"""Browser and HTTP checks against the installed XAMPP site; no orders or Google calls."""
import argparse
import json
import os
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--presentation-only', action='store_true')
args = parser.parse_args()
ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'output/qa-seo-catalogue'
audits = {locale: json.loads((QA / f'google-audit-{locale}.json').read_text()) for locale in ['fr', 'en', 'de']}
cases = [{'path': variant['url_path'], 'id': variant['variant'], 'title': variant['title']}
         for audit in audits.values() for product in audit['items'] for variant in product['variants']]
recipe = r'''async (page) => {
 const base='http://127.0.0.1:8080', cases=CASES, limit=LIMIT;
 const result={variants:[],pages:[],errors:[],assetFailures:[],withoutJs:[],guards:[]};
 page.on('pageerror',()=>result.errors.push('pageerror'));
 page.on('response',response=>{if(response.url().includes('/build/assets/') && response.status()>=400)result.assetFailures.push(response.status())});
 let cursor=0;
 await Promise.all([0,1].map(async()=>{
  while(cursor<limit){
   const entry=cases[cursor++];
   const response=await page.request.get(base+entry.path);
   const html=await response.text();
   const selected=html.match(/<option\s+value="([^"<>]+)"\s+selected/);
   const noindex=response.headers()['x-robots-tag']==='noindex, nofollow';
   result.variants.push({path:entry.path,status:response.status(),selected:selected?.[1]===entry.id,noindex});
  }
 }));
 const slug=cases[0].path.split('/produit/')[1].split('?')[0];
 for(const width of [1440,375,320]){
  await page.setViewportSize({width,height:1000});
  for(const locale of ['fr','en','de']){
   const response=await page.goto(`${base}/${locale}/produit/${slug}?variant=v2`);
   result.pages.push({width,locale,status:response.status(),h1:await page.locator('h1').count(),selected:await page.locator('#product-variant').inputValue(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),canonicalHasVariant:await page.locator('link[rel=canonical]').getAttribute('href').then(url=>url.includes('variant=')),photoNotice:await page.locator('.gallery .product-content-unavailable').count()===1,validJson:await page.locator('script[type="application/ld+json"]').evaluateAll(nodes=>nodes.every(node=>!!JSON.parse(node.textContent)))});
   if(locale==='fr')await page.screenshot({path:`output/playwright/seo-catalogue-${width}.png`,fullPage:true});
  }
 }
 await page.selectOption('#product-variant','v1');
 await page.waitForURL('**variant=v1#product-purchase');
 result.dropdownUpdatesUrl=await page.locator('#product-variant').inputValue()==='v1';
 for(const locale of ['fr','en','de']){
  const plain=await page.context().browser().newContext({javaScriptEnabled:false});
  const plainPage=await plain.newPage();
  await plainPage.goto(`${base}/${locale}/produit/${slug}?variant=v2`);
  result.withoutJs.push({locale,selected:await plainPage.locator('#product-variant').inputValue()});
  await plain.close();
 }
 for(const [path,status] of [['/robots.txt',200],['/sitemap.xml',404],['/google/merchant/fr.xml',404],['/fr/produit/'+slug+'?variant=v999',404]]){
  const response=await page.request.get(base+path);
  const body=await response.text();
  result.guards.push({path,status:response.status(),expected:status,localRobotsSafe:path!=='/robots.txt'||body.includes('Disallow: /')});
 }
 const examples=['lg-lswd100e-lave-linge-sechant','mobilier-6200-tables-fixes','1-2-palette-500-kg-charbon-20-30-anthracite-superieur','chauffage-111700002-poeles-a-granules-etanches'];
 result.content=[];
 for(const slug of examples){
  const response=await page.goto(base+'/fr/produit/'+slug);
  result.content.push({slug,status:response.status(),titleLength:(await page.locator('h1').innerText()).length,descriptionPresent:await page.locator('#product-description').count()===1,unresolvedPlaceholder:await page.locator('main').innerText().then(text=>text.includes('$7e'))});
 }
 return result;
}'''.replace('CASES', json.dumps(cases, ensure_ascii=False)).replace('LIMIT', str(0 if args.presentation_only else len(cases)))
environment = os.environ.copy()
environment['npm_config_offline'] = 'true'
process = subprocess.run(['/Users/akm/.codex/skills/playwright/scripts/playwright_cli.sh', '-s=homya-seo', 'run-code', recipe], cwd=ROOT, env=environment, text=True, capture_output=True)
if process.returncode or '### Error' in process.stdout:
    print(json.dumps({'result': 'failed', 'reason': 'BROWSER_CHECK_FAILED', 'diagnostic': process.stdout[-1800:]}))
    raise SystemExit(1)
result = json.JSONDecoder().raw_decode(process.stdout.split('### Result\n', 1)[1])[0]
(QA / ('installed-browser-final.json' if args.presentation_only else 'installed-browser.json')).write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
assert len(result['variants']) == (0 if args.presentation_only else 276) and all(row['status'] == 200 and row['selected'] and row['noindex'] for row in result['variants'])
assert len(result['pages']) == 9 and all(row['status'] == 200 and row['h1'] == 1 and row['selected'] == 'v2' and not row['overflow'] and not row['canonicalHasVariant'] and row['validJson'] and row['photoNotice'] for row in result['pages'])
assert all(row['selected'] == 'v2' for row in result['withoutJs']) and result['dropdownUpdatesUrl']
assert all(row['status'] == row['expected'] and row['localRobotsSafe'] for row in result['guards'])
assert all(row['status'] == 200 and row['titleLength'] <= 150 and row['descriptionPresent'] and not row['unresolvedPlaceholder'] for row in result['content'])
assert not result['errors'] and not result['assetFailures']
print(json.dumps({'result': 'passed', 'variant_urls': len(result['variants']), 'responsive_pages': 9, 'languages_without_js': 3, 'no_orders_or_google_calls': True}))
