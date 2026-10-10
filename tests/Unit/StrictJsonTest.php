<?php

namespace Tests\Unit;

use App\Services\StrictJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StrictJsonTest extends TestCase
{
    public static function invalidJson(): array
    {
        return [
            'simple duplicate' => ['{"a":1,"a":2}'],
            'escaped duplicate' => ['{"a":1,"\\u0061":2}'],
            'nested duplicate' => ['{"x":[{"€":1,"\\u20ac":2}]}'],
            'numeric duplicate' => ['{"0":1,"\\u0030":2}'],
            'empty duplicate' => ['{"":1,"":2}'],
            'invalid syntax' => ['{"a":}'],
            'trailing bytes' => ['[] true'],
            'invalid utf8' => ["{\"x\":\"\xff\"}"],
            'BOM' => ["\xef\xbb\xbf{}"],
        ];
    }

    #[DataProvider('invalidJson')]
    public function test_invalid_or_duplicate_json_is_refused(string $json): void
    {
        $this->expectException(RuntimeException::class);
        StrictJson::decode($json);
    }

    public function test_values_and_keys_in_separate_objects_are_preserved(): void
    {
        $data = StrictJson::decode(" \n{\"x\":[{\"a\":\"\\\"\\\\[]{}\"},{\"a\":null}],\"empty\":\"\",\"missing\":false,\"n\":1.5,\"0\":0} \t");
        $this->assertSame('"\\[]{}', $data['x'][0]['a']);
        $this->assertNull($data['x'][1]['a']);
        $this->assertSame('', $data['empty']);
        $this->assertSame(false, $data['missing']);
        $this->assertSame(1.5, $data['n']);
        $this->assertSame(0, $data[0]);
        $this->assertSame([], StrictJson::decode('[]'));
        $this->assertNull(StrictJson::decode('null'));
    }
}
