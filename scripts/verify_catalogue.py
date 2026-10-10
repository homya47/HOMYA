"""Verify local catalogue integrity without network, SQL, or modifying source data."""
import collections
import datetime
import hashlib
import json
import re
import sys
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'storage/app/catalogue'
MEDIA = ROOT / 'public/media/catalogue'
OUTPUT = ROOT / 'output/qa-catalogue/integrite-donnees.json'


def main():
    archive = json.loads((ARCHIVE / 'index.json').read_text())
    inventory = json.loads((ARCHIVE / 'inventory.json').read_text())
    products = archive['products']
    issues = collections.Counter()
    universes = collections.Counter()
    errors = []
    slugs = set()
    source_urls = set()
    media_checked = set()
    galleries = 0
    variants = 0
    missing_specifications = 0
    for entry in products:
        slug = entry['slug']
        if not re.fullmatch('[a-z0-9][a-z0-9-]{0,220}', slug) or slug in slugs:
            errors.append({'slug': slug, 'check': 'unique_safe_identity'})
            continue
        slugs.add(slug)
        path = ARCHIVE / 'products' / (slug + '.json')
        if not path.is_file():
            errors.append({'slug': slug, 'check': 'detail_file_exists'})
            continue
        product = json.loads(path.read_text())
        for key in ['slug', 'name', 'brand', 'reference', 'category', 'universe', 'condition', 'price_cents', 'previous_price_cents', 'currency', 'source_url']:
            if entry[key] != product[key]:
                errors.append({'slug': slug, 'check': 'index_detail_match', 'field': key})
        expected_url = 'https://www.damdam.fr/produit/' + slug
        if product['source_url'] != expected_url:
            errors.append({'slug': slug, 'check': 'canonical_source_url'})
        source_urls.add(product['source_url'])
        price = product['price_cents']
        if price is not None and (type(price) is not int or price <= 0):
            errors.append({'slug': slug, 'check': 'positive_integer_cents'})
        if product['currency'] != 'EUR':
            errors.append({'slug': slug, 'check': 'currency'})
        if {'stock', 'trackStock', 'reviews', 'clients', 'orders', 'password', 'token'} & product.keys():
            errors.append({'slug': slug, 'check': 'excluded_source_data'})
        if not all(isinstance(s['label'], str) and s['label'] and isinstance(s['value'], str) and s['value'] for s in product['specifications']):
            errors.append({'slug': slug, 'check': 'specification_fields'})
        pairs = [(s['label'], s['value']) for s in product['specifications']]
        if len(set(pairs)) != len(pairs):
            errors.append({'slug': slug, 'check': 'exact_duplicate_specs'})
        if not pairs:
            missing_specifications += 1
        issues.update(product['issues'])
        universes.update([product['universe']])
        galleries += len(product['images'])
        variants += bool(product['has_variants'])
        for image in product['images']:
            source = urlparse(image['source_url'])
            if source.scheme != 'https' or source.hostname not in {'damdam.b-cdn.net', 'res.cloudinary.com'}:
                errors.append({'slug': slug, 'check': 'approved_gallery_origin'})
        if product['images']:
            image = product['images'][0]
            local = image.get('local_url', '')
            if not local.startswith('/media/catalogue/') or Path(local).name != local.removeprefix('/media/catalogue/'):
                errors.append({'slug': slug, 'check': 'primary_media_location'})
                continue
            path = MEDIA / Path(local).name
            if not path.is_file():
                errors.append({'slug': slug, 'check': 'primary_media_exists'})
                continue
            if path not in media_checked:
                data = path.read_bytes()
                if image.get('sha256') and hashlib.sha256(data).hexdigest() != image['sha256']:
                    errors.append({'slug': slug, 'check': 'primary_media_hash'})
                if image.get('bytes') and len(data) != image['bytes']:
                    errors.append({'slug': slug, 'check': 'primary_media_size'})
                media_checked.add(path)
    expected_urls = set(inventory['product_urls'])
    if source_urls != expected_urls:
        errors.append({'check': 'sitemap_coverage', 'missing': sorted(expected_urls - source_urls), 'extra': sorted(source_urls - expected_urls)})
    if not archive['meta'].get('complete') or len(products) != archive['meta']['reported_total']:
        errors.append({'check': 'complete_collection'})
    report = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'passed': not errors,
              'product_count': len(products), 'unique_slugs': len(slugs), 'sitemap_urls': len(expected_urls),
              'local_primary_media_checked': len(media_checked), 'gallery_urls': galleries, 'products_with_variants': variants,
              'universes': dict(universes), 'missing_specifications': missing_specifications,
              'source_review_flags': dict(issues), 'primary_media_bytes': sum(p.stat().st_size for p in media_checked),
              'errors': errors, 'limits': 'Checks local structure, provenance and integer amounts; does not certify source specifications or independently verify all gallery images.'}
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(report, ensure_ascii=False, indent=2))
    print(json.dumps(report, ensure_ascii=False))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    sys.exit(main())
