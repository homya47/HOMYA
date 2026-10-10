<?php

namespace Tests\Unit;

use App\Services\CatalogueCapsule;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\CatalogueCapsuleFixture;

class CatalogueCapsuleTest extends TestCase
{
    private CatalogueCapsuleFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = new CatalogueCapsuleFixture;
    }

    protected function tearDown(): void
    {
        $this->fixture->destroy();
        parent::tearDown();
    }

    private function service(?\Closure $space = null): CatalogueCapsule
    {
        return new CatalogueCapsule($this->fixture->root, $space);
    }

    private function copy(CatalogueCapsule $service): array
    {
        return $service->copy($this->service()->inspect()['lot_key']);
    }

    public function test_simulation_is_closed_and_does_not_create_a_capsule_or_use_unreferenced_media(): void
    {
        $this->fixture->write('public/media/catalogue/unreferenced.jpg', 'excluded');
        $inspection = $this->service()->inspect();
        $this->assertSame(11, $inspection['file_count']);
        $this->assertSame(6, $inspection['source_files']);
        $this->assertSame(2, $inspection['media_files']);
        $this->assertSame(1, $inspection['review_rows']);
        $this->assertContains($this->fixture->original, array_column($inspection['files'], 'path'));
        $this->assertNotContains('public/media/catalogue/unreferenced.jpg', array_column($inspection['files'], 'path'));
        $this->assertFalse(file_exists($this->fixture->parent()));
    }

    public function test_copy_preserves_all_bytes_then_verifies_without_sources_or_mutations(): void
    {
        $inspection = $this->service()->inspect();
        $result = $this->copy($this->service());
        $this->assertSame($inspection['lot_key'], $result['lot_key']);
        foreach ($inspection['files'] as $entry) {
            $target = $result['path'].'/files/'.$entry['path'];
            $this->assertSame(file_get_contents($this->fixture->root.'/'.$entry['path']), file_get_contents($target));
            $this->assertSame(0400, fileperms($target) & 0777);
        }
        $this->assertSame(0700, fileperms($result['path']) & 0777);
        $this->fixture->write('storage/app/catalogue/index.json', 'external changed source');
        $manifestBefore = hash_file('sha256', $result['path'].'/manifest.json');
        $verified = $this->service()->verify($result['lot_key']);
        $this->assertSame('CAPSULE_VERIFIED_NO_SQL', $verified['status']);
        $this->assertSame($manifestBefore, hash_file('sha256', $result['path'].'/manifest.json'));
    }

    public function test_a_second_copy_never_overwrites_the_same_destination(): void
    {
        $first = $this->copy($this->service());
        try {
            $this->copy($this->service());
            $this->fail('Expected collision refusal');
        } catch (RuntimeException $e) {
            $this->assertSame('CAPSULE_DESTINATION_EXISTS', $e->getMessage());
        }
        $this->assertSame($first['lot_key'], $this->service()->verify($first['lot_key'])['lot_key']);
    }

    public function test_altered_capsule_is_detected(): void
    {
        $result = $this->copy($this->service());
        $target = $result['path'].'/files/'.$this->fixture->media;
        chmod($target, 0600);
        file_put_contents($target, 'altered');
        chmod($target, 0400);
        $this->expectException(RuntimeException::class);
        $this->service()->verify($result['lot_key']);
    }

    public function test_missing_capsule_file_is_detected(): void
    {
        $result = $this->copy($this->service());
        unlink($result['path'].'/files/'.$this->fixture->media);
        $this->expectException(RuntimeException::class);
        $this->service()->verify($result['lot_key']);
    }

    public function test_additional_empty_capsule_directory_is_detected(): void
    {
        $result = $this->copy($this->service());
        mkdir($result['path'].'/unexpected', 0700);
        $this->expectExceptionMessage('CAPSULE_EXTRA_DIRECTORY');
        $this->service()->verify($result['lot_key']);
    }

    public function test_changed_source_without_a_new_review_is_refused(): void
    {
        $this->fixture->write('storage/app/catalogue/products/fixture.json', '{}');
        $this->expectExceptionMessage('SOURCE_REVIEW_CHANGED');
        $this->service()->inspect();
    }

    public function test_reviewed_json_with_duplicate_decoded_keys_is_still_refused(): void
    {
        $this->fixture->write('storage/app/catalogue/localizations/en.json', '{"a":1,"\\u0061":2}');
        $this->fixture->refreshReview();
        $this->expectExceptionMessage('JSON_DUPLICATE_KEY');
        $this->service()->inspect();
    }

    public function test_media_symlink_is_refused_without_following_it(): void
    {
        unlink($this->fixture->root.'/'.$this->fixture->media);
        symlink($this->fixture->root.'/'.$this->fixture->original, $this->fixture->root.'/'.$this->fixture->media);
        $this->expectException(RuntimeException::class);
        $this->service()->inspect();
    }

    public function test_changed_media_claim_is_refused(): void
    {
        $this->fixture->write($this->fixture->media, 'unreviewed');
        $this->expectExceptionMessage('MEDIA_CLAIM_MISMATCH');
        $this->service()->inspect();
    }

    public function test_path_traversal_in_overlay_is_refused(): void
    {
        $this->fixture->writeJson('storage/app/catalogue/media-overrides.json', ['products' => ['fixture' => ['original_file' => '../../.env', 'original_sha256' => str_repeat('a', 64)]]]);
        $this->fixture->refreshReview();
        $this->expectExceptionMessage('ORIGINAL_PATH_INVALID');
        $this->service()->inspect();
    }

    public function test_additional_review_input_is_refused(): void
    {
        $path = $this->fixture->root.'/output/qa-import/empreintes-sources.json';
        $review = json_decode(file_get_contents($path), true);
        $review['.env'] = str_repeat('a', 64);
        $this->fixture->writeJson('output/qa-import/empreintes-sources.json', $review);
        $this->expectExceptionMessage('REVIEW_SOURCE_INVALID');
        $this->service()->inspect();
    }

    public function test_space_refusal_before_copy_creates_no_parent(): void
    {
        try {
            $this->copy($this->service(fn () => 0));
            $this->fail('Expected space refusal');
        } catch (RuntimeException $e) {
            $this->assertSame('DISK_SPACE_INSUFFICIENT', $e->getMessage());
        }
        $this->assertFalse(file_exists($this->fixture->parent()));
    }

    public function test_space_failure_during_copy_keeps_private_incomplete_staging(): void
    {
        $calls = 0;
        $service = $this->service(function () use (&$calls) {
            return ++$calls < 4 ? PHP_INT_MAX : 0;
        });
        try {
            $this->copy($service);
            $this->fail('Expected space refusal');
        } catch (RuntimeException $e) {
            $this->assertSame('DISK_SPACE_INSUFFICIENT', $e->getMessage());
        }
        $stages = glob($this->fixture->parent().'/.staging-*');
        $this->assertCount(1, $stages);
        $this->assertSame(0700, fileperms($stages[0]) & 0777);
        $this->assertCount(1, glob($this->fixture->parent().'/.failure-*.json'));
        $this->assertFalse(file_exists($stages[0].'/manifest.json'));
    }

    public function test_source_mutation_during_copy_is_detected(): void
    {
        $calls = 0;
        $service = $this->service(function () use (&$calls) {
            if (++$calls === 2) {
                $this->fixture->write('storage/app/catalogue/index.json', '{}');
            }

            return PHP_INT_MAX;
        });
        $this->expectException(RuntimeException::class);
        $this->copy($service);
    }

    public function test_io_conflict_does_not_promote_a_partial_copy(): void
    {
        $calls = 0;
        $service = $this->service(function () use (&$calls) {
            if (++$calls === 2) {
                $stage = glob($this->fixture->parent().'/.staging-*')[0];
                file_put_contents($stage.'/files/storage', 'conflict');
            }

            return PHP_INT_MAX;
        });
        $this->expectException(RuntimeException::class);
        $this->copy($service);
    }

    public function test_local_exclusion_lock_refuses_a_concurrent_preparation(): void
    {
        mkdir($this->fixture->parent(), 0700);
        $file = $this->fixture->parent().'/.prepare.lock';
        $lock = fopen($file, 'x');
        chmod($file, 0600);
        flock($lock, LOCK_EX);
        try {
            $this->copy($this->service());
            $this->fail('Expected lock refusal');
        } catch (RuntimeException $e) {
            $this->assertSame('CAPSULE_PREPARATION_BUSY', $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->assertEmpty(glob($this->fixture->parent().'/.staging-*'));
    }

    public function test_manifest_contract_is_independent_of_input_order_and_dates(): void
    {
        $a = ['path' => 'é/123'."\u{2028}".'.json', 'bytes' => 0, 'sha256' => str_repeat('a', 64)];
        $b = ['path' => 'a.json', 'bytes' => 1, 'sha256' => str_repeat('b', 64)];
        $expected = '{"format_version":1,"files":[{"path":"a.json","bytes":1,"sha256":"'.str_repeat('b', 64).'"},{"path":"é/123'."\u{2028}".'.json","bytes":0,"sha256":"'.str_repeat('a', 64).'"}]}';
        $this->assertSame($expected, CatalogueCapsule::manifest([$a, $b]));
        $this->assertSame($expected, CatalogueCapsule::manifest([$b, $a]));
        $before = $this->service()->inspect()['lot_key'];
        touch($this->fixture->root.'/'.$this->fixture->media, time() - 200);
        $this->assertSame($before, $this->service()->inspect()['lot_key']);
    }

    public function test_manifest_duplicate_entry_is_refused(): void
    {
        $entry = ['path' => 'same', 'bytes' => 0, 'sha256' => str_repeat('a', 64)];
        $this->expectExceptionMessage('MANIFEST_ENTRY_INVALID');
        CatalogueCapsule::manifest([$entry, $entry]);
    }

    public function test_copy_refuses_a_different_simulated_key_before_creating_any_directory(): void
    {
        try {
            $this->service()->copy(str_repeat('0', 64));
            $this->fail('Expected simulation binding refusal');
        } catch (RuntimeException $e) {
            $this->assertSame('SIMULATED_LOT_CHANGED', $e->getMessage());
        }
        $this->assertFalse(file_exists($this->fixture->parent()));
    }

    public function test_copy_refuses_if_a_review_report_changes_after_simulation(): void
    {
        $simulatedKey = $this->service()->inspect()['lot_key'];
        $this->fixture->writeJson('output/qa-import/anomalies-detaillees.json', [['id' => 'AN-fixture', 'evidence' => 'changed']]);
        $this->expectExceptionMessage('SIMULATED_LOT_CHANGED');
        $this->service()->copy($simulatedKey);
    }

    public function test_source_change_during_final_copy_verification_blocks_promotion(): void
    {
        $key = $this->service()->inspect()['lot_key'];
        try {
            $this->service()->copy($key, function ($phase) {
                if ($phase === 'verification') {
                    $this->fixture->write('storage/app/catalogue/index.json', '{}');
                }
            });
            $this->fail('Expected final source refusal');
        } catch (RuntimeException $e) {
            $this->assertSame('SOURCE_CHANGED_BEFORE_PROMOTION', $e->getMessage());
        }
        $this->assertFalse(file_exists($this->fixture->parent().'/'.$key));
        $this->assertCount(1, glob($this->fixture->parent().'/.staging-*'));
    }
}
