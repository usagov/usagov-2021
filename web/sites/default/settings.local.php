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