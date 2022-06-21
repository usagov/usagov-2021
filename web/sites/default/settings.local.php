<?php

# $settings['tome_static_path_exclude'] = [];
# $config['admin_toolbar_tools.settings']['hoverintent_functionality'] = TRUE;

$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

$config['s3fs.settings']['disable_version_sync'] = TRUE;
$config['s3fs.settings']['disable_cert_verify'] = TRUE;

$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  '^cms-usagov\.docker\.local$',
];

$cf_service_data = json_decode($_ENV['VCAP_SERVICES'] ?? '{}', TRUE);

$config['system.performance']['css']['preprocess'] = FALSE; 
$config['system.performance']['js']['preprocess'] = FALSE;

// foreach ($cf_service_data as $service_list) {
//   foreach ($service_list as $service) {
//     if ($service['name'] === 'storage') {

//       $settings['s3fs.access_key'] = $service['credentials']['access_key_id'];
//       $settings['s3fs.secret_key'] = $service['credentials']['secret_access_key'];
//       $config['s3fs.settings']['bucket'] = $service['credentials']['bucket'];
//       $config['s3fs.settings']['region'] = $service['credentials']['region'];

//       $config['s3fs.settings']['root_folder'] = 'cms';
//       $config['s3fs.settings']['public_folder'] = 'public';
//       $config['s3fs.settings']['private_folder'] = 'private';

//       $S3_PROXY_PATH_CMS = getenv('S3_PROXY_PATH_CMS') ?: '/s3/files';

//       // var_dump($_SERVER['HTTP_HOST'] .':' . $service['credentials']['endport'] . '/' . $config['s3fs.settings']['bucket'] . '/' . $config['s3fs.settings']['root_folder'] . '/' . $S3_PROXY_PATH_CMS);
//       // var_dump($config['s3fs.settings']);

//       $config['s3fs.settings']['use_cname'] = FALSE;
//       $config['s3fs.settings']['domain'] = $_SERVER['HTTP_HOST'] .':'. $service['credentials']['endport']. $S3_PROXY_PATH_CMS;
//       $config['s3fs.settings']['domain_root'] = 'public';

//       $config['s3fs.settings']['use_customhost'] = TRUE;
//       $config['s3fs.settings']['hostname'] = $service['credentials']['fips_endpoint'];
//       $config['s3fs.settings']['use-path-style-endpoint'] = FALSE;

//       $config['s3fs.settings']['use_https'] = TRUE;
//       $config['s3fs.settings']['no_rewrite_cssjs'] = TRUE;
//       $settings['s3fs.upload_as_private'] = FALSE;
//       $settings['s3fs.use_s3_for_public'] = TRUE;
//       $settings['s3fs.use_s3_for_private'] = TRUE;
//     }
//   }
// }
