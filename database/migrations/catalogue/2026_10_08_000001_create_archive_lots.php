<?php

use App\Services\CatalogueArchiveSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        app(CatalogueArchiveSchema::class)->apply('archive_lots');
    }

    public function down(): void
    {
        throw new RuntimeException('DESTRUCTIVE_ROLLBACK_NOT_AUTHORISED');
    }
};
