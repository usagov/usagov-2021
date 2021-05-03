<?php

namespace Drupal\workbench_email;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Provides an interface for defining Email Template entities.
 */
interface TemplateInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Gets the template subject.
   *
   * @return string
   *   Template subject.
   */
  public function getSubject();

  /**
   * Gets the template body - array with keys value and format.
   *
   * @return string[]
   *   Template body.
   */
  public function getBody();

  /**
   * Gets the template reply-to.
   *
   * @return string
   *   Template reply-to.
   */
  public function getReplyTo();

  /**
   * Sets the body.
   *
   * @param string[] $body
   *   Body with keys value and format.
   *
   * @return self
   *   Called instance
   */
  public function setBody(array $body);

  /**
   * Sets the subject.
   *
   * @param string $subject
   *   Template subject.
   *
   * @return self
   *   Called instance.
   */
  public function setSubject($subject);

  /**
   * Sets the reply-to.
   *
   * @param string $replyTo
   *   Template reply-to.
   *
   * @return self
   *   Called instance.
   */
  public function setReplyTo($replyTo);

  /**
   * Returns the ordered collection of recipient type plugin instances or an individual plugin instance.
   *
   * @param string $instance_id
   *   (optional) The ID of a recipient type plugin instance to return.
   *
   * @return \Drupal\workbench_email\RecipientTypePluginCollection|\Drupal\workbench_email\Plugin\RecipientTypeInterface
   *   Either the recipient type collection or a specific recipient type plugin
   *   instance.
   */
  public function recipientTypes($instance_id = NULL);

  /**
   * Gets value of bundles.
   *
   * @return string[]
   *   Value of bundles
   */
  public function getBundles();

  /**
   * Sets bundles this template applies to.
   *
   * @param string[] $bundles
   *   Bundles this template applies to in {entity_type_id}:{bundle} format.
   *
   * @return self
   *   Called instance.
   */
  public function setBundles(array $bundles);

  /**
   * Calculates recipients.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being sent.
   *
   * @return array
   *   Array of email addresses.
   */
  public function getRecipients(ContentEntityInterface $entity);

}
