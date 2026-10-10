"""Read-only browser review of the new contact and privacy notices."""
import json
import subprocess
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
code = r'''async (page) => {
 const result={pages:[],errors:[]};
 page.on('pageerror',()=>result.errors.push('pageerror'));
 for(const width of [1440,375]) {
  await page.setViewportSize({width,height:900});
  for(const locale of ['fr','en','de']) {
   for(const name of ['contact','confidentialite']) {
    const response=await page.goto('http://127.0.0.1:8080/'+locale+'/'+name);
    result.pages.push({locale,name,width,status:response.status(),h1:await page.locator('h1').count(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),supportLink:name!=='contact' || await page.locator('a[href$=assistance]').count()>0,privacySections:name!=='confidentialite' || await page.locator('#legal-assistance').count()>0 && await page.locator('#legal-mesure-interne').count()>0});
   }
  }
 }
 return result;
}'''
proc=subprocess.run(['/Users/akm/.codex/skills/playwright/scripts/playwright_cli.sh','-s=homya-commerce','run-code',code],cwd=ROOT,text=True,capture_output=True)
if proc.returncode or '### Error' in proc.stdout:
    print(json.dumps({'result':'failed','reason':'NOTICE_CHECK_FAILED'}))
    raise SystemExit(1)
result=json.JSONDecoder().raw_decode(proc.stdout.split('### Result\n',1)[1])[0]
(ROOT/'output/qa-commerce/dashboard-notices.json').write_text(json.dumps(result,indent=2)+'\n')
assert len(result['pages'])==12 and not result['errors'] and all(p['status']==200 and p['h1']==1 and not p['overflow'] and p['supportLink'] and p['privacySections'] for p in result['pages'])
print(json.dumps({'result':'passed','views':len(result['pages']),'languages':['fr','en','de'],'new_sections':True,'overflow':False,'errors':False}))
