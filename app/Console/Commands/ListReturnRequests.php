<?php

namespace App\Console\Commands;

use App\Services\ReturnRequests;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ListReturnRequests extends Command
{
    protected $signature = 'homya:returns-list';

    protected $description = 'List recorded withdrawal references without exposing customer data';

    public function handle(ReturnRequests $requests): int
    {
        $directory = storage_path('app/private/returns');
        $rows = [];
        $invalid = 0;
        foreach (is_dir($directory) ? File::files($directory) : [] as $file) {
            if ($file->getExtension() !== 'encrypted') {
                continue;
            }
            try {
                $record = $requests->find($file->getBasename('.encrypted'));
                $rows[] = [$record['id'], $record['submitted_at'], $record['status']];
            } catch (\Throwable) {
                $invalid++;
            }
        }
        $this->table(['Reference', 'Received (UTC)', 'Status'], $rows);
        if ($invalid > 0) {
            $this->warn('Unreadable records: '.$invalid.'; inspect private storage.');
        }

        return $invalid > 0 ? self::FAILURE : self::SUCCESS;
    }
}
