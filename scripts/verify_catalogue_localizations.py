"""Verify offline dictionary coverage and protected facts; export editorial candidates.

No model, network, database or source changes. Coverage does not certify meaning.
"""
import csv
import json

from localize_catalogue import DESTINATION, ROOT, safe, technical, texts


def main():
    values = texts()
    report = {}
    candidates = []
    for locale in ('en', 'de'):
        data = json.loads((DESTINATION / (locale + '.json')).read_text())
        mapping = data['strings']
        missing = [source for source in values if source not in mapping]
        invalid = [source for source in values if source in mapping and not safe(source, mapping[source])]
        unchanged = [source for source in values if mapping.get(source) == source and not technical(source)]
        segmented = set(data.get('segmented_review', [])) & set(values)
        report[locale] = {'source_strings': len(values), 'covered': len(values) - len(missing),
                          'missing': missing, 'numeric_or_code_mismatch': invalid,
                          'unchanged_review_candidates': len(unchanged), 'segmented_candidates': len(segmented)}
        for source in sorted(set(unchanged) | segmented):
            reasons = []
            if source in unchanged:
                reasons.append('Texte identique : vérifier nom propre, emprunt ou traduction')
            if source in segmented:
                reasons.append('Traduction segmentée pour protéger les nombres/références : relire le sens')
            candidates.append([locale, source, mapping.get(source, ''), '; '.join(reasons)])
    destination = ROOT / 'output/qa-catalogue'
    destination.mkdir(parents=True, exist_ok=True)
    (destination / 'langues-couverture.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    with (destination / 'traductions-a-relire.csv').open('w', encoding='utf-8-sig', newline='') as file:
        writer = csv.writer(file)
        writer.writerow(['language', 'source', 'translation', 'reason'])
        writer.writerows(candidates)
    print(json.dumps(report, ensure_ascii=False))
    if any(result['missing'] or result['numeric_or_code_mismatch'] for result in report.values()):
        raise SystemExit(1)


if __name__ == '__main__':
    main()
