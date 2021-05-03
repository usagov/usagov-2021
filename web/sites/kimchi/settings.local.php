<?php

$databases['default']['default'] = [
    'driver' => 'sqlite',
    'database' => '/var/www/sqlite_kimchi.db',
    'prefix' => '',
];
$settings['config_sync_directory'] = '/var/www/config/kimchi';

$config['admin_toolbar_tools.settings']['hoverintent_functionality'] = TRUE;