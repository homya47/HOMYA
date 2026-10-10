"""Local browser QA. Read credentials privately; never print or archive submitted secrets."""
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PRIVATE = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA/storage/app/private/admin')
files = sorted(PRIVATE.glob('access-*.txt'))
assert files and not files[-1].is_symlink()
access = files[-1].read_text()
username = re.search(r'Identifiant : (.+)', access)[1]
password = re.search(r'Mot de passe : (.+)', access)[1]
code = r'''async (page) => {
 const result = {pages:[], errors:[]};
 page.on('pageerror', () => result.errors.push('pageerror'));
 await page.goto('http://127.0.0.1:8080/admin/connexion');
 await page.getByLabel('Identifiant', {exact:true}).fill(USERNAME);
 await page.getByLabel('Mot de passe', {exact:true}).fill(PASSWORD);
 await page.getByRole('button', {name:'Se connecter au dashboard'}).click();
 await page.getByRole('heading', {name:'Vue d’ensemble', exact:true}).waitFor();
 result.login = new URL(page.url()).pathname === '/admin';
 for (const width of [1440,375,320]) {
  await page.setViewportSize({width,height:1000});
  for (const path of ['/admin','/admin/commandes','/admin/stocks','/admin/retours']) {
   const response = await page.goto('http://127.0.0.1:8080'+path);
   await page.locator('h1').waitFor();
   result.pages.push({path,width,status:response.status(), h1:await page.locator('h1').count(), overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth), private:response.headers()['cache-control']==='no-store, private', noindex:response.headers()['x-robots-tag']==='noindex, nofollow'});
   if (width === 1440 || width === 375) await page.screenshot({path:'output/playwright/admin-'+path.split('/').pop()+'-'+width+'.png',fullPage:true});
  }
 }
 await page.goto('http://127.0.0.1:8080/admin/stocks?q=absent-admin-fixture-only');
 result.emptySearch = await page.getByText('Aucun produit pour cette recherche.').count() === 1;
 await page.goto('http://127.0.0.1:8080/admin/stocks');
 const next = page.getByRole('link',{name:'Suivant →'});
 if(await next.count()) { await next.click(); result.pagination = new URL(page.url()).searchParams.get('page') === '2'; }
 await page.goto('http://127.0.0.1:8080/admin/commandes');
 const detail = page.getByRole('link', {name:/Consulter la commande/}).first();
 if(await detail.count()) { await detail.click(); result.orderDetail = await page.getByRole('heading',{name:'Détail de la commande', exact:true}).count() === 1; }
 const badPost = await page.request.post('http://127.0.0.1:8080/admin/deconnexion',{data:{}});
 result.csrfRefused = [403,419].includes(badPost.status());
 await page.goto('http://127.0.0.1:8080/admin');
 await page.getByRole('button',{name:'Se déconnecter',exact:true}).click();
 await page.getByRole('heading',{name:'Connexion administrateur',exact:true}).waitFor();
 await page.goto('http://127.0.0.1:8080/admin/stocks');
 result.logout = new URL(page.url()).pathname === '/admin/connexion';
 const ctx=await page.context().browser().newContext({viewport:{width:375,height:900},javaScriptEnabled:false});
 const p=await ctx.newPage();
 await p.goto('http://127.0.0.1:8080/admin/connexion');
 await p.getByLabel('Identifiant',{exact:true}).fill(USERNAME);
 await p.getByLabel('Mot de passe',{exact:true}).fill(PASSWORD);
 await p.getByRole('button',{name:'Se connecter au dashboard'}).click();
 await p.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
 result.noJavascript = new URL(p.url()).pathname === '/admin';
 await ctx.close();
 return result;
}'''.replace('USERNAME', json.dumps(username)).replace('PASSWORD', json.dumps(password))
command = ['/Users/akm/.codex/skills/playwright/scripts/playwright_cli.sh', '-s=homya-commerce', 'run-code', code]
proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
output = proc.stdout
if '### Error' in output or proc.returncode:
    # Diagnostics exclude the submitted code; there are no credential values in the report.
    print(json.dumps({'result':'failed','reason':'BROWSER_CHECK_FAILED'}))
    raise SystemExit(1)
result = json.JSONDecoder().raw_decode(output.split('### Result\n',1)[1])[0]
assert result['login'] and result['logout'] and result['csrfRefused'] and result['emptySearch'] and result['noJavascript']
assert not result['errors']
assert all(row['status']==200 and row['h1']==1 and not row['overflow'] and row['private'] and row['noindex'] for row in result['pages'])
(ROOT/'output/qa-commerce/admin-browser.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result))
