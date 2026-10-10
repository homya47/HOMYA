"""Prepare static EN/DE catalogue text offline. Never changes source records/images.

Requires ctranslate2 and sentencepiece in an isolated preparation environment only.
Models: official Argos fr_en 1.9 and en_de 1.3, provided via --models.
Resume cache is saved every batch. Numeric/code mismatches use conservative segments.
No web translation API, database, or application runtime dependency.
"""
import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'storage/app/catalogue'
DESTINATION = ARCHIVE / 'localizations'
NUMBERS = re.compile(r'\d+(?:[.,]\d+)*')
CODES = re.compile(r'\b(?=[A-Za-z0-9/-]*[A-Z])(?=[A-Za-z0-9/-]*\d)[A-Za-z0-9]+(?:[-/][A-Za-z0-9]+)*\b')
PROTECTED = re.compile(r'\b(?:m²|m³|cm²|cm³|H₂O|CO₂|O₂)\b|' + CODES.pattern + r'|\d+(?:[.,]\d+)*')


def clean(text):
    text = re.sub(r'https?://[^\s]*damdam[^\s]*', '', text, flags=re.I)
    return re.sub('damdam', 'HOMYA', text, flags=re.I).strip()


def product_texts(product):
    yield product['name']
    yield product['category']['name']
    yield product.get('description', '')
    for spec in product.get('specifications', []):
        yield spec['label']
        yield spec['value']
    for variant in product.get('variants', []):
        yield variant.get('title', '')


def texts():
    return sorted({clean(t) for path in (ARCHIVE / 'products').glob('*.json')
                   for t in product_texts(json.loads(path.read_text())) if clean(t)})


def numeric_signature(text):
    return Counter(NUMBERS.findall(text))


def safe(source, target):
    return bool(target.strip()) and numeric_signature(source) == numeric_signature(target) and all(
        code in target for code in CODES.findall(source))


def technical(text):
    # Codes, symbols and number/unit values have no prose to translate.
    remainder = PROTECTED.sub('', text)
    remainder = re.sub(r'\b(?:cm|mm|m|kg|g|kW|W|kWh|Wh|Hz|V|A|L|l|dB|h|min|rpm|kPa|bar|IP|ISO|EN|DIN)\b', '', remainder)
    if not re.search('[A-Za-wy-zÀ-ÖØ-öø-ÿ]', remainder):
        return True
    return bool(re.fullmatch(r'[A-G][+]*|[A-Za-z0-9_-]+_conf', text)) or text in {'PVC', 'MDF', 'ABS', 'LCD', 'LED', 'OLED', 'QLED', 'HD', 'UHD', 'Wi-Fi', 'Bluetooth'}


def atomic(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix('.tmp')
    temporary.write_text(json.dumps(data, ensure_ascii=False, separators=(',', ':')))
    temporary.replace(path)


class Engine:
    def __init__(self, path):
        import ctranslate2
        import sentencepiece
        self.tokenizer = sentencepiece.SentencePieceProcessor(model_file=str(path / 'sentencepiece.model'))
        self.model = ctranslate2.Translator(str(path / 'model'), device='cpu', compute_type='int8', intra_threads=4)

    def batch(self, values):
        encoded = [self.tokenizer.encode(value, out_type=str) for value in values]
        # Catalogue texts are short; no input truncation, sufficient output budget.
        results = self.model.translate_batch(encoded, beam_size=4, max_input_length=0,
                                             max_decoding_length=1024, batch_type='tokens', max_batch_size=2048)
        return [self.tokenizer.decode(result.hypotheses[0]).replace('▁', ' ').strip() for result in results]

    def guarded(self, source, translated):
        if safe(source, translated):
            return translated, False
        # Preserve every number and model code verbatim rather than accepting altered facts.
        pieces = re.split('(' + PROTECTED.pattern + ')', source)
        positions = [i for i, piece in enumerate(pieces) if i % 2 == 0 and re.search('[A-Za-zÀ-ÖØ-öø-ÿ]', piece)]
        for i, target in zip(positions, self.batch([pieces[i] for i in positions])):
            leading = ' ' if pieces[i][:1].isspace() else ''
            trailing = ' ' if pieces[i][-1:].isspace() else ''
            pieces[i] = leading + target + trailing
        target = ''.join(pieces)
        if not safe(source, target):
            raise ValueError('Protected data could not be retained: ' + source[:100])
        return target, True


def generate(models):
    values = texts()
    digest = hashlib.sha256('\n'.join(values).encode()).hexdigest()
    engines = {'en': Engine(models / 'translate-fr_en-1_9'), 'de': Engine(models / 'translate-en_de-1_3')}
    glossary_path = ROOT / 'scripts/catalogue-glossary.json'
    glossary = json.loads(glossary_path.read_text()) if glossary_path.exists() else {}
    from refine_catalogue_localizations import title_translation
    phrases = json.loads((ROOT / 'scripts/catalogue-phrases.json').read_text())
    for product_path in (ARCHIVE / 'products').glob('*.json'):
        product = json.loads(product_path.read_text())
        if product['universe'] not in ['heating', 'appliances', 'firewood']:
            continue
        for i, locale in enumerate(['en', 'de']):
            target = title_translation(product, phrases, i)
            if target:
                glossary[locale].setdefault(clean(product['name']), target)
                if product['description'] == product['name'] + ', ' + product['brand'] + '.':
                    glossary[locale].setdefault(clean(product['description']), target + ', ' + clean(product['brand']) + '.')
    for locale, engine in engines.items():
        path = DESTINATION / (locale + '.json')
        data = json.loads(path.read_text()) if path.exists() else {'strings': {}, 'segmented_review': []}
        mapping = data['strings']
        mapping.update(glossary.get(locale, {}))
        remaining = [value for value in values if value not in mapping]
        for start in range(0, len(remaining), 48):
            batch = remaining[start:start + 48]
            originals = [value if locale == 'en' else english[value] for value in batch]
            translated = engine.batch(originals)
            for value, original, target in zip(batch, originals, translated):
                if technical(value):
                    mapping[value] = value
                    continue
                target, segmented = engine.guarded(original, target)
                if not safe(value, target):
                    target, segmented = engine.guarded(value, target)
                # Unit name, not an invented measurement/conversion.
                target = re.sub(r'\bstères?\b', 'stacked cubic metres' if locale == 'en' else 'Raummeter', target, flags=re.I)
                mapping[value] = target
                if segmented:
                    data['segmented_review'].append(value)
            data.update(version=1, source_digest=digest, locale=locale, strings=mapping,
                        method='Argos/OPUS-MT offline; French–English' + ('–German' if locale == 'de' else ''),
                        human_review='Shared glossary and samples; full technical editorial review pending')
            atomic(path, data)
            print(locale, 'prepared', len(mapping), '/', len(values), flush=True)
        if locale == 'en':
            english = mapping
        invalid = [value for value in values if value not in mapping or not safe(value, mapping[value])]
        if invalid:
            raise ValueError('Invalid or missing translations: ' + str(invalid[:10]))
        data.update(version=1, source_digest=digest, locale=locale, strings=mapping)
        atomic(path, data)
    print('Complete:', len(values), 'source strings in each locale', flush=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--models', type=Path, required=True)
    args = parser.parse_args()
    generate(args.models)
