<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;
use Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for scheduled transitions.
 */
class ScheduledTransitionsSettingsForm extends ConfigFormBase {

  /**
   * Cache tag for scheduled transition settings.
   *
   * Features depending on settings from this form should add this tag for
   * invalidation.
   */
  public const SETTINGS_TAG = 'scheduled_transition_settings';

  /**
   * Cache tag invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagInvalidator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Utilities for Scheduled Transitions module.
   *
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface
   */
  protected $scheduledTransitionsUtility;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagInvalidator
   *   Cache tag invalidator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle information service.
   * @param \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility
   *   Utilities for Scheduled Transitions module.
   */
  public function __construct(ConfigFactoryInterface $configFactory, CacheTagsInvalidatorInterface $cacheTagInvalidator, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo, ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility) {
    parent::__construct($configFactory);
    $this->cacheTagInvalidator = $cacheTagInvalidator;
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->scheduledTransitionsUtility = $scheduledTransitionsUtility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache_tags.invalidator'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('scheduled_transitions.utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['scheduled_transitions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scheduled_transitions_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['bundles'] = [
      '#type' => 'details',
    ];
    $form['bundles']['help'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Enable entity type/bundles for use with scheduled transitions.'),
      '#suffix' => '</p>',
    ];
    $form['bundles']['enabled'] = [
      '#type' => 'tableselect',
      '#header' => [
        $this->t('Entity type'),
        $this->t('Bundle'),
        $this->t('Permissions'),
      ],
    ];

    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    $enabledBundles = [];
    foreach ($this->scheduledTransitionsUtility->getBundles() as $entityTypeId => $bundles) {
      foreach ($bundles as $bundle) {
        $enabledBundles[] = sprintf('%s:%s', $entityTypeId, $bundle);
      }
    }

    // Flattens possible entity type/bundles.
    $possibleBundles = [];
    foreach ($this->scheduledTransitionsUtility->getApplicableBundles() as $entityTypeId => $bundles) {
      foreach ($bundles as $bundle) {
        $possibleBundles[] = sprintf('%s:%s', $entityTypeId, $bundle);
      }
    }

    $form['bundles']['enabled']['#options'] = array_map(function (string $bundle) use ($roles, $enabledBundles): array {
      $checked = in_array($bundle, $enabledBundles, TRUE);
      [$entityTypeId, $bundle] = explode(':', $bundle);
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $bundleInfo = $this->bundleInfo->getBundleInfo($entityTypeId);

      // Show a handy permissions message, but only if this bundle is enabled.
      if ($checked) {
        $permissionMessage = $this->t('<strong>Notice</strong>: no roles are currently granted permissions for this type.');
        $needsPermissions = [
          Permissions::viewScheduledTransitionsPermission($entityTypeId, $bundle),
          Permissions::addScheduledTransitionsPermission($entityTypeId, $bundle),
        ];
        // Find at least one role with at least one of the permissions.
        foreach ($roles as $role) {
          if (count(array_intersect($needsPermissions, $role->getPermissions()))) {
            $permissionMessage = '';
            break;
          }
        }
      }
      else {
        $permissionMessage = '';
      }

      return [
        ['data' => ['#markup' => $entityType ? $entityType->getLabel() : '']],
        ['data' => ['#markup' => $bundleInfo[$bundle]['label'] ?? '']],
        ['data' => ['#markup' => $permissionMessage]],
      ];
    }, array_combine($possibleBundles, $possibleBundles));

    // Collapse the details element if anything is enabled.
    $form['bundles']['#title'] = $this->t('Enabled types (@count)', [
      '@count' => count($enabledBundles),
    ]);
    $form['bundles']['#open'] = count($enabledBundles) === 0;
    $form['bundles']['enabled']['#default_value'] = array_fill_keys($enabledBundles, TRUE);

    $settings = $this->config('scheduled_transitions.settings');

    $form['cron'] = [
      '#type' => 'details',
      '#title' => $this->t('Automation'),
      '#open' => TRUE,
    ];
    $form['cron']['cron_create_queue_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create scheduling jobs in cron'),
      '#description' => $this->t('If this setting is not enabled, items must be created using <code>drush scheduled-transitions:queue-jobs</code> command.'),
      '#default_value' => (bool) $settings->get('automation.cron_create_queue_items'),
    ];

    $form['entity_operations'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity operations'),
      '#open' => TRUE,
    ];
    $form['entity_operations']['mirror_operation_view'] = [
      '#type' => 'select',
      '#title' => 'Mirror view scheduled transitions',
      '#description' => $this->t('When attempting to <em>view scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $settings->get('mirror_operations.view scheduled transition'),
    ];
    $form['entity_operations']['mirror_operation_add'] = [
      '#type' => 'select',
      '#title' => 'Mirror add scheduled transitions',
      '#description' => $this->t('When attempting to <em>add scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $settings->get('mirror_operations.add scheduled transition'),
    ];
    $form['entity_operations']['mirror_operation_reschedule'] = [
      '#type' => 'select',
      '#title' => 'Mirror reschedule scheduled transitions',
      '#description' => $this->t('When attempting to <em>reschedule scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disable -'),
      '#default_value' => $settings->get('mirror_operations.reschedule scheduled transitions'),
    ];

    $form['messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Messages'),
      '#open' => TRUE,
    ];
    $form['message_transition_latest'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Latest'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_latest'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-revision-id] [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_historical'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Historical'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_historical'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-revision-id] [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_copy_latest_draft'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Copy latest draft'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_copy_latest_draft'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:latest-state] [scheduled-transitions:latest-revision-id]'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $settings = $this->config('scheduled_transitions.settings')
      ->set('mirror_operations.view scheduled transition', $form_state->getValue('mirror_operation_view'))
      ->set('mirror_operations.add scheduled transition', $form_state->getValue('mirror_operation_add'))
      ->set('mirror_operations.reschedule scheduled transitions', $form_state->getValue('mirror_operation_reschedule'))
      ->set('automation.cron_create_queue_items', (bool) $form_state->getValue('cron_create_queue_items'))
      ->set('message_transition_latest', $form_state->getValue('message_transition_latest'))
      ->set('message_transition_historical', $form_state->getValue('message_transition_historical'))
      ->set('message_transition_copy_latest_draft', $form_state->getValue('message_transition_copy_latest_draft'));

    $settings->clear('bundles');
    foreach (array_keys(array_filter($form_state->getValue('enabled'))) as $index => $enabledBundle) {
      [$entityTypeId, $bundle] = explode(':', $enabledBundle);
      $settings->set('bundles.' . $index . '.entity_type', $entityTypeId);
      $settings->set('bundles.' . $index . '.bundle', $bundle);
    }

    $settings->save();
    $this->cacheTagInvalidator->invalidateTags([static::SETTINGS_TAG]);
    parent::submitForm($form, $form_state);
  }

}
