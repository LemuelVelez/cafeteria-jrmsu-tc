<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class View extends BaseConfig
{
    public string $coreRenderer = 'CodeIgniter\\View\\View';
    public array $coreRendererOptions = [];
    public bool $saveData = true;
    public array $filters = [];
    public array $plugins = [];
    public array $decorators = [];
    public string $appOverridesFolder = 'overrides';
}
