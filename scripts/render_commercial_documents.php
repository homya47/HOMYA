<?php

// Isolated visual fixtures. Never creates a merchant invoice or changes MariaDB.
use App\Services\Documents\DocumentData;
use App\Services\Documents\DocumentStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

putenv('APP_ENV=testing');
putenv('HOMYA_CATALOGUE_SOURCE=files');
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$temp = sys_get_temp_dir().'/homya-commercial-render-'.Str::uuid();
File::ensureDirectoryExists($temp, 0700);
$app->useStoragePath($temp);
$output = base_path('output/qa-commerce/documents');
File::ensureDirectoryExists($output);
$dataService = app(DocumentData::class);
try {
    foreach (array_keys(DocumentData::TYPES) as $type) {
        $data = $dataService->blank($type);
        $data['customer'] = array_replace($data['customer'], ['name' => 'RECETTE UNIQUEMENT — Élodie Démonstration', 'company' => 'Client fictif', 'address' => '12 rue de la Démonstration', 'city' => 'Paris', 'postal_code' => '75001', 'country' => 'FR', 'email' => 'test@example.invalid']);
        $data['tax_mode'] = 'taxed';
        $data['valid_until'] = now('Europe/Paris')->addDays(10)->toDateString();
        $data['operation_date'] = now('Europe/Paris')->toDateString();
        $data['due_date'] = now('Europe/Paris')->addDays(5)->toDateString();
        $data['payment_terms'] = "Conditions fictives de recette. Aucune somme à régler.\nRèglement non confirmé.";
        $data['delivery_terms'] = 'Exemple de livraison. Aucun engagement commercial réel.';
        $data['discount_terms'] = 'Escompte fictif : néant';
        $data['eco_reviewed'] = $data['guarantee_reviewed'] = true;
        $data['legal_notes'] = 'Document synthétique pour contrôle du rendu uniquement. Ne pas utiliser pour une vente réelle.';
        $data['lines'] = [
            ['reference' => 'RECETTE-CHAUFFAGE-001', 'name' => 'Appareil de chauffage — description et éco-participation fictives', 'quantity' => 2, 'price' => '1196,19', 'vat_rate' => '20', 'eco' => '2.00'],
            ['reference' => 'RECETTE-BOIS-002', 'name' => 'Produit de démonstration — taux distinct fictif', 'quantity' => 1, 'price' => '12,34', 'vat_rate' => '5.50', 'eco' => ''],
        ];
        $store = app(DocumentStore::class);
        $record = $store->create($type, copy: $data);
        file_put_contents($output.'/'.$type.'-draft.pdf', $store->pdf($record));
        $issued = $store->issue($record['id'], 1, ['prefix' => 'TEST-'.strtoupper(str_replace('_', '-', $type)), 'first_sequence' => 1, 'series_confirmed' => true]);
        file_put_contents($output.'/'.$type.'-issued.pdf', $store->pdf($issued));
        if ($type === 'invoice') {
            $data['lines'] = array_fill(0, 28, $data['lines'][0]);
            foreach ($data['lines'] as $index => &$line) {
                $line['reference'] .= '-'.($index + 1);
                $line['name'] .= ' · Modèle de recette à description longue, poignée et façade gris anthracite. Informations techniques fictives.';
            }
            unset($line);
            $record = $store->create('invoice', copy: $data);
            $issued = $store->issue($record['id'], 1, []);
            file_put_contents($output.'/invoice-multipage.pdf', $store->pdf($issued));
        }
    }
    echo json_encode(['result' => 'rendered', 'pdfs' => 7, 'data' => 'isolated synthetic fixtures', 'database_changed' => false, 'real_number_allocated' => false]);
} finally {
    File::deleteDirectory($temp);
}
