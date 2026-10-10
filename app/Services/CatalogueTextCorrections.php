<?php

namespace App\Services;

/** Editorial projection only: the approved archive and profile remain untouched. */
final class CatalogueTextCorrections
{
    private static ?array $rules = null;

    public static function apply(string $source, string $locale, string $translated): string
    {
        self::$rules ??= json_decode(file_get_contents(__DIR__.'/../../lang/catalogue-editorial.json'), true, flags: JSON_THROW_ON_ERROR);
        $rule = self::$rules[hash('sha256', $source)][$locale] ?? null;
        if (! is_array($rule) || hash('sha256', $translated) !== $rule['expected_sha256']) {
            return $translated;
        }

        return strtr($translated, $rule['replacements']);
    }
}
