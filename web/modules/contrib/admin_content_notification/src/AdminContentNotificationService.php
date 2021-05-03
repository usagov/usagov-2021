<?php

namespace Drupal\admin_content_notification;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * AdminContentNotificationService implement helper service class.
 */
class AdminContentNotificationService {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The mail manager instance.
   *
   * @var Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * The link generator instance.
   *
   * @var Drupal\Core\Mail\MailManager
   */
  protected $linkGenerator;

  /**
   * Module Handler.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Creates a verbose messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $account, MailManagerInterface $mailManager, LinkGeneratorInterface $linkGenerator, ModuleHandlerInterface $moduleHandler) {
    $this->configFactory = $config_factory;
    $this->account = $account;
    $this->mailManager = $mailManager;
    $this->linkGenerator = $linkGenerator;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Get settings of admin content notification.
   */
  public function getConfigs() {
    return $this->configFactory->get('admin_content_notification.settings');
  }

  /**
   * Get users of roles.
   *
   * @return array
   *   Array of User Uids.
   */
  public function getUsersOfRoles($roles) {
    $ids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', $roles, 'IN')
      ->execute();
    if (in_array('authenticated', $roles)) {
      $ids_authenticated = \Drupal::entityQuery('user')
        ->condition('status', 1)
        ->execute();
      $ids = array_unique(array_merge($ids, $ids_authenticated));
    }
    return $ids;
  }

  /**
   * Check if current user allowed to send admin content notification.
   *
   * @return bool
   *   Return true if allowed to send admin content notification.
   */
  public function isCurrentUserRoleAllowedToSendNotification() {
    $roles = $this->account->getRoles();
    $trigger_for_roles = ($this->getConfigs()->get('admin_content_notification_allowed_roles')) ?: [];
    return count(array_intersect(array_filter($trigger_for_roles), $roles));
  }

  /**
   * Send Eamil.
   *
   * @param Drupal\node\NodeInterface $node
   * @param bool $is_new
   */
  public function sendMail(NodeInterface $node, $is_new = FALSE) {
    global $base_url;
    $config = $this->getConfigs();
    $node_type = $node->getType();
    $node_type_label = $node->type->entity->label();
    // Checking if the nodetype is the one selected.
    $selected_node_types = $config->get('admin_content_notification_node_types');
    if (count($selected_node_types) && in_array($node_type, $selected_node_types)) {
      // Check if limiting based on node status.
      $selected_node_status = $config->get('admin_content_notification_trigger_on_node_status');
      if ($selected_node_status > 0) {
        $node_published = $node->isPublished();
        // Don't notify of published nodes.
        if ($node_published && $selected_node_status == 2) {
          return;
        }
        // Don't notify of unpublished nodes.
        elseif (!$node_published && $selected_node_status == 1) {
          return;
        }
      }
      $user = $is_new ? $node->getOwner() : $node->getRevisionUser();
      $user_name = $user->getDisplayName();
      $url = Url::fromUri($base_url . '/node/' . $node->id());
      $internal_link = $this->linkGenerator->generate($this->t('@title', ['@title' => $node->label()]), $url);
      $variables = [
        '@user_who_posted' => $user_name,
        '@content_link' => $internal_link,
        '@content_title' => $node->label(),
        '@content_type' => $node_type_label,
        '@action' => $is_new ? $this->t('posted') : $this->t('updated'),
      ];
      $subject = $this->t($config->get('admin_content_notification_email_subject'), $variables);
      $body = $this->t($config->get('admin_content_notification_email_body'), $variables);
      if ($this->isTokenEnabled()) {
        $token_service = \Drupal::token();
        // Replace the token for body.
        $body = $token_service->replace($body, ['node' => $node]);
        $subject = $token_service->replace($subject, ['node' => $node]);
      }
      $admin_email = $config->get('admin_content_notification_email');
      if (empty($admin_email)) {
        $roles_notify = array_keys(array_filter($config->get('admin_content_notification_roles_notified')));
        $ids = !empty($roles_notify) ? $this->getUsersOfRoles($roles_notify) : [];
        $emails = [];
        if (count($ids)) {
          $users = User::loadMultiple($ids);
          foreach ($users as $userload) {
            $emails[] = $userload->getEmail();
          }
        }
        $admin_email = implode(',', $emails);
      }
      $to = \Drupal::config('system.site')->get('mail');
      $params = [
        'body' => $body,
        'subject' => $subject,
        'bcc' => $admin_email,
        'nid' => $node->id(),
      ];

      // Allow to alter $admin_email
      // by using hook_admin_content_notification_recipients_alter().
      // @see admin_content_notification.api.php
      \Drupal::moduleHandler()
        ->alter('admin_content_notification_recipients', $admin_email, $node);

      // Allow to alter $params
      // by using hook_admin_content_notification_params_alter().
      // @see admin_content_notification.api.php
      \Drupal::moduleHandler()
        ->alter('admin_content_notification_params', $params, $node);

      if (strlen($admin_email) === 0) {
        return;
      }
      $key = 'admin_content_notification_key';
      $this->mailManager->mail('admin_content_notification', $key, $to, 'en', $params, \Drupal::config('system.site')->get('mail'), TRUE);
      $this->getLogger('admin_content_notification')->notice($this->t('Admin content notification sent to @emails.', ['@emails' => $admin_email]));
    }
  }

  /**
   * Check if token module enabled.
   *
   * @return bool
   *   Return True if enabled.
   */
  public function isTokenEnabled() {
    return $this->moduleHandler->moduleExists('token');
  }

}
