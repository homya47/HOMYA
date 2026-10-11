"""Read-only browser checks of the provider-neutral, disabled card form."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
code = r'''async (page) => {
 const base='http://127.0.0.1:8080';
 const result={pages:[],errors:[],externalRequests:[]};
 page.on('pageerror',()=>result.errors.push('pageerror'));
 page.on('request',r=>{if(!r.url().startsWith(base) && !r.url().startsWith('data:'))result.externalRequests.push(new URL(r.url()).hostname)});
 for(const width of [1440,375,320]) {
  await page.setViewportSize({width,height:1000});
  for(const locale of width===320?['fr']:['fr','en','de']) {
   const response=await page.goto(base+'/'+locale+'/paiement/carte');
   result.pages.push({locale,width,status:response.status(),h1:await page.locator('h1').count(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),inputs:await page.locator('fieldset[disabled] input[disabled]').count(),noInputNames:await page.locator('fieldset[disabled] input[name]').count()===0,noStripe:!(await page.locator('body').innerText()).includes('Stripe') && !(response.headers()['content-security-policy']||'').includes('stripe'),noCardSubmit:await page.locator('.card-payment-panel form').count()===0,private:response.headers()['cache-control']==='no-store, private',noindex:response.headers()['x-robots-tag']==='noindex, nofollow'});
   if(locale==='fr')await page.screenshot({path:`output/playwright/stripe-retired-card-${width}.png`,fullPage:true});
  }
 }
 result.csrfStatus=(await page.request.post(base+'/fr/commande/00000000-0000-4000-8000-000000000001/carte/virement')).status();
 result.foreignOrderStatus=(await page.request.get(base+'/fr/commande/00000000-0000-4000-8000-000000000001/carte')).status();
 result.adminAnonymousRedirect=(await page.request.get(base+'/admin/parametres',{maxRedirects:0})).status();
 result.retiredWebhookStatus=(await page.request.get(base+'/paiement/stripe/webhook')).status();
 result.retiredSessionStatus=(await page.request.get(base+'/fr/commande/00000000-0000-4000-8000-000000000001/carte/session')).status();
 const browser=page.context().browser();
 const plain=await browser.newContext({javaScriptEnabled:false});
 const plainPage=await plain.newPage();
 await plainPage.goto(base+'/fr/paiement/carte');
 result.withoutJsDisabled=await plainPage.locator('fieldset[disabled] input[disabled]').count()===9;
 await plain.close();
 await page.setViewportSize({width:1440,height:1000});
 await page.goto(base+'/fr/paiement/carte');
 return result;
}'''
proc = subprocess.run(['/Users/akm/.codex/skills/playwright/scripts/playwright_cli.sh', '-s=homya-card-retired', 'run-code', code], cwd=ROOT, text=True, capture_output=True)
if proc.returncode or '### Error' in proc.stdout:
    print(json.dumps({'result': 'failed', 'reason': 'CARD_BROWSER_RECIPE_FAILED', 'diagnostic': proc.stdout[-1600:]}))
    raise SystemExit(1)
result = json.JSONDecoder().raw_decode(proc.stdout.split('### Result\n', 1)[1])[0]
(ROOT / 'output/qa-commerce/stripe-retirement-browser.json').write_text(json.dumps(result, indent=2) + '\n')
assert len(result['pages']) == 7 and not result['errors'] and not result['externalRequests']
assert all(p['status'] == 200 and p['h1'] == 1 and not p['overflow'] and p['inputs'] == 9 and p['noInputNames'] and p['noStripe'] and p['noCardSubmit'] and p['private'] and p['noindex'] for p in result['pages'])
assert result['csrfStatus'] == 419 and result['foreignOrderStatus'] == 404 and result['adminAnonymousRedirect'] == 302 and result['withoutJsDisabled']
assert result['retiredWebhookStatus'] == 404 and result['retiredSessionStatus'] == 404
print(json.dumps({'result': 'passed', 'preview_views': 7, 'javascript_errors': 0, 'external_requests': 0, 'no_card_entry': True, 'no_real_order_or_payment': True}))
