"""Review local catalogue data and write reports only; never connect to SQL.

No network, migrations, source edits or corrective decisions. Python stdlib.
CSV cells are escaped for spreadsheet use; JSON retains exact source values.
"""
from collections import Counter, defaultdict
from datetime import datetime, timezone
import csv
import hashlib
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'storage/app/catalogue'
OUTPUT = ROOT / 'output/qa-import'


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError('Duplicate JSON object key: ' + key)
        result[key] = value
    return result


def load(path):
    return json.loads(path.read_text(encoding='utf-8'), object_pairs_hook=unique_object)


def csv_cell(value):
    value = str(value)
    if value.startswith(('\t', '\r', '\n')) or value.lstrip().startswith(('=', '+', '-', '@')):
        return "'" + value
    return value


def review_product(product, dictionaries):
    rows = []

    def add(kind, field, evidence, action, status='À vérifier'):
        identity = product['slug'] + '\0' + kind + '\0' + field
        rows.append(dict(id='AN-' + hashlib.sha256(identity.encode()).hexdigest()[:16],
                         product=product['slug'], reference=product['reference'],
                         kind=kind, field=field, status=status, evidence=evidence,
                         recommendation=action, source_file='storage/app/catalogue/products/' + product['slug'] + '.json'))

    groups = defaultdict(list)
    for position, spec in enumerate(product['specifications'], 1):
        groups[spec['label'].casefold()].append(dict(position=position, **spec))
        for field in ('label', 'value'):
            source = spec[field]
            translated = dictionaries.get('de', {}).get(source)
            if (source == 'Fonte' and translated == 'Schriftzug') or (source == 'Trémie hermétique' and translated == 'Hermetischer Hopfen'):
                add('semantic_translation_review', 'de:' + source,
                    {'source': source, 'translation': translated, 'context': spec},
                    'Relire en contexte matériel/foyer : Gusseisen pour Fonte ; traduction technique de trémie à confirmer. Ne pas appliquer un remplacement global sans revue.')
    for label, values in groups.items():
        if len({row['value'] for row in values}) > 1:
            kind = 'possible_multivalued_specification' if label == "zone d'installation" else 'distinct_specification_values'
            add(kind, label, values, 'Conserver toutes les lignes et leur ordre ; vérifier si plusieurs valeurs sont admissibles ou confronter à une fiche constructeur de la référence exacte.')
    if not product['specifications']:
        add('missing_specifications', 'specifications', [], 'Obtenir une fiche métier/constructeur ; conserver absence explicite, sans attribut inventé.', 'Information manquante')
    description = product['description']
    if not description.strip():
        add('empty_filtered_description', 'description', '', 'Rédiger/valider un descriptif technique sans engagement vendeur repris automatiquement.', 'Information manquante')
    elif re.fullmatch(r'\$[a-z0-9]+', description, re.I):
        add('unresolved_description', 'description', description, 'Ne pas publier ce marqueur de sérialisation comme prose ; obtenir le contenu réel.')
    if product['has_variants'] != bool(product['variants']):
        add('variant_flag_mismatch', 'has_variants', {'flag': product['has_variants'], 'rows': len(product['variants'])}, 'Faire confirmer le groupe de variantes.')
    for position, variant in enumerate(product['variants'], 1):
        unknown = [field for field in ('price', 'stock', 'options') if field not in variant]
        if unknown:
            add('variant_business_data_missing', 'variants:' + str(position), {'position': position, 'variant': variant, 'missing': unknown},
                'Archiver la variante sans la rendre achetable ; confirmer prix, disponibilité et options, sans héritage automatique du prix parent.', 'Information manquante')
    # Keep source flags which are not already represented above, including future flags.
    represented = {'missing_specifications', 'conflicting_specification', 'description_requires_review'}
    for flag in product['issues']:
        if flag not in represented:
            add('source_flag', flag, flag, 'Vérifier ce signalement source avant toute correction.')
    # The same semantic source can occur in multiple specification positions.
    return list({row['id']: row for row in rows}.values())


def main():
    inputs = [ARCHIVE / 'index.json', ARCHIVE / 'inventory.json', ARCHIVE / 'media-overrides.json']
    inputs += sorted((ARCHIVE / 'products').glob('*.json'))
    inputs += sorted((ARCHIVE / 'localizations').glob('*.json'))
    fingerprints = {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest() for path in inputs}
    index = load(ARCHIVE / 'index.json')
    if not index.get('meta', {}).get('complete') or not index.get('products'):
        raise ValueError('Catalogue incomplete or empty; do not prepare an import.')
    if any(not re.fullmatch(r'[a-z0-9][a-z0-9-]{0,220}', entry.get('slug', '')) for entry in index['products']):
        raise ValueError('Unsafe product slug; refusing to read a path outside the catalogue.')
    dictionaries = {locale: load(ARCHIVE / 'localizations' / (locale + '.json'))['strings'] for locale in ('en', 'de')}
    products = [load(ARCHIVE / 'products' / (entry['slug'] + '.json')) for entry in index['products']]
    if len({p['slug'] for p in products}) != len(products):
        raise ValueError('Duplicate product slug.')
    rows, errors = [], []
    references = Counter(p['reference'] for p in products)
    categories = defaultdict(set)
    flags = Counter()
    for entry, product in zip(index['products'], products):
        for key in ('slug', 'reference', 'price_cents', 'currency', 'category'):
            if entry[key] != product[key]:
                errors.append({'product': entry['slug'], 'check': 'index_detail_match', 'field': key})
        if type(product['price_cents']) is not int or product['price_cents'] <= 0 or product['currency'] != 'EUR':
            errors.append({'product': product['slug'], 'check': 'integer_price_eur'})
        if not product['reference']:
            errors.append({'product': product['slug'], 'check': 'reference_missing'})
        categories[product['category']['slug']].add(product['category']['name'])
        flags.update(product['issues'])
        rows.extend(review_product(product, dictionaries))
    category_conflicts = {slug: sorted(names) for slug, names in categories.items() if len(names) != 1}
    if category_conflicts:
        errors.append({'check': 'category_names', 'conflicts': category_conflicts})
    counts = Counter(row['kind'] for row in rows)
    # Fingerprints include dictionaries, media overlays and every product; reports live elsewhere.
    unchanged = all(hashlib.sha256((ROOT / path).read_bytes()).hexdigest() == digest for path, digest in fingerprints.items())
    if not unchanged:
        raise ValueError('Source changed during review; regenerate from a stable snapshot.')
    summary = dict(checked_at=datetime.now(timezone.utc).isoformat(), technical_checks_passed=not errors,
                   products=len(products), categories=len(categories),
                   universes=dict(Counter(p['universe'] for p in products)),
                   specifications=sum(len(p['specifications']) for p in products),
                   products_with_variants=sum(bool(p['variants']) for p in products),
                   variant_rows=sum(len(p['variants']) for p in products),
                   gallery_associations=sum(len(p['images']) for p in products),
                   source_flags=dict(flags), detailed_review_rows=len(rows), review_rows_by_kind=dict(counts),
                   review_products_by_kind={kind: len({row['product'] for row in rows if row['kind'] == kind}) for kind in counts},
                   source_flagged_products=sum(bool(p['issues']) for p in products),
                   distinct_products_to_review=len({row['product'] for row in rows}),
                   duplicate_reference_groups={ref: n for ref, n in references.items() if n > 1},
                   captured_from=min(p['captured_at'] for p in products), captured_to=max(p['captured_at'] for p in products),
                   maximum_product_json_bytes=max((ARCHIVE / 'products' / (p['slug'] + '.json')).stat().st_size for p in products),
                   maximum_price_cents=max(p['price_cents'] for p in products),
                   dictionary_entries={locale: len(values) for locale, values in dictionaries.items()},
                   inputs_unchanged=unchanged, input_files_hashed=len(fingerprints), technical_errors=errors,
                   proposed_import_scope='Archive locale non vendable, proposition à valider ; pas importé',
                   global_unknowns=['Stock et source de mise à jour', 'Fiscalité/prix HOMYA vendables', 'Unités et conditionnements', 'Droits et validation métier avant publication'],
                   limits='Divergent text values are review candidates, not certified errors. No source corrections, SQL, import, migrations or commercial validation.')
    OUTPUT.mkdir(parents=True, exist_ok=True)
    for filename, data in [('synthese.json', summary), ('anomalies-detaillees.json', rows), ('empreintes-sources.json', fingerprints)]:
        (OUTPUT / filename).write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')
    with (OUTPUT / 'anomalies-detaillees.csv').open('w', newline='', encoding='utf-8-sig') as handle:
        columns = ['id', 'product', 'reference', 'kind', 'field', 'status', 'evidence', 'recommendation', 'source_file']
        writer = csv.DictWriter(handle, fieldnames=columns)
        writer.writeheader()
        for row in rows:
            writer.writerow({key: csv_cell(json.dumps(value, ensure_ascii=False) if isinstance(value, (list, dict)) else value) for key, value in row.items()})
    print(json.dumps(summary, ensure_ascii=False))
    return 1 if errors else 0


if __name__ == '__main__':
    raise SystemExit(main())
