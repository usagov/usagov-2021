<?php

$databases['default']['default'] = [
    'driver' => 'sqlite',
    'database' => '/var/www/'.getenv('LOCALDB'),
];

$config['admin_toolbar_tools.settings']['hoverintent_functionality'] = TRUE;
$cf_service_data = json_decode($_ENV['VCAP_SERVICES'] ?? '{}', true);
foreach ($cf_service_data as $service_provider => $service_list) {
    foreach ($service_list as $service) {
    // if ($service['name'] === 'storage') {
    //     $config['s3fs.settings']['access_key'] = $service['credentials']['access_key_id'];
    //     $config['s3fs.settings']['bucket'] = $service['credentials']['bucket'];
    //     $config['s3fs.settings']['encryption'] = 'AES256';
    //     $config['s3fs.settings']['public_folder'] = 'public';
    //     $config['s3fs.settings']['private_folder'] = 'private';
    //     $config['s3fs.settings']['region'] = $service['credentials']['region'];
    //     $config['s3fs.settings']['secret_key'] = $service['credentials']['secret_access_key'];
    //     $config['s3fs.settings']['use_https'] = TRUE;
    //     $settings['s3fs.use_s3_for_public'] = TRUE;
    //     // Twig templates _shouldn't_ be in the public dir (lest they be very slow)
    //     $settings['php_storage']['twig']['directory'] = '../storage/php';

    //     // $config['s3fs.settings']['use_https'] = FALSE;
    //     // $config['s3fs.settings']['no_rewrite_cssjs'] = TRUE;

    //     // $config['s3fs.settings']['use_customhost'] = TRUE;
    //     // $config['s3fs.settings']['hostname'] = "s3";

    // }
  }
}