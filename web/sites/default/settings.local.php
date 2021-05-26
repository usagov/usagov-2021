<?php

$databases['default']['default'] = [
    'driver' => 'sqlite',
    'database' => '/var/www/'.getenv('LOCALDB'),
];

$config['admin_toolbar_tools.settings']['hoverintent_functionality'] = TRUE;