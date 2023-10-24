<?php

$entity_type_manager = \Drupal::entityTypeManager();
$permissions = array_keys(\Drupal::service('user.permissions')->getPermissions());
/** @var \Drupal\user\RoleInterface[] $roles */
$roles = $entity_type_manager->getStorage('user_role')->loadMultiple();
foreach ($roles as $role) {
  $role_permissions = $role->getPermissions();
  $differences = array_diff($role_permissions, $permissions);
  if ($differences) {
    foreach ($differences as $permission) {
      $role->revokePermission($permission);
    }
    $role->save();
  }
}

$config_factory = \Drupal::service('config.factory');
$block_configurations = $config_factory->listAll('block.block');

foreach ($block_configurations as $config_name) {
  // Load the block configuration.
  $config = $config_factory->getEditable($config_name);

  // Check for a value and update it if needed.
  if (is_array($config->get('visibility.node_type'))) {
    if ($config->get('visibility.node_type')['id'] === 'node_type') {
      $config->set('visibility.node_type.id', 'entity_bundle:node');
      $config->save();
    }
  }
}

foreach (\Drupal::configFactory()->listAll('pathauto.pattern.') as $pattern_config_name) {
  $pattern_config = \Drupal::configFactory()->getEditable($pattern_config_name);

  // Loop patterns and swap the node_type plugin by the entity_bundle:node
  // plugin.
  if ($pattern_config->get('type') === 'canonical_entities:node') {
    $selection_criteria = $pattern_config->get('selection_criteria');
    foreach ($selection_criteria as $uuid => $condition) {
      if ($condition['id'] === 'node_type') {
        $pattern_config->set("selection_criteria.$uuid.id", 'entity_bundle:node');
        $pattern_config->save();
        break;
      }
    }
  }
}

\Drupal::database()->schema()->dropTable('embedded_paragraphs_revision');
