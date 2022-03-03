<?php

if ( !empty(getenv('LOCALDB')) ) {
    $databases['default']['default'] = [
        'driver' => 'sqlite',
        'database' => '/var/www/'.getenv('LOCALDB'),
    ];
}

# $settings['tome_static_path_exclude'] = [];

$config['admin_toolbar_tools.settings']['hoverintent_functionality'] = TRUE;

$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

$cf_service_data = json_decode($_ENV['VCAP_SERVICES'] ?? '{}', true);
foreach ($cf_service_data as $service_provider => $service_list) {
  foreach ($service_list as $service) {
        if ($service['name'] === 'database') {
      $databases['default']['default'] = array (
        'database' => $service['credentials']['db_name'],
        'username' => $service['credentials']['username'],
        'password' => $service['credentials']['password'],
        'prefix' => '',
        'host' => $service['credentials']['host'],
        'port' => $service['credentials']['port'],
        'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
        'driver' => 'mysql',
      );
    }
    if ($service['name'] === 'secrets') {
      $settings['hash_salt'] = $service['credentials']['HASH_SALT'];
    }
  }
}

$settings['cache']['bins']['data'] = 'cache.backend.php';
