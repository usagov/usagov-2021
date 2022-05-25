<?php

if ( !empty(getenv('LOCALDB')) ) {
    $databases['default']['default'] = [
        'driver' => 'sqlite',
        'database' => '/var/www/'.getenv('LOCALDB'),
    ];
}

# $settings['tome_static_path_exclude'] = [];

$settings['new_relic_rpm.api_key'] = getenv('NEW_RELIC_API_KEY');
$config['new_relic_rpm.settings']['api_key'] = getenv('NEW_RELIC_API_KEY');

$config['admin_toolbar_tools.settings']['hoverintent_functionality'] = TRUE;

$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

$cf_service_data = json_decode($_ENV['VCAP_SERVICES'] ?? '{}', true);

$settings['file_public_path'] = 'sites/default/files';
/*
foreach ($cf_service_data as $service_provider => $service_list) {
  foreach ($service_list as $service) {
    if ($service['name'] === 'storage') {
      $settings['s3fs.access_key'] = $service['credentials']['access_key_id'];
      $settings['s3fs.secret_key'] = $service['credentials']['secret_access_key'];
      $config['s3fs.settings']['bucket'] = $service['credentials']['bucket'];
      $config['s3fs.settings']['region'] = $service['credentials']['region'];
      $config['s3fs.settings']['public_folder'] = 'public';
      $config['s3fs.settings']['private_folder'] = 'private';
      // -----------------------------------

      $config['s3fs.settings']['root_folder'] = '';

      $config['s3fs.settings']['use_cname'] = TRUE;
      $config['s3fs.settings']['domain'] = $_SERVER['HTTP_HOST'] . '/s3/files';
      $config['s3fs.settings']['domain_root'] = 'none';

      $config['s3fs.settings']['use_customhost'] = TRUE;
      $config['s3fs.settings']['hostname'] = $service['credentials']['hostname'];
      $config['s3fs.settings']['use-path-style-endpoint'] = FALSE;
      $config['s3fs.settings']['disable_version_sync'] = TRUE;
      $config['s3fs.settings']['disable_cert_verify'] = TRUE;

      $config['s3fs.settings']['use_https'] = TRUE;
      $settings['s3fs.upload_as_private'] = FALSE;
      $settings['s3fs.use_s3_for_public'] = TRUE;
      $settings['s3fs.use_s3_for_private'] = TRUE;
      $settings['php_storage']['twig']['directory'] = '../storage/php';
    }
  }
}
*/