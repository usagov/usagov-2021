<?php

namespace Drupal\usa_workflow\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\usa_workflow\UsaWorkflowPolicy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for USA workflow restrictions.
 */
class UsaWorkflowSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private UsaWorkflowPolicy $policy,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('usa_workflow.policy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'usa_workflow_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   */
  protected function getEditableConfigNames(): array {
    return ['usa_workflow.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('usa_workflow.settings');
    $bundle_options = $this->policy->getConfigurableBundleOptions();

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable USA workflow restrictions'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['restrict_self_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Restrict publishing a user\'s own latest revision'),
      '#description' => $this->t('Users without the Approve own content permission must have another editor publish their latest revision.'),
      '#default_value' => $config->get('restrict_self_publish'),
    ];

    $form['restrict_self_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Restrict deleting a user\'s own latest revision'),
      '#default_value' => $config->get('restrict_self_delete'),
    ];

    $form['excluded_publish_bundles'] = [
      '#type' => 'details',
      '#title' => $this->t('Self-publish exclusions'),
      '#description' => $this->t('Checked bundles are excluded from the self-publish restriction.'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['excluded_delete_bundles'] = [
      '#type' => 'details',
      '#title' => $this->t('Self-delete exclusions'),
      '#description' => $this->t('Checked bundles are excluded from the self-delete restriction.'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $saved_publish = $config->get('excluded_publish_bundles') ?? [];
    $saved_delete = $config->get('excluded_delete_bundles') ?? [];

    if (!$bundle_options) {
      $form['no_bundles'] = [
        '#type' => 'item',
        '#markup' => $this->t('No moderated bundles are currently available for configuration.'),
      ];
    }

    foreach ($bundle_options as $entity_type_id => $group) {
      $publish_defaults = array_values(array_intersect(array_keys($group['options']), $saved_publish));
      $delete_defaults = array_values(array_intersect(array_keys($group['options']), $saved_delete));

      $form['excluded_publish_bundles'][$entity_type_id] = [
        '#type' => 'checkboxes',
        '#title' => $group['title'],
        '#options' => $group['options'],
        '#default_value' => $publish_defaults,
      ];

      $form['excluded_delete_bundles'][$entity_type_id] = [
        '#type' => 'checkboxes',
        '#title' => $group['title'],
        '#options' => $group['options'],
        '#default_value' => $delete_defaults,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @param-out array<mixed> $form
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory()->getEditable('usa_workflow.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('restrict_self_publish', (bool) $form_state->getValue('restrict_self_publish'))
      ->set('restrict_self_delete', (bool) $form_state->getValue('restrict_self_delete'))
      ->set(
        'excluded_publish_bundles',
        $this->flattenSelectedValues((array) $form_state->getValue('excluded_publish_bundles', [])),
      )
      ->set(
        'excluded_delete_bundles',
        $this->flattenSelectedValues((array) $form_state->getValue('excluded_delete_bundles', [])),
      )
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Flattens checkbox values into a list of selected bundle keys.
   *
   * @param array<string, mixed> $groups
   *   Checkbox values grouped by entity type.
   *
   * @return string[]
   *   The selected bundle keys.
   */
  protected function flattenSelectedValues(array $groups): array {
    $values = [];

    foreach ($groups as $options) {
      if (!is_array($options)) {
        continue;
      }

      foreach ($options as $value) {
        if (is_string($value) && $value !== '0' && $value !== '') {
          $values[] = $value;
        }
      }
    }

    sort($values);
    return array_values(array_unique($values));
  }

}
