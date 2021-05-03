<?php

namespace Drupal\workbench_email\Entity;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\workbench_email\Plugin\RecipientTypeInterface;
use Drupal\workbench_email\RecipientTypePluginCollection;
use Drupal\workbench_email\TemplateInterface;

/**
 * Defines the Email Template entity.
 *
 * @ConfigEntityType(
 *   id = "workbench_email_template",
 *   label = @Translation("Email Template"),
 *   handlers = {
 *     "list_builder" = "Drupal\workbench_email\TemplateListBuilder",
 *     "form" = {
 *       "add" = "Drupal\workbench_email\Form\TemplateForm",
 *       "edit" = "Drupal\workbench_email\Form\TemplateForm",
 *       "delete" = "Drupal\workbench_email\Form\TemplateDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\workbench_email\TemplateHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "workbench_email_template",
 *   admin_permission = "administer workbench_email templates",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/workbench-moderation/workbench-email-template/{workbench_email_template}",
 *     "add-form" = "/admin/structure/workbench-moderation/workbench-email-template/add",
 *     "edit-form" = "/admin/structure/workbench-moderation/workbench-email-template/{workbench_email_template}/edit",
 *     "delete-form" = "/admin/structure/workbench-moderation/workbench-email-template/{workbench_email_template}/delete",
 *     "collection" = "/admin/structure/workbench-moderation/workbench-email-template"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "subject",
 *     "body",
 *     "bundles",
 *     "recipient_types",
 *     "replyTo",
 *   }
 * )
 */
class Template extends ConfigEntityBase implements TemplateInterface {
  /**
   * The Email Template ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Email Template label.
   *
   * @var string
   */
  protected $label;

  /**
   * Body with value and format keys.
   *
   * @var string[]
   */
  protected $body = [];

  /**
   * Message subject.
   *
   * @var string
   */
  protected $subject;

  /**
   * Message reply-to.
   *
   * @var string
   */
  protected $replyTo;

  /**
   * Configured recipient types for this template.
   *
   * An associative array of recipient types assigned to the email template,
   * keyed by the instance ID of each recipient type and using the properties:
   * - id: The plugin ID of the recipient type plugin instance.
   * - provider: The name of the provider that owns the recipient type.
   * - status: (optional) A Boolean indicating whether the recipient type is
   *   enabled for the email template. Defaults to FALSE.
   * - settings: (optional) An array of configured settings for the recipient
   *   type.
   *
   * Use Template::recipientTypes() to access the actual recipient types.
   *
   * @var array
   */
  protected $recipient_types = [];

  /**
   * Holds the collection of recipient types that are attached to this template.
   *
   * @var \Drupal\workbench_email\RecipientTypePluginCollection
   */
  protected $recipientTypeCollection;

  /**
   * Entity bundles.
   *
   * @var string[]
   */
  protected $bundles = [];

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->subject;
  }

  /**
   * {@inheritdoc}
   */
  public function getBody() {
    return $this->body;
  }

  /**
   * {@inheritdoc}
   */
  public function getReplyTo() {
    return $this->replyTo;
  }

  /**
   * {@inheritdoc}
   */
  public function setBody(array $body) {
    $this->body = $body;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject($subject) {
    $this->subject = $subject;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setReplyTo($replyTo) {
    $this->replyTo = $replyTo;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function recipientTypes($instance_id = NULL) {
    if (!isset($this->recipientTypeCollection)) {
      $this->recipientTypeCollection = new RecipientTypePluginCollection(\Drupal::service('plugin.manager.recipient_type'), $this->recipient_types);
      $this->recipientTypeCollection->sort();
    }
    if (isset($instance_id)) {
      return $this->recipientTypeCollection->get($instance_id);
    }
    return $this->recipientTypeCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['recipient_types' => $this->recipientTypes()];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    foreach ($this->bundles as $bundle) {
      list($entity_type_id, $bundle_id) = explode(':', $bundle, 2);
      $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
      $bundle_config_dependency = $entity_type->getBundleConfigDependency($bundle_id);
      $this->addDependency($bundle_config_dependency['type'], $bundle_config_dependency['name']);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function calculatePluginDependencies(PluginInspectionInterface $instance) {
    // Only add dependencies for plugins that are actually configured.
    if (isset($this->recipient_types[$instance->getPluginId()])) {
      parent::calculatePluginDependencies($instance);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles() {
    return $this->bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function setBundles(array $bundles) {
    $this->bundles = $bundles;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipients(ContentEntityInterface $entity) {
    $recipients = [];
    foreach ($this->recipient_types as $plugin_id => $config) {
      $recipientType = $this->recipientTypes($plugin_id);
      if (!$recipientType->isEnabled()) {
        continue;
      }
      $recipients = array_merge($recipients, $recipientType->prepareRecipients($entity, $this));
    }
    return array_filter(array_unique($recipients));
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    // Give the parent method and each recipient type plugin a chance to react
    // to removed dependencies and report if any of them made a change.
    return array_reduce(iterator_to_array($this->recipientTypes()), function ($carry, RecipientTypeInterface $type) use ($dependencies) {
      return $type->onDependencyRemoval($dependencies) || $carry;
    }, parent::onDependencyRemoval($dependencies));
  }

}
