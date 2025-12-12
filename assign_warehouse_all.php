<?php
require 'FlexibeeAPI.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

$config = AppConfig::get();
$warehouse = $config['mapping']['default_warehouse'] ?? '';
if (!$warehouse) {
    echo "Brak DEFAULT_WAREHOUSE w .env\n";
    exit(1);
}

$api = new FlexibeeAPI();

$start = 0;
$limit = 100;
$maxBatches = null; // no safety cap; process all batches
$batchCount = 0;
$updated = 0;
$skipped = 0;
$fetched = 0;
$year = intval(date('Y'));
echo "Start assign warehouse to all products (warehouse={$warehouse})\n";

while (true) {
    $products = $api->getProducts($start, $limit);
    $count = count($products);
    $fetched += $count;
    if (empty($products)) {
        break;
    }

    echo "Batch start=$start fetched=$count (updated so far: $updated)\n";

    foreach ($products as $p) {
        $kod = $p['kod'] ?? null;
        if (!$kod) {
            continue;
        }

        $isStock = (!empty($p['skladovy']) && $p['skladovy'] === 'true') || (!empty($p['skladove']) && $p['skladove'] === 'true');
        $hasWarehouse = !empty($p['sklad@ref']) || !empty($p['sklad']);

        $fields = [];
        if (!$isStock) {
            $fields['skladovy'] = true;
            $fields['skladove'] = true;
        }
        if (!$hasWarehouse) {
            $fields['sklad'] = $warehouse;
        }

        $ok = true;

        if (!empty($fields)) {
            $ok = $api->updateProduct('code:' . $kod, $fields);
            if ($ok) {
                $updated++;
                if (($updated % 20) === 0) {
                    echo "...updated $updated (last kod=$kod)\n";
                }
            } else {
                echo "Błąd aktualizacji produktu: $kod\n";
            }
        } else {
            $skipped++;
        }

        // Zadbaj o kartę magazynową w bieżącym roku nawet jeśli nie było aktualizacji
        $api->ensureStockCard($kod, $warehouse, $year);
    }

    $start += $limit;
    $batchCount++;
    if ($maxBatches !== null && $batchCount >= $maxBatches) {
        echo "Przerwano po $batchCount batchach (limit debug).\n";
        break;
    }
    flush();
}

echo "Pobrano: $fetched, zaktualizowano: $updated, pominieto (już ok): $skipped\n";
