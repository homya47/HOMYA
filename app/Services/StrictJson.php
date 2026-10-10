<?php

namespace App\Services;

use JsonException;
use RuntimeException;

/** Validate decoded object keys too: PHP's decoder otherwise silently keeps the last duplicate. */
final class StrictJson
{
    public static function decode(string $json, bool $associative = true): mixed
    {
        try {
            $value = json_decode($json, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('JSON_INVALID');
        }

        $offset = 0;
        self::scanValue($json, $offset);

        return $value;
    }

    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            throw new RuntimeException('JSON_INVALID');
        }
    }

    /** Object key order is immaterial; array order, JSON types and exact keys are not. */
    public static function same(mixed $left, mixed $right): bool
    {
        if (get_debug_type($left) !== get_debug_type($right)) {
            return false;
        }
        if ($left instanceof \stdClass) {
            $a = get_object_vars($left);
            $b = get_object_vars($right);
            ksort($a, SORT_STRING);
            ksort($b, SORT_STRING);
            if (array_keys($a) !== array_keys($b)) {
                return false;
            }
            foreach ($a as $key => $value) {
                if (! self::same($value, $b[$key])) {
                    return false;
                }
            }

            return true;
        }
        if (is_array($left)) {
            if (array_keys($left) !== array_keys($right)) {
                return false;
            }
            foreach ($left as $key => $value) {
                if (! self::same($value, $right[$key])) {
                    return false;
                }
            }

            return true;
        }

        return $left === $right;
    }

    public static function canonical(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $members = get_object_vars($value);
            ksort($members, SORT_STRING);
            $result = new \stdClass;
            foreach ($members as $key => $member) {
                $result->{(string) $key} = self::canonical($member);
            }

            return $result;
        }
        if (is_array($value)) {
            return array_map(self::canonical(...), $value);
        }

        return $value;
    }

    private static function whitespace(string $json, int &$offset): void
    {
        $offset += strspn($json, " \t\r\n", $offset);
    }

    private static function scanString(string $json, int &$offset): string
    {
        $start = $offset++;
        while ($json[$offset] !== '"') {
            $offset += $json[$offset] === '\\' ? 2 : 1;
        }
        $offset++;

        return json_decode(substr($json, $start, $offset - $start), flags: JSON_THROW_ON_ERROR);
    }

    private static function scanValue(string $json, int &$offset): void
    {
        self::whitespace($json, $offset);
        $token = $json[$offset];
        if ($token === '"') {
            self::scanString($json, $offset);
        } elseif ($token === '{') {
            $keys = [];
            $offset++;
            self::whitespace($json, $offset);
            while ($json[$offset] !== '}') {
                $key = "key\0".self::scanString($json, $offset);
                if (isset($keys[$key])) {
                    throw new RuntimeException('JSON_DUPLICATE_KEY');
                }
                $keys[$key] = true;
                self::whitespace($json, $offset);
                $offset++; // colon: syntax already checked by json_decode
                self::scanValue($json, $offset);
                self::whitespace($json, $offset);
                if ($json[$offset] === ',') {
                    $offset++;
                    self::whitespace($json, $offset);
                }
            }
            $offset++;
        } elseif ($token === '[') {
            $offset++;
            self::whitespace($json, $offset);
            while ($json[$offset] !== ']') {
                self::scanValue($json, $offset);
                self::whitespace($json, $offset);
                if ($json[$offset] === ',') {
                    $offset++;
                }
                self::whitespace($json, $offset);
            }
            $offset++;
        } else {
            $offset += strcspn($json, ",]} \t\r\n", $offset);
        }
    }
}
