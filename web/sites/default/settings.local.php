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
  '^cms-local-usagov\.apps\.internal$',
];

$config['user.settings']['register'] = 'admin_only';
$settings['usagov_login_local_form'] = 1;

/**
 * Database configuration.
 */
$databases['default']['default'] = [
#  'database' => getenv('DB_NAME'),
#  'username' => getenv('DB_USER'),
#  'password' => getenv('DB_PW'),
#  'prefix' => '',
#  'host' => getenv('DB_HOST'),
#  'port' => getenv('DB_PORT'),
  'database' => 'drupal',
  'username' => 'root',
  'password' => 'mysql',
#  'prefix' => '',
  'host' => 'database',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];
