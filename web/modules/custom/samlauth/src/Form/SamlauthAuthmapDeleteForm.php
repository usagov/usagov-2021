<?php

namespace Drupal\samlauth\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirm the user wants to delete an authmap entry.
 *
 * @package Drupal\certificatelogin\Form
 */
class SamlauthAuthmapDeleteForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Temporary storage for the current authmap entry.
   *
   * @var array
   */
  protected $authmapEntry;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a router for Drupal with access check and upcasting.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to get authmap entries.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'samlauth_authmap_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if (!empty($this->authmapEntry['uid'])) {
      /** @var \Drupal\user\Entity\User $user */
      $user = $this->entityTypeManager->getStorage('user')->load($this->authmapEntry['uid']);
    }
    return $this->t('Are you sure you want to delete the link between login ID %id and Drupal user %user?', [
      '%id' => $this->authmapEntry['authname'],
      '%user' => isset($user) ? $user->getAccountName() : "<unknown> ({$this->authmapEntry['uid']})",
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('view.samlauth_map.page');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $authname = FALSE;
    $uid = $this->getRouteMatch()->getParameter('uid');
    if ($uid && filter_var($uid, FILTER_VALIDATE_INT)) {
      $authname = $this->connection->select('authmap', 'm')
        ->fields('m', ['authname'])
        ->condition('m.uid', (int) $uid)
        ->condition('m.provider', 'samlauth')
        ->execute()->fetchField();
    }
    if ($authname === FALSE) {
      // Display same error for either illegal UID or no record.
      $this->messenger()->addError(t('No authmap record found for uid @uid.', ['@uid' => $uid]));
      return [];
    }

    $this->authmapEntry = ['uid' => $uid, 'authname' => $authname];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $this->getRouteMatch()->getParameter('uid');
    if (!$uid || filter_var($uid, FILTER_VALIDATE_INT) === FALSE) {
      throw new \LogicException('It should be impossible to submit this form without valid uid parameter.');
    }
    $this->connection->delete('authmap')
      ->condition('uid', (int) $uid)
      ->condition('provider', 'samlauth')
      ->execute();
    $this->messenger()->addStatus($this->t('The link has been deleted.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
