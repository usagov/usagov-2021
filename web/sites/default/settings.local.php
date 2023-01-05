<?php

# $settings['tome_static_path_exclude'] = [];
# $config['admin_toolbar_tools.settings']['hoverintent_functionality'] = TRUE;

$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

$config['s3fs.settings']['disable_version_sync'] = TRUE;
$config['s3fs.settings']['disable_cert_verify'] = TRUE;
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';

if (class_exists('Kint')) {
  // Change the maximum depth to prevent out-of-memory errors.
  \Kint::$depth_limit= 4;
}

$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  '^cms-usagov\.docker\.local$',
  '^cms-local-usagov\.apps\.internal$',
];

$config['user.settings']['register'] = 'admin_only';
$settings['usagov_login_local_form'] = 1;
