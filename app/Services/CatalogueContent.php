<?php

namespace App\Services;

/** Public editorial projection. Never changes the immutable supplier archive. */
final class CatalogueContent
{
    private static ?array $rules = null;

    public static function apply(array $source, array $product, string $locale): array
    {
        self::$rules ??= json_decode(file_get_contents(__DIR__.'/../../lang/catalogue-content.json'), true, flags: JSON_THROW_ON_ERROR);
        $rule = self::$rules[$source['slug']] ?? null;
        if ($rule && hash_equals($rule['source_name_sha256'], hash('sha256', $source['name']))) {
            $product['name'] = $rule['names'][$locale];
            $product['title_source'] = 'trained_algorithmic_media';
            if ($rule['requires_review'] ?? false) {
                $product['issues'][] = 'title_content_requires_review';
            }
        }
        if (! array_key_exists('description', $product)) {
            return $product;
        }
        if (trim($product['description']) === '') {
            // Only already displayed facts; no invented benefits, certification or specifications.
            $product['description'] = $product['name'].'. '.match ($locale) {
                'en' => 'Catalogue reference: ', 'de' => 'Katalogreferenz: ', default => 'Référence catalogue : ',
            }.$product['reference'].'.';
            $product['description_source'] = 'trained_algorithmic_media';
            $product['description_hidden'] = false;
            $product['issues'] = array_values(array_diff($product['issues'], ['description_requires_review', 'unresolved_description']));
        }
        // Exact duplicates are ingestion artefacts. Different values remain unresolved.
        $groups = [];
        foreach ($source['specifications'] ?? [] as $index => $spec) {
            $groups[trim($spec['label'])][$index] = trim($spec['value']);
        }
        $specifications = [];
        $conflicts = [];
        foreach ($groups as $values) {
            $first = array_key_first($values);
            $spec = $product['specifications'][$first];
            if (count(array_unique($values)) > 1) {
                $conflicts[] = ['label' => $spec['label'], 'values' => array_values(array_unique($values))];
                // Do not publicly present two incompatible values as established facts.
                $spec['value'] = match ($locale) {
                    'en' => 'Awaiting verification', 'de' => 'Wird geprüft', default => 'En cours de vérification',
                };
            }
            $specifications[] = $spec;
        }
        $product['specifications'] = $specifications;
        $product['specification_reviews'] = $conflicts;
        if (! $conflicts) {
            $product['issues'] = array_values(array_diff($product['issues'], ['conflicting_specification']));
        } elseif (! in_array('conflicting_specification', $product['issues'], true)) {
            $product['issues'][] = 'conflicting_specification';
        }
        // Detect an impossible interval; never guess a missing decimal point or nominal power.
        $powers = [];
        foreach ($source['specifications'] ?? [] as $index => $spec) {
            if (preg_match('/\APuissance (?:(?:nominale|thermique) )?(min|max) \(kW\)\z/u', $spec['label'], $match)
                && preg_match('/\A\d+(?:[.,]\d+)?\z/', trim($spec['value']))) {
                $powers[$match[1]] = (float) str_replace(',', '.', $spec['value']);
            }
        }
        if (isset($powers['min'], $powers['max']) && $powers['min'] > $powers['max']) {
            $product['issues'][] = 'inconsistent_power_interval';
            foreach ($source['specifications'] as $index => $spec) {
                if (preg_match('/\APuissance (?:(?:nominale|thermique) )?(?:min|max) \(kW\)\z/u', $spec['label'])) {
                    $label = $product['specifications'][array_search(trim($spec['label']), array_keys($groups), true)]['label'];
                    $product['specification_reviews'][] = ['label' => $label, 'values' => [$spec['value']], 'reason' => 'minimum_exceeds_maximum'];
                    foreach ($product['specifications'] as &$presented) {
                        if ($presented['label'] === $label) {
                            $presented['value'] = match ($locale) {
                                'en' => 'Awaiting verification', 'de' => 'Wird geprüft', default => 'En cours de vérification',
                            };
                        }
                    }
                    unset($presented);
                }
            }
        }
        $product['issues'] = array_values(array_unique($product['issues']));

        return $product;
    }
}
