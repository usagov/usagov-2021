<?php

namespace Drupal\autosave_form\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure autosave form settings for this site.
 */
class AutosaveFormSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new AutosaveFormSettingsForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'autosave_form_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'autosave_form.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('autosave_form.settings');

    $form['interval'] = [
      '#type' => 'number',
      '#title' => $this->t('The interval to use for triggering autosave in milliseconds.'),
      '#default_value' => $config->get('interval'),
    ];

    $form['active_on'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Active on:'),
      '#tree' => TRUE,
    ];
    $form['active_on']['content_entity_forms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Content Entity Forms'),
      '#default_value' => $config->get('active_on')['content_entity_forms'],
    ];
    $form['active_on']['config_entity_forms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Config Entity Forms'),
      '#default_value' => $config->get('active_on')['config_entity_forms'],
    ];

    $form['notification'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notification settings'),
      '#description' => $this->t('Display a simple notification every time content is saved'),
      '#tree' => TRUE,
    ];
    $form['notification']['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#default_value' => $config->get('notification')['active'],
    ];
    $form['notification']['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $config->get('notification')['message'],
      '#states' => [
        'visible' => [
          ':input[name="notification[active]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['notification']['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('The duration of the notification in milliseconds.'),
      '#default_value' => $config->get('notification')['delay'],
      '#states' => [
        'visible' => [
          ':input[name="notification[active]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $allowed_content_entity_types = $config->get('allowed_content_entity_types');
    $form['allowed_content_entities'] = [
      '#type' => 'details',
      '#open' => !empty($allowed_content_entity_types),
      '#title' => $this->t('Allowed Content Entity Forms'),
      '#description' => $this->t('In case no entity type is selected then autosave is enabled on all entity forms, otherwise it will be enabled only on the selected ones. Selecting only the entity type will enable all corresponding bundles and selecting only a subset of the bundles will enable autosave only for those bundles and will be disabled for the others.'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="active_on[content_entity_forms]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /** @var  \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $entity_type_label = $entity_type->getLabel();
        $bundles_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        $allowed_bundles = !empty($allowed_content_entity_types[$entity_type_id]['bundles']) ? $allowed_content_entity_types[$entity_type_id]['bundles'] : [];
        $bundles = [];
        foreach ($bundles_info as $key => $bundle) {
          $bundles[$key] = $bundle['label'];
        }

        $form['allowed_content_entities'][$entity_type_id]['active'] = [
          '#type' => 'checkbox',
          '#title' => $entity_type_label,
          '#default_value' => isset($allowed_content_entity_types[$entity_type_id]),
        ];
        $form['allowed_content_entities'][$entity_type_id]['bundles'] = [
          '#type' => 'details',
          '#open' => !empty($allowed_bundles),
          '#title' => $entity_type_label . ' ' . $this->t('bundles'),
          '#states' => [
            'visible' => [
              ':input[name="allowed_content_entities[' . $entity_type_id . '][active]"]' => ['checked' => TRUE],
            ],
          ],
        ];
        $form['allowed_content_entities'][$entity_type_id]['bundles']['selection'] = [
          '#type' => 'checkboxes',
          '#default_value' => $allowed_bundles,
          '#options' => $bundles,
          '#prefix' => '<div class="panel">',
          '#suffix' => '</div>',
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('autosave_form.settings');
    $allowed_content_entity_types = [];
    foreach ($form_state->getValue('allowed_content_entities') as $entity_type_id => $data) {
      if (!$data['active']) {
        continue;
      }
      $allowed_bundles = array_filter($data['bundles']['selection']);
      $allowed_content_entity_types[$entity_type_id]['bundles'] = $allowed_bundles;
    }
    $config->set('interval', $form_state->getValue('interval'))
      ->set('active_on', $form_state->getValue('active_on'))
      ->set('notification', $form_state->getValue('notification'))
      ->set('allowed_content_entity_types', $allowed_content_entity_types)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
