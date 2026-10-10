<?php

namespace App\Console\Commands;

use App\Services\Catalogue;
use App\Services\Commerce\InitialInventory;
use App\Services\Commerce\OrderStore;
use Illuminate\Console\Command;
use Throwable;

final class PrepareCommerceInventory extends Command
{
    protected $signature = 'homya:commerce-inventory {--apply : Install the already reviewed initial manifest; requires separately authorized SQL} {--sha256= : Exact reviewed manifest digest} {--refresh : Preserve the previous manifest by digest before refreshing preparation}';

    protected $description = 'Prepare a private 1000-unit-per-product inventory manifest without SQL; explicit installation is separate.';

    public function handle(Catalogue $catalogue, InitialInventory $inventory, OrderStore $orders): int
    {
        $directory = storage_path('app/private/commerce');
        $path = $directory.'/initial-inventory-v2.json';
        try {
            if ($this->option('apply')) {
                if (! is_file($path) || is_link($path) || ! hash_equals(hash_file('sha256', $path), (string) $this->option('sha256'))) {
                    throw new \RuntimeException;
                }
                $manifest = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
                $summary = $inventory->install($manifest, $catalogue, $orders);
            } else {
                $manifest = $inventory->prepare($catalogue);
                if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                    throw new \RuntimeException;
                }
                if (is_link($path)) {
                    throw new \RuntimeException;
                }
                if (file_exists($path) && ! $this->option('refresh')) {
                    $this->error('Manifest already exists; preserve it and review before creating another version.');

                    return self::FAILURE;
                }
                if (file_exists($path)) {
                    $backup = $directory.'/initial-inventory-'.hash_file('sha256', $path).'.json';
                    if (file_exists($backup) || ! rename($path, $backup)) {
                        throw new \RuntimeException;
                    }
                }
                $previousMask = umask(0077);
                try {
                    $stream = fopen($path, 'x');
                } finally {
                    umask($previousMask);
                }
                if ($stream === false) {
                    throw new \RuntimeException;
                }
                $bytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
                try {
                    if (fwrite($stream, $bytes) !== strlen($bytes)) {
                        throw new \RuntimeException;
                    }
                } finally {
                    fclose($stream);
                }
                $summary = $manifest['summary'];
            }
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->line('Manifest SHA256: '.hash_file('sha256', $path));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Commerce inventory preparation or installation refused. No private diagnostics displayed.');

            return self::FAILURE;
        }
    }
}
