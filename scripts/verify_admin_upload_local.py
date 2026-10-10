"""Upload a clearly synthetic QA image to an existing unpublished QA draft only."""
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
access = sorted(Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA/storage/app/private/admin').glob('access-*.txt'))[-1].read_text()
code = r'''async (page) => {
 const result={stage:'login'};
 try {
  const base='http://127.0.0.1:8080';
  await page.goto(base+'/admin/connexion');
  await page.getByLabel('Identifiant',{exact:true}).fill(USERNAME);
  await page.getByLabel('Mot de passe',{exact:true}).fill(PASSWORD);
  await page.getByRole('button',{name:'Se connecter au dashboard'}).click();
  await page.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
  result.stage='draft';
  await page.goto(base+'/admin/stocks?q=RECETTE-DASHBOARD-');
  await page.getByRole('link',{name:'Modifier',exact:true}).first().click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  if(await page.locator('[name=published]').isChecked() || ! (await page.locator('[name=reference]').inputValue()).startsWith('RECETTE-DASHBOARD-')) throw Error('Fixture only');
  result.stage='upload';
  await page.locator('[name=image]').setInputFiles('/private/tmp/homya-admin-upload-fixture.png');
  await page.getByRole('button',{name:'Enregistrer le produit',exact:true}).click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  const image=page.locator('.admin-product-preview').first();
  await image.waitFor();
  result.image=await image.evaluate(el=>({loaded:el.complete && el.naturalWidth===600,webp:new URL(el.src).pathname.endsWith('.webp')}));
  result.unpublished=!(await page.locator('[name=published]').isChecked());
  result.stage='gallery';
  await page.locator('[name="images[]"]').setInputFiles('/private/tmp/homya-admin-upload-fixture.png');
  await page.getByRole('button',{name:'Enregistrer le produit',exact:true}).click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  result.gallery=await page.locator('.admin-product-preview').count()===2;
  const csrf=await page.request.post(base+'/admin/parametres',{data:{}});
  result.settingsCsrfRefused=[403,419].includes(csrf.status());
  await page.goto(base+'/admin');
  await page.getByRole('button',{name:'Se déconnecter',exact:true}).click();
  await page.getByRole('heading',{name:'Connexion administrateur',exact:true}).waitFor();
  result.stage='done';
 } catch { result.failure=true; }
 return result;
}'''.replace('USERNAME',json.dumps(re.search(r'Identifiant : (.+)',access)[1])).replace('PASSWORD',json.dumps(re.search(r'Mot de passe : (.+)',access)[1]))
proc=subprocess.run(['/Users/akm/.codex/skills/playwright/scripts/playwright_cli.sh','-s=homya-commerce','run-code',code],cwd=ROOT,text=True,capture_output=True)
if proc.returncode or '### Error' in proc.stdout:
    print(json.dumps({'result':'failed','reason':'UPLOAD_QA_FAILED'}))
    raise SystemExit(1)
result=json.JSONDecoder().raw_decode(proc.stdout.split('### Result\n',1)[1])[0]
(ROOT/'output/qa-commerce/admin-management-upload.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result))
assert not result.get('failure') and result['image']['loaded'] and result['image']['webp'] and result['unpublished'] and result['gallery'] and result['settingsCsrfRefused']
