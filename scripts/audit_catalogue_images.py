"""Read-only image audit. Pillow required. Original files are never changed.

Reports technical thresholds only; product correspondence, overlays, rights and
Google approval require separate evidence. Missing originals stay 'À vérifier'.
"""
from collections import Counter
import csv
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'storage/app/catalogue'
OUTPUT = ROOT / 'output/qa-catalogue'
SUPPORTED = {'JPEG', 'PNG', 'WEBP', 'GIF', 'BMP', 'TIFF'}


def inspect(path):
    result = {'width': '', 'height': '', 'bytes': path.stat().st_size, 'format': '', 'sha256': '', 'reason': ''}
    data = path.read_bytes()
    result['sha256'] = hashlib.sha256(data).hexdigest()
    try:
        with Image.open(path) as image:
            result.update(width=image.width, height=image.height, format=image.format)
            image.verify()
        reasons = []
        if result['width'] < 500 or result['height'] < 500:
            reasons.append('Sous le seuil annoncé de 500 × 500 au 31 janvier 2027')
        if result['width'] * result['height'] > 64_000_000:
            reasons.append('Plus de 64 mégapixels')
        if result['bytes'] > 16_000_000:
            reasons.append('Plus de 16 Mo')
        if result['format'] not in SUPPORTED:
            reasons.append('Format non listé par Merchant Center')
        result['reason'] = ' ; '.join(reasons)
        result['status'] = 'À remplacer' if reasons else 'Seuils techniques vérifiés'
        result['recommended_1500'] = result['width'] >= 1500 and result['height'] >= 1500
    except Exception as error:
        result.update(status='À remplacer', reason='Lecture image impossible : ' + type(error).__name__, recommended_1500=False)
    return result


def main():
    OUTPUT.mkdir(parents=True, exist_ok=True)
    rows, images = [], {}
    for file in sorted((ARCHIVE / 'products').glob('*.json')):
        product = json.loads(file.read_text())
        for number, image in enumerate(product['images'], 1):
            local = image.get('local_url')
            path = ROOT / 'public' / local.lstrip('/') if local else None
            if path and path.is_file():
                if local not in images:
                    images[local] = inspect(path)
                result = images[local]
            else:
                result = dict(status='À vérifier', reason='Original local indisponible', width='', height='', bytes='', format='', sha256='', recommended_1500=False)
            rows.append(dict(product=product['slug'], name=product['name'], reference=product['reference'],
                             position=number, primary=number == 1, file=local or '', **result))
    columns = list(rows[0]) if rows else []
    for filename, selection in [('images-inventaire.csv', rows), ('images-a-remplacer.csv', [r for r in rows if r['status'] != 'Seuils techniques vérifiés'])]:
        with (OUTPUT / filename).open('w', newline='', encoding='utf-8-sig') as handle:
            writer = csv.DictWriter(handle, fieldnames=columns)
            writer.writeheader()
            writer.writerows(selection)
    baseline_path = OUTPUT / 'avant-langues-images.json'
    baseline = json.loads(baseline_path.read_text()) if baseline_path.exists() else {}
    modified_images, changed_business = [], []
    for slug, original in baseline.items():
        product = json.loads((ARCHIVE / 'products' / (slug + '.json')).read_text())
        business = {key: value for key, value in product.items() if key != 'images'}
        digest = hashlib.sha256(json.dumps(business, sort_keys=True, ensure_ascii=False).encode()).hexdigest()
        if digest != original['data_sha256']:
            changed_business.append(slug)
        for local, digest in original['images'].items():
            if images.get(local, {}).get('sha256') != digest:
                modified_images.append(local)
    primary = [row for row in rows if row['primary']]
    summary = dict(checked_at=datetime.now(timezone.utc).isoformat(), product_count=len(primary), image_associations=len(rows),
                   unique_local_images=len(images), primary_statuses=dict(Counter(r['status'] for r in primary)),
                   all_statuses=dict(Counter(r['status'] for r in rows)), unique_replace_images=sum(i['status'] == 'À remplacer' for i in images.values()),
                   recommendation_1500_unique=sum(i['recommended_1500'] for i in images.values()),
                   original_images_modified=modified_images, source_business_modified=changed_business,
                   minimum_effective='2027-01-31', consultation_date='2026-10-07',
                   sources=['https://support.google.com/merchants/answer/16989427?hl=en', 'https://support.google.com/merchants/answer/6324350?hl=en'],
                   limits='Dimensions, bytes, format and decodability only. No full visual/rights review, no public crawl or Merchant Center approval. Originals preserved.')
    (OUTPUT / 'images-synthese.json').write_text(json.dumps(summary, ensure_ascii=False, indent=2))
    print(json.dumps(summary, ensure_ascii=False), flush=True)
    if changed_business or modified_images:
        raise SystemExit('Originals or business data differ from baseline: inspect before claiming preservation.')


if __name__ == '__main__':
    main()
