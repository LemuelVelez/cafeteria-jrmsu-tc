<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    public string $baseURL = 'http://localhost:8080/';
    public array $allowedHostnames = [];
    public string $indexPage = '';
    public string $uriProtocol = 'REQUEST_URI';
    public string $permittedURIChars = 'a-z 0-9~%.:_\-';
    public string $defaultLocale = 'en';
    public bool $negotiateLocale = false;
    public array $supportedLocales = ['en'];
    public string $appTimezone = 'Asia/Manila';
    public string $charset = 'UTF-8';
    public bool $forceGlobalSecureRequests = false;
    public array $proxyIPs = [];
    public bool $CSPEnabled = false;

    public function __construct()
    {
        parent::__construct();

        $runtimeBaseUrl = trim((string) env('APP_BASE_URL', ''));
        if ($runtimeBaseUrl !== '') {
            $this->baseURL = rtrim($runtimeBaseUrl, '/') . '/';

            return;
        }

        $requestBaseUrl = $this->detectRequestBaseUrl();
        if ($requestBaseUrl === null) {
            return;
        }

        $configuredHost = strtolower((string) parse_url($this->baseURL, PHP_URL_HOST));
        $requestHost = strtolower((string) parse_url($requestBaseUrl, PHP_URL_HOST));

        if (
            $configuredHost === ''
            || $this->isLocalHost($configuredHost)
            || strcasecmp($configuredHost, $requestHost) === 0
        ) {
            $this->baseURL = $requestBaseUrl;
        }
    }

    private function detectRequestBaseUrl(): ?string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

        if ($host === '') {
            $forwardedHost = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0] ?? '';
            $host = trim($forwardedHost);
        }

        if ($host === '') {
            $host = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));
        }

        if (! $this->isValidHost($host)) {
            return null;
        }

        $scheme = $this->detectRequestScheme();
        $basePath = $this->detectBasePath();

        return $scheme . '://' . $host . $basePath . '/';
    }

    private function detectRequestScheme(): string
    {
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        if (in_array($forwardedProto, ['http', 'https'], true)) {
            return $forwardedProto;
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }

        $requestScheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if (in_array($requestScheme, ['http', 'https'], true)) {
            return $requestScheme;
        }

        return ((string) ($_SERVER['SERVER_PORT'] ?? '')) === '443' ? 'https' : 'http';
    }

    private function detectBasePath(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $directory = trim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($directory === '' || $directory === '.') {
            return '';
        }

        if ($directory === 'public') {
            return '';
        }

        if (str_ends_with($directory, '/public')) {
            $directory = substr($directory, 0, -strlen('/public'));
        }

        return $directory === '' ? '' : '/' . trim($directory, '/');
    }

    private function isValidHost(string $host): bool
    {
        return preg_match('/^(?:\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::[0-9]{1,5})?$/iD', $host) === 1;
    }

    private function isLocalHost(string $host): bool
    {
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1';
    }
}
