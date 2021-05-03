<?php

namespace Drupal\workbench_email\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workbench_email\TemplateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;

/**
 * Defines the interface for Recipient type plugins.
 *
 * TODO Docs.
 *
 * @see \Drupal\workbench_email\Annotation\RecipientType
 * @see \Drupal\workbench_email\RecipientTypePluginManager
 * @see \Drupal\workbench_email\Plugin\RecipientTypeBase
 * @see plugin_api
 */
interface RecipientTypeInterface extends ConfigurableInterface, PluginInspectionInterface, PluginWithFormsInterface, PluginFormInterface, DependentPluginInterface {

  /**
   * Returns the administrative label for this recipient type plugin.
   *
   * @return string
   *   The label.
   */
  public function getLabel();

  /**
   * Returns the administrative description for this recipient type plugin.
   *
   * @return string
   *   The description.
   */
  public function getDescription();

  /**
   * Generates a recipient types's settings form.
   *
   * @param array $form
   *   A minimally prepopulated form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the (entire) configuration form.
   *
   * @return array
   *   The $form array with additional form elements for the settings of this
   *   recipient type. The submitted form values should match $this->settings.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * Returns email address(s) matching this recipient type's configuration.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being transitioned.
   * @param \Drupal\workbench_email\TemplateInterface $template
   *   Template being used.
   */
  public function prepareRecipients(ContentEntityInterface $entity, TemplateInterface $template);

  /**
   * Checks status.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function isEnabled();

  /**
   * Informs the plugin that a dependency of the recipient type will be deleted.
   *
   * @param array $dependencies
   *   An array of dependencies that will be deleted keyed by dependency type.
   *
   * @return bool
   *   TRUE if the template settings have been changed.
   *
   * @see \Drupal\Core\Config\ConfigEntityInterface::onDependencyRemoval()
   *
   * @todo https://www.drupal.org/node/2579743 make part of a generic interface.
   */
  public function onDependencyRemoval(array $dependencies);

}
