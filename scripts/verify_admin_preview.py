"""Read-only visual review of HTML rendered from isolated SQLite fixtures."""
import json
import subprocess
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
code = r'''async (page) => {
 const result={pages:[],errors:[]};
 page.on('pageerror',()=>result.errors.push('pageerror'));
 for(const width of [1440,375,320]) {
  await page.setViewportSize({width,height:1000});
  for(const name of ['product','inbox','thread','activity','settings','integrations','support']) {
   await page.goto('file://DIRECTORY/'+name+'.html');
   await page.locator('h1').waitFor();
   result.pages.push({name,width,h1:await page.locator('h1').count(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),styled:await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor)});
   if(width!==320) await page.screenshot({path:'output/playwright/admin-management-'+name+'-'+width+'.png',fullPage:true});
  }
 }
 return result;
}'''.replace('DIRECTORY',str(ROOT/'output/qa-commerce/admin-preview'))
proc=subprocess.run(['/Users/akm/.codex/skills/playwright/scripts/playwright_cli.sh','-s=homya-commerce','run-code',code],cwd=ROOT,text=True,capture_output=True)
if proc.returncode or '### Error' in proc.stdout:
    print(json.dumps({'result':'failed','reason':'VISUAL_PREVIEW_FAILED'}))
    raise SystemExit(1)
result=json.JSONDecoder().raw_decode(proc.stdout.split('### Result\n',1)[1])[0]
(ROOT/'output/qa-commerce/admin-management-visual.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result))
assert all(p['h1']==1 and not p['overflow'] for p in result['pages'])
assert not result['errors']
