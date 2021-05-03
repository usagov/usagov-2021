<?php

namespace Drupal\workbench_email\Plugin\RecipientType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\workbench_email\Plugin\RecipientTypeBase;
use Drupal\workbench_email\TemplateInterface;

/**
 * Provides a recipient type of the content author.
 *
 * @RecipientType(
 *   id = "author",
 *   title = @Translation("Author"),
 *   description = @Translation("Send to entity author/owner."),
 * )
 */
class Author extends RecipientTypeBase {

  /**
   * {@inheritdoc}
   */
  public function prepareRecipients(ContentEntityInterface $entity, TemplateInterface $template) {
    $recipients = [];
    if ($this->isEnabled() && $entity instanceof EntityOwnerInterface) {
      if (!$entity->getOwner()->isAnonymous()) {
        $recipients[] = $entity->getOwner()->getEmail();
      }
    }
    return $recipients;
  }

}
