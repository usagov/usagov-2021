<?php

namespace Drupal\workbench_email\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Form\SubformState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TemplateForm.
 *
 * @package Drupal\workbench_email\Form
 */
class TemplateForm extends EntityForm {

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity Bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * Moderation info.
   *
   * @var \Drupal\workbench_moderation\ModerationInformationInterface|\Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new TemplateForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\workbench_moderation\ModerationInformationInterface $moderation_info
   *   The moderation info service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_bundle_info, $moderation_info, ModuleHandlerInterface $module_handler, Messenger $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $this->moderationInfo = $moderation_info;
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->has('workbench_moderation.moderation_information') ? $container->get('workbench_moderation.moderation_information') : $container->get('content_moderation.moderation_information'),
      $container->get('module_handler'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\workbench_email\TemplateInterface $workbench_email_template */
    $workbench_email_template = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $workbench_email_template->label(),
      '#description' => $this->t("Label for the Email Template."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $workbench_email_template->id(),
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\workbench_email\Entity\Template::load',
      ],
      '#disabled' => !$workbench_email_template->isNew(),
    ];

    $form['contents'] = [
      '#type' => 'details',
      '#title' => $this->t('Email contents'),
      '#open' => TRUE,
    ];

    $form['contents']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#default_value' => $workbench_email_template->getSubject(),
      '#description' => $this->t('Email subject. You can use tokens like [node:title] depending on the entity type being updated.'),
      '#required' => TRUE,
    ];

    $form['contents']['replyTo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reply-To'),
      '#maxlength' => 255,
      '#default_value' => $workbench_email_template->getReplyTo(),
      '#description' => $this->t('Email Reply-To. You can use tokens like [node:author:mail] depending on the entity type being updated.'),
      '#required' => FALSE,
    ];

    $default_body = $workbench_email_template->getBody() + [
      'value' => '',
      'format' => 'plain_text',
    ];
    $form['contents']['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#description' => $this->t('Email body, you may use tokens like [node:title] depending on the entity type being updated.'),
      '#required' => TRUE,
      '#format' => $default_body['format'],
      '#default_value' => $default_body['value'],
    ];

    // Display a token browser if the Token module is available.
    if ($this->moduleHandler->moduleExists('token')) {
      $form['contents']['tokens'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node'],
      ];
      $form['contents']['tokens-warning'] = [
        '#type' => 'item',
        '#title' => '',
        '#markup' => '<b>Warning:</b> The token browser currently only shows node tokens. However, there may be other tokens available depending on the entity type being updated.',
      ];
    }

    // Recipient types.
    $recipient_types = $workbench_email_template->recipientTypes();
    $form['enabled_recipient_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled recipient types'),
      '#required' => TRUE,
      '#options' => [],
      '#default_value' => [],
    ];
    $form['recipient_types_settings'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Recipient type configuration'),
    ];
    /** @var \Drupal\workbench_email\Plugin\RecipientTypeInterface $plugin */
    foreach ($recipient_types as $plugin_id => $plugin) {
      $form['enabled_recipient_types']['#options'][$plugin_id] = $plugin->getLabel();
      if ($plugin->isEnabled()) {
        $form['enabled_recipient_types']['#default_value'][$plugin_id] = $plugin_id;
      }
      if ($plugin->hasFormClass('configure')) {
        $form['recipient_types']['settings'][$plugin_id] = [
          '#tree' => TRUE,
          '#access' => FALSE,
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $plugin->getLabel(),
          '#group' => 'recipient_types_settings',
          '#parents' => ['recipient_types', $plugin_id, 'settings'],
        ];
        $subform_state = SubformState::createForSubform($form['recipient_types']['settings'][$plugin_id], $form, $form_state);
        $configurationForm = $plugin->buildConfigurationForm($form['recipient_types']['settings'][$plugin_id], $subform_state);
        if ($configurationForm) {
          $form['recipient_types']['settings'][$plugin_id] += $configurationForm;
          $form['recipient_types']['settings'][$plugin_id]['#access'] = TRUE;
        }
      }
    }

    // Bundles.
    $bundle_options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$this->isModeratableEntityType($entity_type)) {
        // Irrelevant - continue.
        continue;
      }
      $bundles = $this->entityBundleInfo->getBundleInfo($entity_type_id);
      if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
        $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
        $bundle_entities = $bundle_storage->loadMultiple(array_keys($bundles));
        foreach ($bundle_entities as $bundle_id => $bundle) {
          if ($this->isModeratableBundle($entity_type, $bundle_id)) {
            $bundle_options["$entity_type_id:$bundle_id"] = $bundle->label() . ' (' . $entity_type->getLabel() . ')';
          }
        }
      }
      // For non-bundleable entities bundle ID is same as entity type ID.
      elseif ($this->isModeratableBundle($entity_type, $entity_type_id)) {
        $bundle_options["$entity_type_id:$entity_type_id"] = $entity_type->getLabel() . ' (' . $entity_type->getLabel() . ')';
      }
    }
    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#options' => $bundle_options,
      '#access' => !empty($bundle_options),
      '#default_value' => $workbench_email_template->getBundles(),
      '#title' => $this->t('Bundles'),
      '#description' => $this->t('Limit to the following bundles. Select none to include all bundles.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\workbench_email\TemplateInterface $workbench_email_template */
    $workbench_email_template = $this->entity;
    $recipient_types = $workbench_email_template->recipientTypes();
    /** @var \Drupal\workbench_email\Plugin\RecipientTypeInterface $plugin */
    foreach ($recipient_types as $plugin_id => $plugin) {
      if ($plugin->hasFormClass('configure')) {
        $subform_state = SubformState::createForSubform($form['recipient_types']['settings'][$plugin_id], $form, $form_state);
        $plugin->validateConfigurationForm($form['recipient_types']['settings'][$plugin_id], $subform_state);
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\workbench_email\TemplateInterface $workbench_email_template */
    $workbench_email_template = $this->entity;
    $recipient_types = $workbench_email_template->recipientTypes();
    /** @var \Drupal\workbench_email\Plugin\RecipientTypeInterface $plugin */
    foreach ($recipient_types as $plugin_id => $plugin) {
      if ($plugin->hasFormClass('configure')) {
        $subform_state = SubformState::createForSubform($form['recipient_types']['settings'][$plugin_id], $form, $form_state);
        $plugin->submitConfigurationForm($form['recipient_types']['settings'][$plugin_id], $subform_state);
      }
    }
    $status = $workbench_email_template->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addStatus($this->t('Created the %label Email Template.', [
          '%label' => $workbench_email_template->label(),
        ]));
        break;

      default:
        $this->messenger->addStatus($this->t('Saved the %label Email Template.', [
          '%label' => $workbench_email_template->label(),
        ]));
    }
    $form_state->setRedirectUrl($workbench_email_template->toUrl('collection'));
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    // Filter out unchecked items.
    $types = [];
    foreach (array_filter($form_state->getValue('enabled_recipient_types')) as $type) {
      $types[$type] = [
        'status' => TRUE,
        'settings' => $form_state->getValue([
          'recipient_types',
          $type,
          'settings',
        ]),
      ];
    }
    $entity->set('recipient_types', $types);
    $entity->set('bundles', array_filter($entity->get('bundles')));
  }

  /**
   * Determines if an entity type has been marked as moderatable.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type object.
   *
   * @return bool
   *   TRUE if this entity type has been marked as moderatable, FALSE otherwise.
   */
  protected function isModeratableEntityType(EntityTypeInterface $entity_type) {
    if (method_exists($this->moderationInfo, 'isModeratableEntityType')) {
      return $this->moderationInfo->isModeratableEntityType($entity_type);
    }
    else {
      return $this->moderationInfo->canModerateEntitiesOfEntityType($entity_type);
    }
  }

  /**
   * Determines if an entity type/bundle is one that will be moderated.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition to check.
   * @param string $bundle
   *   The bundle to check.
   *
   * @return bool
   *   TRUE if this is a bundle we want to moderate, FALSE otherwise.
   */
  protected function isModeratableBundle(EntityTypeInterface $entity_type, $bundle) {
    if (method_exists($this->moderationInfo, 'isModeratableBundle')) {
      return $this->moderationInfo->isModeratableBundle($entity_type, $bundle);
    }
    else {
      return $this->moderationInfo->shouldModerateEntitiesOfBundle($entity_type, $bundle);
    }
  }

}
