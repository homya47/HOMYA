"""Local UI recipe: synthetic drafts only; no real numbering, sale, email or SQL mutation."""
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
files = sorted(Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA/storage/app/private/admin').glob('access-*.txt'))
assert files and not files[-1].is_symlink()
access = files[-1].read_text()
username = re.search(r'Identifiant : (.+)', access)[1]
password = re.search(r'Mot de passe : (.+)', access)[1]
code = r'''async (page) => {
 page.setDefaultTimeout(15000);
 const base='http://127.0.0.1:8080';
 const result={stage:'login',pages:[],errors:[],external:[],drafts:[]};
 page.on('pageerror',()=>result.errors.push('javascript-error'));
 page.on('request',r=>{if(!r.url().startsWith(base) && !r.url().startsWith('data:'))result.external.push(new URL(r.url()).hostname)});
 try {
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/admin/documents');
  if(new URL(page.url()).pathname==='/admin/connexion') {
   await page.locator('[name=username]').fill(USERNAME);
   await page.locator('[name=password]').fill(PASSWORD);
   await page.getByRole('button',{name:'Se connecter au dashboard',exact:false}).click();
   await page.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
  }
  result.login=true;
  const check=async (path,name,width)=>{
   await page.setViewportSize({width,height:1000});
   const response=await page.goto(base+path);
   const row={name,width,status:response.status(),h1:await page.locator('h1').count(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),private:response.headers()['cache-control']==='no-store, private',noindex:response.headers()['x-robots-tag']==='noindex, nofollow'};
   result.pages.push(row);
   if(width===1440 || width===375) await page.screenshot({path:`output/playwright/documents-${name}-${width}.png`,fullPage:true});
  };
  result.stage='create-drafts';
  for (const [type,label] of [['quote','Devis'],['purchase_order','Bon de commande'],['invoice','Facture']]) {
   await page.goto(base+'/admin/documents');
   // A replay of this recipe reuses its named draft rather than creating more copies.
   await page.locator('[name=q]').fill('RECETTE DOCUMENTS '+type);
   await page.getByRole('button',{name:'Rechercher',exact:true}).click();
   const edit=page.getByRole('link',{name:'Modifier',exact:true}).first();
   if(await edit.count()) { await edit.click(); }
   else {
    await page.getByRole('button',{name:'+ '+label,exact:true}).click();
    await page.locator('[data-document-editor]').waitFor();
   }
   const path=new URL(page.url()).pathname;
   const editor=page.locator('[data-document-editor]');
   await editor.locator('[name="customer[name]"]').fill('RECETTE DOCUMENTS '+type+' — NON COMMERCIAL');
   await editor.locator('[name="customer[email]"]').fill('recette@example.invalid');
   await editor.locator('[name="customer[address]"]').fill('12 rue de démonstration');
   await editor.locator('[name="customer[postal_code]"]').fill('75001');
   await editor.locator('[name="customer[city]"]').fill('Paris');
   await editor.locator('[name="customer[country]"]').fill('FR');
   const first=editor.locator('[data-document-line]').first();
   await first.locator('[name$="[reference]"]').fill('RECETTE-001');
   await first.locator('[name$="[name]"]').fill('Produit fictif <script>window.fixtureExecuted=true</script>');
   await first.locator('[name$="[price]"]').fill('10.00');
   if(await editor.locator('[data-document-line]').count()===1)await editor.getByRole('button',{name:'+ Ajouter un article',exact:true}).click();
   const second=editor.locator('[data-document-line]').nth(1);
   await second.locator('[name$="[reference]"]').fill('RECETTE-002');
   await second.locator('[name$="[name]"]').fill('Deuxième article fictif');
   await second.locator('[name$="[quantity]"]').fill('2');
   await second.locator('[name$="[price]"]').fill('12.01');
   await editor.locator('[name=tax_mode]').selectOption(''); // Fiscal data intentionally missing: live issuance remains blocked.
   await editor.locator('[name=payment_terms]').fill('RECETTE — Aucune somme à régler.');
   await editor.locator('[name=delivery_terms]').fill('RECETTE — Aucun envoi réel.');
   await page.request.get(base+'/admin/notifications',{headers:{'X-Requested-With':'XMLHttpRequest'}});
   await editor.getByRole('button',{name:'Enregistrer le brouillon et recalculer',exact:true}).click();
   await page.getByRole('status').filter({hasText:'Brouillon enregistré'}).waitFor();
   result.drafts.push({type,path,redirectCorrect:new URL(page.url()).pathname===path,total:await page.locator('.admin-document-summary').innerText().then(t=>t.includes('34,02')),blocked:await page.getByRole('button',{name:'Émettre et archiver le PDF',exact:true}).isDisabled()});
   result.noInjectedScript=await page.evaluate(()=>!window.fixtureExecuted);
   const pdf=await page.request.get(base+path+'/pdf');
   result.drafts.at(-1).pdfStatus=pdf.status();
   result.drafts.at(-1).pdf=pdf.status()===200 && pdf.headers()['content-type']==='application/pdf' && (await pdf.body()).subarray(0,5).toString()==='%PDF-';
   result.drafts.at(-1).privatePdf=pdf.headers()['cache-control']==='no-store, private';
   for(const width of [1440,375,320])await check(path,type,width);
  }
  result.stage='search';
  for(const width of [1440,375,320])await check('/admin/documents?q=RECETTE%20DOCUMENTS','index',width);
  const csrf=await page.request.post(base+result.drafts[0].path+'/emettre',{form:{revision:'1'}});
  result.csrfRejected=csrf.status()===419;
  const browser=page.context().browser();
  const outsider=await browser.newContext();
  const hidden=await outsider.request.get(base+result.drafts[0].path+'/pdf',{maxRedirects:0});
  result.privateStatus=hidden.status();result.privateRedirect=new URL(hidden.headers()['location']||'/',base).pathname;
  result.privateAccess=hidden.status()===302 && hidden.headers()['location'].endsWith('/admin/connexion');
  await outsider.close();
  result.stage='without-js';
  const plain=await browser.newContext({javaScriptEnabled:false,storageState:await page.context().storageState()});
  const plainPage=await plain.newPage();
  await plainPage.goto(base+result.drafts[0].path);
  await plainPage.locator('[data-document-editor]').getByRole('button',{name:'Enregistrer le brouillon et recalculer',exact:true}).click();
  result.withoutJs=(await plainPage.locator('body').innerText()).includes('Brouillon enregistré');
  await plain.close();
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/admin/documents');
  result.stage='done';
 }catch(error){result.failure=true;result.failureKind=error.name;}
 return result;
}'''.replace('USERNAME', json.dumps(username)).replace('PASSWORD', json.dumps(password))
if '--screenshots-only' in sys.argv:
    data = json.loads((ROOT/'output/qa-commerce/documents-browser.json').read_text())
    path = next(d['path'] for d in data['drafts'] if d['type'] == 'invoice')
    code = r'''async (page) => {
        page.setDefaultTimeout(10000);
        await page.setViewportSize({width:375,height:1000});
        await page.goto('http://127.0.0.1:8080'+PATH);
        await page.getByRole('heading',{name:'Facture · Brouillon',exact:true}).waitFor();
        await page.screenshot({path:'output/playwright/documents-mobile-top.png'});
        await page.locator('.admin-document-summary').scrollIntoViewIfNeeded();
        await page.screenshot({path:'output/playwright/documents-mobile-issue.png'});
        await page.setViewportSize({width:1440,height:1000});
        await page.goto('http://127.0.0.1:8080/admin/documents');
        return {screenshots:2,synthetic:true};
    }'''.replace('PATH', json.dumps(path))
try:
    proc = subprocess.run(['/Users/akm/.codex/skills/playwright/scripts/playwright_cli.sh', '-s=homya-commerce', 'run-code', code], cwd=ROOT, capture_output=True, text=True, timeout=150)
except subprocess.TimeoutExpired:
    print(json.dumps({'result':'failed','reason':'BROWSER_TIMEOUT'}))
    raise SystemExit(1)
if proc.returncode or '### Error' in proc.stdout:
    print(json.dumps({'result': 'failed', 'reason': 'BROWSER_CHECK_FAILED'}))
    raise SystemExit(1)
result = json.JSONDecoder().raw_decode(proc.stdout.split('### Result\n', 1)[1])[0]
if '--screenshots-only' in sys.argv:
    print(json.dumps(result))
    assert result.get('screenshots') == 2
    raise SystemExit(0)
(ROOT/'output/qa-commerce/documents-browser.json').write_text(json.dumps(result, indent=2)+'\n')
print(json.dumps(result))
assert not result.get('failure') and not result['errors'] and not result['external']
assert all(result[key] for key in ['login','noInjectedScript','csrfRejected','privateAccess','withoutJs'])
assert len(result['pages']) == 12 and all(p['status']==200 and p['h1']==1 and not p['overflow'] and p['private'] and p['noindex'] for p in result['pages'])
assert len(result['drafts']) == 3 and all(all(d[key] for key in ['redirectCorrect','total','blocked','pdf','privatePdf']) for d in result['drafts'])
