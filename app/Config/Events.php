<?php

namespace Config;

use CodeIgniter\Events\Events;

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            ob_start();
        }
    }
});
