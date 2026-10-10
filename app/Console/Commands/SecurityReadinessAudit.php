<?php

namespace App\Console\Commands;

use App\Services\ProductionSecurity;
use Illuminate\Console\Command;

final class SecurityReadinessAudit extends Command
{
    protected $signature = 'homya:security-audit {--json : Return check names and booleans only}';

    protected $description = 'Check production security configuration without SQL, network requests or secret output';

    public function handle(ProductionSecurity $security): int
    {
        $checks = $security->checks();
        $passed = ! in_array(false, $checks, true);
        if ($this->option('json')) {
            $this->line(json_encode(['domain' => ProductionSecurity::HOST, 'configuration_passed' => $passed,
                'checks' => $checks, 'hosting_tls_permissions_backups_verified' => false], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $name => $ok) {
                $this->line(($ok ? 'OK ' : 'FAIL ').$name);
            }
            $this->comment('Hosting, HTTPS, document root, permissions and backups must also be verified on the target host.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
