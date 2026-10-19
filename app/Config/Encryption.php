<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Encryption extends BaseConfig
{
    public string $key = '';
    public string $driver = 'OpenSSL';
    public int $blockSize = 16;
    public string $digest = 'SHA512';

    public function __construct()
    {
        parent::__construct();

        // Prefer an explicitly configured key so previously encrypted data remains readable.
        if ($this->key !== '') {
            return;
        }

        // A missing .env key must not produce a different key on every request.
        // Store the generated key outside public/ and reuse it for subsequent requests.
        $path = WRITEPATH . '.encryption-key';
        if (is_link($path)) {
            throw new \RuntimeException('Encryption key file must not be a symbolic link.');
        }

        $file = @fopen($path, 'c+');
        if ($file === false) {
            throw new \RuntimeException('Set encryption.key in .env or make writable/ writable by PHP.');
        }

        try {
            if (! flock($file, LOCK_EX)) {
                throw new \RuntimeException('Could not lock the encryption key file.');
            }

            // Restrict the private key file to the application server account.
            if (DIRECTORY_SEPARATOR !== '\\' && ! @chmod($path, 0600)) {
                throw new \RuntimeException('Could not secure the encryption key file.');
            }

            rewind($file);
            $stored = stream_get_contents($file);
            if ($stored === false) {
                throw new \RuntimeException('Could not read the encryption key file.');
            }

            $key = trim($stored);
            if ($key === '') {
                $key = 'hex2bin:' . bin2hex(random_bytes(32));
                $data = $key . "\n";
                rewind($file);
                if (fwrite($file, $data) !== strlen($data) || ! fflush($file)) {
                    throw new \RuntimeException('Could not persist the encryption key.');
                }
            }

            // Do not silently replace a damaged key: old encrypted values need the original.
            if (! preg_match('/\Ahex2bin:[a-f0-9]{64}\z/i', $key)) {
                throw new \RuntimeException('Invalid encryption key file; restore the original encryption key.');
            }

            $this->key = $key;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
