<?php

namespace App\Commands;

use App\Services\BarcodeService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class BarcodeAudit extends BaseCommand
{
    protected $group = 'Inventory';
    protected $name = 'barcode:audit';
    protected $description = 'Report active or archived products with invalid barcode checksums.';

    public function run(array $params): int
    {
        $errors = 0;
        $rows = db_connect()->table('products')->select('id,slug,barcode')
            ->where('barcode IS NOT NULL', null, false)->get()->getResultArray();
        foreach ($rows as $row) {
            if (! BarcodeService::valid((string) $row['barcode'])) {
                CLI::error(sprintf('Product #%d (%s): invalid barcode %s', $row['id'], $row['slug'], $row['barcode']));
                $errors++;
            }
        }
        if ($errors > 0) {
            CLI::error($errors . ' invalid barcode(s) found.');
            return EXIT_ERROR;
        }
        CLI::write('Barcode audit passed: zero invalid codes.', 'green');
        return EXIT_SUCCESS;
    }
}
