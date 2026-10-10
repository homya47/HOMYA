"""Apply reviewed domain terminology to prepared translations, preserving source facts.

No network, image changes or source edits. Manufacturer names/model codes are kept.
Run after localize_catalogue.py. Static files remain editable for editorial review.
"""
import json
from pathlib import Path
import re

from localize_catalogue import ARCHIVE, ROOT, atomic, clean, safe


def title_translation(product, phrases, language):
    source = clean(product['name'])
    # A term glossary must not partially translate a sentence. Keep full prose
    # with the sentence translator or a reviewed complete translation.
    if re.search(r'[.!?]\s+[A-Za-zÀ-ÖØ-öø-ÿ]|\b(?:une|fonctionnant|nécessitant|fabriquée|permet|offre|dispose|garantit|pratique|livrée)\b', source, re.I):
        return None
    brand = clean(product['brand'])
    # Protect the stated manufacturer even when it contains ordinary words.
    protections = [brand] if brand and brand.casefold() in source.casefold() else []
    protections.extend(re.findall(r'\b[A-Za-z0-9_/-]*\d[A-Za-z0-9_/-]+\b', source))
    protections.extend(re.findall(r'\b[A-Za-z0-9]+[™®]', source))
    pattern = re.compile(r'(?<![\w])(' + '|'.join(re.escape(k) for k in sorted(phrases, key=len, reverse=True)) + r')(?![\w])', re.I)
    lookup = {key.casefold(): values[language] for key, values in phrases.items()}
    if re.search(r'\bballon\b', source, re.I):
        lookup['tampon'] = 'Buffer' if language == 0 else 'Puffer'
    def replacement(match):
        value = lookup[match[0].casefold()]
        if language == 0:
            value = value.lower()
            if match[0][0].isupper():
                value = value[0].upper() + value[1:]
        return value
    if protections:
        protected = re.compile('(' + '|'.join(re.escape(value) for value in sorted(set(protections), key=len, reverse=True)) + ')', re.I)
        parts = protected.split(source)
        result = ''.join(part if i % 2 else pattern.sub(replacement, part) for i, part in enumerate(parts))
    else:
        result = pattern.sub(replacement, source)
    # Source duplicate nouns do not describe an extra part.
    result = result.replace('Elbow of Elbow', 'Elbow').replace('Rohrbogen Von Rohrbogen', 'Rohrbogen')
    return result if safe(source, result) and result != source else None


def main():
    glossary = json.loads((ROOT / 'scripts/catalogue-glossary.json').read_text())
    phrases = json.loads((ROOT / 'scripts/catalogue-phrases.json').read_text())
    products = [json.loads(path.read_text()) for path in (ARCHIVE / 'products').glob('*.json')]
    for i, locale in enumerate(['en', 'de']):
        path = ARCHIVE / 'localizations' / (locale + '.json')
        data = json.loads(path.read_text())
        mapping = data['strings']
        mapping.update(glossary[locale])
        folded = {source.casefold(): target for source, target in glossary[locale].items()}
        for source in mapping:
            if source.casefold() in folded:
                mapping[source] = folded[source.casefold()]
        changed = 0
        for product in products:
            if product['universe'] not in ['heating', 'appliances', 'firewood']:
                continue
            target = title_translation(product, phrases, i)
            if target:
                mapping[clean(product['name'])] = target
                if product['description'] == product['name'] + ', ' + product['brand'] + '.':
                    mapping[clean(product['description'])] = target + ', ' + clean(product['brand']) + '.'
                changed += 1
        # Context-sensitive corrections to heating terms; no numerical conversions.
        for source, target in list(mapping.items()):
            # Localized notation for the same rotational-speed unit, no conversion.
            target = re.sub(r'\btr/min\b', 'rpm' if locale == 'en' else 'U/min', target)
            if locale == 'en' and re.search('poêle|granulés|chauffage|étanche', source, re.I):
                target = re.sub(r'\bwaterproof\b', 'room-sealed', target, flags=re.I)
                target = re.sub(r'\b(?:granular|granulated)\b', 'pellet', target, flags=re.I)
                target = re.sub(r'\b(?:channelable|canalizable|channelizable)\b', 'ducted', target, flags=re.I)
                target = re.sub(r'\bgranulés\b', 'pellets', target, flags=re.I)
            if locale == 'de' and re.search('poêle|granulés|chauffage|étanche', source, re.I):
                target = re.sub(r'\b(?:wasserdicht|wasserfest)\b', 'raumluftunabhängig', target, flags=re.I)
            if not safe(source, target):
                raise ValueError('Protected facts changed: ' + source[:80])
            mapping[source] = target
        data['terminology_revision'] = 1
        atomic(path, data)
        print(locale, 'terminology applied to', changed, 'product titles', flush=True)


if __name__ == '__main__':
    main()
