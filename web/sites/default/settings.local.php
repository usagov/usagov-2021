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
$aws = [
  'access_key_id' => $_ENV['AKI'],
  'secret'        => $_ENV['AKIS'],
  'bucket'        => 'usagovbeta',
];
$settings['file_public_path'] = 'sites/default/files';
foreach ($cf_service_data as $service_provider => $service_list) {
  foreach ($service_list as $service) {
    if ($service['name'] === 'storage') {
      //$config['s3fs.settings']['access_key'] = $service['credentials']['access_key_id'];
      //$config['s3fs.settings']['bucket'] = $service['credentials']['bucket'];
      // $config['s3fs.settings']['encryption'] = 'AES256';
      //$config['s3fs.settings']['public_folder'] = 'public';
      //$config['s3fs.settings']['private_folder'] = 'private';
      //$config['s3fs.settings']['region'] = $service['credentials']['region'];
      //$config['s3fs.settings']['secret_key'] = $service['credentials']['secret_access_key'];
      //$config['s3fs.settings']['use_https'] = TRUE;
      //$config['s3fs.settings']['disable_cert_verify'] = TRUE;

      //$settings['s3fs.access_key'] = $aws['access_key_id'];
      //$settings['s3fs.bucket'] = $aws['bucket'];
      //$settings['s3fs.public_folder'] = 'public';
      //$settings['s3fs.private_folder'] = 'private';
      //$settings['s3fs.region'] = $service['credentials']['region'];
      //$settings['s3fs.secret_key'] = $aws['access_key_id'];
      //$settings['s3fs.use_https'] = TRUE;

      //$config['s3fs.settings']['use_customhost'] = TRUE;
      //$config['s3fs.settings']['hostname'] = $service['credentials']['hostname'];
      //$config['s3fs.settings']['no_rewrite_cssjs'] = TRUE;
      //$config['s3fs.use_customhost'] = TRUE;
      //$config['s3fs.hostname'] = $service['credentials']['hostname'];
      //$config['s3fs.no_rewrite_cssjs'] = TRUE;
      //$settings['s3fs.disable_cert_verify'] = TRUE;

      //$settings['s3fs.use_s3_for_public'] = TRUE;

      // Twig templates _shouldn't_ be in the public dir (lest they be very slow)
      //$settings['php_storage']['twig']['directory'] = '../storage/php';

      //s3fs setting
      // Minio
      //$settings['s3fs.access_key']              = $service['credentials']['access_key_id'];
      //$settings['s3fs.secret_key']              = $service['credentials']['secret_access_key'];
      //$config['s3fs.settings']['bucket']        = $service['credentials']['bucket'];
      //$config['s3fs.settings']['region']        = $service['credentials']['region'];
      //$config['s3fs.settings']['public_folder'] = 'local';

      //OR
      //AWS
      $settings['s3fs.access_key']              = $aws['access_key_id'];
      $settings['s3fs.secret_key']              = $aws['secret'];
      $config['s3fs.settings']['bucket']        = $aws['bucket'];
      $config['s3fs.settings']['region']        = 'us-east-1';
      $config['s3fs.settings']['public_folder'] = 'files';
      // -----------------------------------

      $config['s3fs.settings']['use_https'] = TRUE;
      $settings['s3fs.upload_as_private'] = TRUE;
      $settings['s3fs.use_s3_for_public'] = TRUE;
      $settings['s3fs.use_s3_for_private'] = FALSE;
      $settings['php_storage']['twig']['directory'] = '../storage/php';
    }
  }
}
