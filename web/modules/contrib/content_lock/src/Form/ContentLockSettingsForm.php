<?php

namespace Drupal\content_lock\Form;

use Drupal\content_lock\ContentLock\ContentLock;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentLockSettingsForm.
 *
 * @package Drupal\content_lock\Form
 */
class ContentLockSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'content_lock.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_lock_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('content_lock.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('Verbose'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[get_class($this), 'formProcessMergeParent']],
      '#weight' => 0,
    ];
    $form['general']['verbose'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this option to display a message to the user when they lock a content item by editing it.'),
      '#description' => $this->t('Users trying to edit a content locked still see the content lock message.'),
      '#default_value' => $config->get('verbose'),
      '#return_value' => 1,
      '#empty' => 0,
    ];

    $form['entities'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity type protected'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[get_class($this), 'formProcessMergeParent']],
      '#weight' => 1,
    ];

    $definitions = $this->entityTypeManager->getDefinitions();
    $entity_types = [];
    $selected_entity_types = [];
    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $entity_types[$definition->id()] = $definition->getLabel();
        if (!empty($config->get('types.' . $definition->id()))) {
          $selected_entity_types[] = $definition->id();
        }
      }
    }
    $form['entities']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => 'Protected entity types',
      '#options' => $entity_types,
      '#default_value' => $selected_entity_types,
      '#attributes' => [
        'class' => ['content-lock-entity-types'],
      ],
    ];

    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $form['entities'][$definition->id()] = [
          '#type' => 'container',
          '#title' => $definition->getLabel(),
          '#theme' => 'content_lock_settings_entities',
          '#states' => [
            'visible' => [
              ':input[name="entity_types[' . $definition->id() . ']"]' => ['checked' => TRUE],
            ],
          ],
          '#attributes' => [
            'class' => [$definition->id()],
          ],
        ];

        $options = [
          '*' => $this->t('All'),
        ];

        if ($definition->getBundleEntityType()) {
          $bundles = $this->entityTypeManager
            ->getStorage($definition->getBundleEntityType())
            ->loadMultiple();

          foreach ($bundles as $bundle) {
            $options[$bundle->id()] = $bundle->label();
          }
        }
        else {
          $options[$definition->id()] = $definition->getLabel();
        }
        $form['entities'][$definition->id()]['bundles'] = [
          '#type' => 'checkboxes',
          '#title' => $definition->getBundleLabel() ?: $definition->getLabel(),
          '#description' => $this->t('Select the bundles on which enable content lock'),
          '#options' => $options,
          '#default_value' => $config->get('types.' . $definition->id()) ?: [],
          '#attributes' => ['class' => ['content-lock-entity-settings']],
        ];

        $form['entities'][$definition->id()]['settings']['translation_lock'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Lock only on entity translation level.'),
          '#default_value' => in_array($definition->id(), $config->get('types_translation_lock')?: []),
          '#description' => $this->t('Activating this options allows users to edit multiple translations concurrently'),
        ];
        if (!$this->moduleHandler->moduleExists('conflict')) {
          $form['entities'][$definition->id()]['settings']['translation_lock'] = [
            '#disabled' => TRUE,
            '#default_value' => FALSE,
            '#description' => $this->t('To allow editing multiple translations concurrently you need to install %module',
              [
                '%module' => Link::fromTextAndUrl('Conflict', Url::fromUri('https://www.drupal.org/project/conflict'))->toString(),
              ]
            ),
          ] + $form['entities'][$definition->id()]['settings']['translation_lock'];
        }

        $form['entities'][$definition->id()]['settings']['js_lock'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Lock form using JS.'),
          '#default_value' => in_array($definition->id(), $config->get('types_js_lock')?: []),
          '#description' => $this->t('Activating this options activates the lock when the user is on the form. This helps if modules interacting with form without a user interacting with the form, like the prefetch_cache module.'),
        ];

        if (!empty($definition->getHandlerClasses()['form'])) {
          $form['entities'][$definition->id()]['settings']['form_op_lock'] = [
            '#tree' => 1,
          ];
          $form['entities'][$definition->id()]['settings']['form_op_lock']['mode'] = [
            '#type' => 'radios',
            '#title' => $this->t('Lock only on entity form operation level.'),
            '#options' => [
              ContentLock::FORM_OP_MODE_DISABLED => $this->t('Disabled'),
              ContentLock::FORM_OP_MODE_WHITELIST => $this->t('Enable lock for selected form operations'),
              ContentLock::FORM_OP_MODE_BLACKLIST => $this->t('Disable lock for selected form operations'),
            ],
            '#default_value' => $config->get('form_op_lock.' . $definition->id() . '.mode') ?: ContentLock::FORM_OP_MODE_DISABLED,
            '#description' => $this->t('Activating this options allows users to edit different entity forms concurrently'),
          ];

          $form_ops = array_keys($definition->getHandlerClasses()['form']);
          $form_ops = array_combine($form_ops, $form_ops);
          $form['entities'][$definition->id()]['settings']['form_op_lock']['values'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Form operations'),
            '#options' => $form_ops,
            '#default_value' => (array) $config->get('form_op_lock.' . $definition->id() . '.values'),
            '#states' => [
              'invisible' => [
                ':input[name="' . $definition->id() . '[settings][form_op_lock][mode]"]' => ['value' => ContentLock::FORM_OP_MODE_DISABLED],
              ],
            ],
          ];
        }
      }
    }

    $form['#attached']['library'][] = 'content_lock/drupal.content_lock.settings';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $content_lock = $this->config('content_lock.settings');
    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        if ($form_state->getValue($definition->id())) {
          $content_lock->set('types.' . $definition->id(), $this->removeEmptyValue($form_state->getValue([$definition->id(), 'bundles'])));

          $translation_lock = (bool) $form_state->getValue([$definition->id(), 'settings', 'translation_lock']);
          $types_translation_lock = $content_lock->get('types_translation_lock')?:[];
          if ($translation_lock && !in_array($definition->id(), $types_translation_lock)) {
            $types_translation_lock[] = $definition->id();
          }
          elseif (!$translation_lock && in_array($definition->id(), $types_translation_lock)) {
            $types_translation_lock = array_diff($types_translation_lock, [$definition->id()]);
          }
          $content_lock->set('types_translation_lock', $types_translation_lock);

          $js_lock = (bool) $form_state->getValue([$definition->id(), 'settings', 'js_lock']);
          $types_js_lock = $content_lock->get('types_js_lock')?:[];
          if ($js_lock && !in_array($definition->id(), $types_js_lock)) {
            $types_js_lock[] = $definition->id();
          }
          elseif (!$js_lock && in_array($definition->id(), $types_js_lock)) {
            $types_js_lock = array_diff($types_js_lock, [$definition->id()]);
          }
          $content_lock->set('types_js_lock', $types_js_lock);

          $content_lock->set('form_op_lock.' . $definition->id() . '.mode', $form_state->getValue([$definition->id(), 'settings', 'form_op_lock', 'mode']));
          $content_lock->set('form_op_lock.' . $definition->id() . '.values', $this->removeEmptyValue((array) $form_state->getValue([$definition->id(), 'settings', 'form_op_lock', 'values'])));
        }
      }
    }

    $content_lock->set('verbose', $form_state->getValue('verbose'))
      ->save();
  }

  /**
   * Helper function to filter empty value in an array.
   *
   * @param array $array
   *   The array to check for empty values.
   *
   * @return array
   *   The array without empty values.
   */
  protected function removeEmptyValue(array $array) {
    return array_filter($array, function ($value) {
      return !empty($value);
    });
  }

  /**
   * Merge elements to the level up.
   *
   * Render API callback: Moves entity_reference specific Form API elements
   * (i.e. 'handler_settings') up a level for easier processing values.
   *
   * @param array $element
   *   The array to filter.
   *
   * @return array
   *   The array filtered.
   *
   * @see _entity_reference_field_settings_process()
   */
  public static function formProcessMergeParent(array $element) {
    $parents = $element['#parents'];
    array_pop($parents);
    $element['#parents'] = $parents;
    return $element;
  }

}
