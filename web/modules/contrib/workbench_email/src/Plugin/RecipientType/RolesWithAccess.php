<?php

namespace Drupal\workbench_email\Plugin\RecipientType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\RoleInterface;
use Drupal\workbench_email\TemplateInterface;


/**
 * Provides a recipient type for Workbench Access Sections.
 *
 * @RecipientType(
 *   id = "roles_with_access",
 *   title = @Translation("Roles with access"),
 *   description = @Translation("Send to recipients with given role and permission to update."),
 *   settings = {
 *     "roles" = {},
 *   },
 * )
 */

class RolesWithAccess extends Role {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $build = parent::buildConfigurationForm($form, $form_state);
    $build['roles']['#description'] = $this->t('Send to all users with selected roles who have permission to update the transitioned item.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRecipients(ContentEntityInterface $entity, TemplateInterface $template) {
    $recipients = [];
    foreach ($this->getRoles() as $role) {
      foreach ($this->entityTypeManager->getStorage('user')->loadByProperties([
        'roles' => $role,
        'status' => 1,
      ]) as $account) {
        if ($entity->access('update', $account)) {
          $recipients[] = $account->getEmail();
        }
      }
    }
    return $recipients;
  }
}
