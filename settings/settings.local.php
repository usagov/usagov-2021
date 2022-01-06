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
    if ($service['name'] === 'storage') {
      $config['s3fs.settings']['access_key'] = $service['credentials']['access_key_id'];
      $config['s3fs.settings']['bucket'] = $service['credentials']['bucket'];
      // $config['s3fs.settings']['encryption'] = 'AES256';
      $config['s3fs.settings']['public_folder'] = 'public';
      $config['s3fs.settings']['private_folder'] = 'private';
      $config['s3fs.settings']['region'] = $service['credentials']['region'];
      $config['s3fs.settings']['secret_key'] = $service['credentials']['secret_access_key'];
      $config['s3fs.settings']['use_https'] = FALSE;

      $settings['s3fs.access_key'] = $service['credentials']['access_key_id'];
      $settings['s3fs.bucket'] = $service['credentials']['bucket'];
      $settings['s3fs.public_folder'] = 'public';
      $settings['s3fs.private_folder'] = 'private';
      $settings['s3fs.region'] = $service['credentials']['region'];
      $settings['s3fs.secret_key'] = $service['credentials']['secret_access_key'];
      $settings['s3fs.use_https'] = FALSE;

      $config['s3fs.settings']['use_customhost'] = TRUE;
      $config['s3fs.settings']['hostname'] = $service['credentials']['hostname'];
      $config['s3fs.settings']['no_rewrite_cssjs'] = TRUE;
      $config['s3fs.use_customhost'] = TRUE;
      $config['s3fs.hostname'] = $service['credentials']['hostname'];
      $config['s3fs.no_rewrite_cssjs'] = TRUE;

      $settings['s3fs.use_s3_for_public'] = TRUE;
      // Twig templates _shouldn't_ be in the public dir (lest they be very slow)
      $settings['php_storage']['twig']['directory'] = '../storage/php';
    }
  }
}
