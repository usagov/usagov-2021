<?php

/**
 * Collect external service information from environment.
 * Cloud Foundry places all service credentials in VCAP_SERVICES
 */

$cf_service_data = json_decode($_ENV['VCAP_SERVICES'] ?? '{}', TRUE);

foreach ($cf_service_data as $service_list) {
  foreach ($service_list as $service) {
    if ($service['name'] === 'database') {
      $databases['default']['default'] = [
        'database' => $service['credentials']['db_name'],
        'username' => $service['credentials']['username'],
        'password' => $service['credentials']['password'],
        'prefix' => '',
        'host' => $service['credentials']['host'],
        'port' => $service['credentials']['port'],
        'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
        'driver' => 'mysql',
      ];
    }
    if ($service['name'] === 'secrets') {
      $settings['hash_salt'] = $service['credentials']['HASH_SALT'];
    }
    if ($service['name'] === 'storage') {
      //s3fs setting
      $settings['s3fs.access_key'] = $service['credentials']['access_key_id'];
      $settings['s3fs.secret_key'] = $service['credentials']['secret_access_key'];
      $config['s3fs.settings']['bucket'] = $service['credentials']['bucket'];
      $config['s3fs.settings']['region'] = $service['credentials']['region'];
      $config['s3fs.settings']['public_folder'] = 'public';
      $config['s3fs.settings']['private_folder'] = 'private';
      // -----------------------------------
      $config['s3fs.settings']['use_https'] = TRUE;
      $settings['s3fs.upload_as_private'] = FALSE;
      $settings['s3fs.use_s3_for_public'] = TRUE;
      $settings['s3fs.use_s3_for_private'] = TRUE;
      $settings['php_storage']['twig']['directory'] = '../storage/php';
    }
  }
}

// CSS and JS aggregation need per dyno/container cache.
// This is from https://www.fomfus.com/articles/how-to-create-a-drupal-8-project-for-heroku-part-1
// included here without fully understanding implications:
$settings['cache']['bins']['data'] = 'cache.backend.php';

$cf_application_data = json_decode($_ENV['VCAP_APPLICATION'] ?? '{}', TRUE);
if (!empty($cf_application_data['space_name']) &&
    in_array($cf_application_data['space_name'],
             ['dev', 'stage', 'prod'])) {
  switch (strtolower($cf_application_data['space_name'])) {
    case "dev":
      $settings['trusted_host_patterns'] = ['^cms-dev.usa.gov$'];
      break;

    case "stage":
      $settings['trusted_host_patterns'] = ['^cms-stage.usa.gov$'];
      break;

    case "prod":
      $settings['trusted_host_patterns'] = ['^cms.usa.gov$'];
      break;
  }
}
