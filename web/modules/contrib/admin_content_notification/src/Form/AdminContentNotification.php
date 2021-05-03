<?php

namespace Drupal\admin_content_notification\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\admin_content_notification\AdminContentNotificationService;

/**
 * Class AdminContentNotification implements settings for admin notification.
 */
class AdminContentNotification extends ConfigFormBase {

  /** A instance of the admin_content_notification helper services.
   *
   * @var \Drupal\admin_content_notification\AdminContentNotificationService
   */
  protected $adminContentNotificationService;

  /**
   * {@inheritdoc}
   */
  public function __construct(AdminContentNotificationService $adminContentNotificationService) {
    $this->adminContentNotificationService = $adminContentNotificationService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('admin_content_notification.common')
    );
  }

  /**
   * Get the form_id.
   *
   * @inheritDoc
   */
  public function getFormId() {
    return 'admin_content_notification_form';
  }

  /**
   * Build the Form.
   *
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('admin_content_notification.settings');
    $form = [];
    $form['admin_content_notification_content_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select the content types'),
      '#description' => $this->t('Choose the content type for which you want notification on content insert/update.'),
    ];

    $default_content_types = ($config->get('admin_content_notification_node_types')) ?: [];
    $form['admin_content_notification_content_types']['admin_content_notification_node_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#default_value' => $default_content_types,
      '#options' => node_type_get_names(),
    ];

    $trigger_node_update = ($config->get('admin_content_notification_trigger_on_node_update')) ?: FALSE;
    $form['admin_content_notification_trigger_on_node_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable on content update'),
      '#default_value' => $trigger_node_update,
      '#description' => $this->t('Please check on it if you want to send notification on update action as well.'),
    ];

    $trigger_node_status = ($config->get('admin_content_notification_trigger_on_node_status')) ?: 0;
    $content_status = [];
    $content_status[0] = $this->t('Notify for both published and unpublished content.');
    $content_status[1] = $this->t('Only notify for published content');
    $content_status[2] = $this->t('Only notify for unpublished content');
    $form['admin_content_notification_trigger_on_node_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Content status'),
      '#options' => $content_status,
      '#default_value' => $trigger_node_status,
      '#description' => $this->t('Select if you want to limit notifications to only published or only unpublished content.'),
    ];

    $trigger_for_roles = ($config->get('admin_content_notification_allowed_roles')) ?: [];
    $user_roles = user_role_names();
    $form['admin_content_notification_allowed_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select roles'),
      '#options' => $user_roles,
      '#default_value' => $trigger_for_roles,
      '#description' => $this->t('Please select the roles for which email notifications should trigger on content insert/update'),
    ];

    $form['admin_content_notification_recepient_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email Recipients'),
    ];

    $form['admin_content_notification_recepient_fieldset']['admin_content_notification_email_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipients Limit (Max)'),
      '#default_value' => !empty($config->get('admin_content_notification_email_limit')) ? $config->get('admin_content_notification_email_limit') : 50,
      '#description' => $this->t('Enter -1 if you want to ignore Recipients Limit'),
    ];

    $site_email = $config->get('mail');
    $admin_content_notification_email = $config->get('admin_content_notification_email');
    $form['admin_content_notification_recepient_fieldset']['admin_content_notification_email'] = [
      '#type' => 'textarea',
      '#title' => $this->t("Email Id's to whom the notification is to be sent, add comma separated emails in case of multiple recipients"),
      '#default_value' => isset($admin_content_notification_email) ? $admin_content_notification_email : $site_email,
      '#description' => $this->t('You can add emails upto Recipients Limit (Max)'),
    ];

    $form['admin_content_notification_recepient_fieldset']['admin_content_notification_email_or_markup']['#markup'] = '<strong>' . $this->t('OR') . '</strong>';

    $roles_to_be_notified = ($config->get('admin_content_notification_roles_notified')) ?: [];
    if (array_key_exists('anonymous', $user_roles)) {
      unset($user_roles['anonymous']);
    }
    $form['admin_content_notification_recepient_fieldset']['admin_content_notification_roles_notified'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select roles'),
      '#options' => $user_roles,
      '#default_value' => $roles_to_be_notified,
      '#description' => $this->t('Please select the roles to whom you want to send email, please remember to select roles in a way so that total user count should not be greater than You can add emails upto Recipients Limit (Max).'),
    ];

    $form['admin_content_notification_email_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email Settings'),
    ];

    $form['admin_content_notification_email_fieldset']['admin_content_notification_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Configurable email subject'),
      '#default_value' => $config->get('admin_content_notification_email_subject'),
      '#description' => $this->t('Enter subject of the email.'),
    ];

    $form['admin_content_notification_email_fieldset']['admin_content_notification_email_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configurable email body'),
      '#default_value' => $config->get('admin_content_notification_email_body'),
      '#description' => $this->t('Email body for the email. Use the following tokens: @user_who_posted, @content_link, @content_title, @content_type, @action (posted or updated, will update accrodingly).'),
    ];

    if ($this->adminContentNotificationService->isTokenEnabled()) {
      $form['admin_content_notification_email_fieldset']['admin_content_notification_email_tokens']['#markup'] = '<strong>' . $this->t('You can use tokens provided by token module as well.') . '</strong><br>';
      // Add the token tree UI.
      $form['admin_content_notification_email_fieldset']['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [
          'user',
          'node',
          'content-type',
          'current-date',
          'current-user'
        ],
        '#show_restricted' => TRUE,
        '#global_types' => FALSE,
        '#weight' => 90,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Get Editable config names.
   *
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['admin_content_notification.settings'];
  }

  /**
   * Add validate handler.
   *
   * @inheritDoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user_input_values = $form_state->getUserInput();
    $admin_content_notification_email_limit = $user_input_values['admin_content_notification_email_limit'];
    if ($admin_content_notification_email_limit != -1) {
      if (!empty($user_input_values['admin_content_notification_email'])) {
        $admin_content_notification_email = explode(',', $user_input_values['admin_content_notification_email']);
        if (count($admin_content_notification_email) > $admin_content_notification_email_limit) {
          $form_state->setErrorByName('admin_content_notification_email', $this->t('Email Ids should be less than ' . $admin_content_notification_email_limit . '.'));
        }
        foreach ($admin_content_notification_email as $email) {
          if (!\Drupal::service('email.validator')->isValid(trim($email))) {
            $form_state->setErrorByName('admin_content_notification_email', $this->t('Some Email Ids are not valid, please add all email ids in a valid format.'));
            break;
          }
        }
      }
      else {
        $roles_notified = array_keys(array_filter($user_input_values['admin_content_notification_roles_notified']));
        if (count($roles_notified)) {
          $ids = $this->adminContentNotificationService->getUsersOfRoles($roles_notified);
          if (count($ids) > $admin_content_notification_email_limit) {
            $form_state->setErrorByName('admin_content_notification_roles_notified', $this->t('User count for the Recipients should be less than ' . $admin_content_notification_email_limit . '.'));
          }
        }
      }
    }
  }

  /**
   * Add submit handler.
   *
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_input_values = $form_state->getUserInput();
    $config = $this->configFactory->getEditable('admin_content_notification.settings');
    $config->set('admin_content_notification_node_types', $user_input_values['admin_content_notification_node_types']);
    $config->set('admin_content_notification_trigger_on_node_update', $user_input_values['admin_content_notification_trigger_on_node_update']);
    $config->set('admin_content_notification_trigger_on_node_status', $user_input_values['admin_content_notification_trigger_on_node_status']);
    $config->set('admin_content_notification_email_limit', $user_input_values['admin_content_notification_email_limit']);
    $config->set('admin_content_notification_allowed_roles', $user_input_values['admin_content_notification_allowed_roles']);
    $config->set('admin_content_notification_email', $user_input_values['admin_content_notification_email']);
    $config->set('admin_content_notification_roles_notified', $user_input_values['admin_content_notification_roles_notified']);
    $config->set('admin_content_notification_email_subject', $user_input_values['admin_content_notification_email_subject']);
    $config->set('admin_content_notification_email_body', $user_input_values['admin_content_notification_email_body']);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
