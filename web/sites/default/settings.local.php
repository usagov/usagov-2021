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

### Uncomment and clear cache to allow local logins
#$config['user.settings']['register'] = 'visitors_admin_approval';
#$settings['usagov_login_local_form'] = 1;

### Admin Only is the new system default
#$config['user.settings']['register'] = 'admin_only';
#$settings['usagov_login_local_form'] = 0;
