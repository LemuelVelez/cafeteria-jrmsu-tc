<?php

use CodeIgniter\Autoloader\FileLocator;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use Config\Autoload;
use Config\Modules;
use Config\Paths;
use Config\Services as AppServices;

class_alias(Autoload::class, 'Config\\Autoload');
class_alias(Modules::class, 'Config\\Modules');
class_alias(Paths::class, 'Config\\Paths');
class_alias(AppServices::class, 'Config\\Services');

class_exists(FileLocator::class);
class_exists(Factories::class);
class_exists(Services::class);
