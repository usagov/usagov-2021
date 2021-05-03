<?php

namespace Drupal\content_lock\Controller;

use Drupal\content_lock\Ajax\LockFormCommand;
use Drupal\content_lock\ContentLock\ContentLock;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ContentLockController.
 *
 * @package Drupal\content_lock\Controller
 */
class ContentLockController extends ControllerBase {

  /**
   * Content lock service.
   *
   * @var \Drupal\content_lock\ContentLock\ContentLock
   */
  protected $lockService;

  /**
   * EntityBreakLockForm constructor.
   *
   * @param \Drupal\content_lock\ContentLock\ContentLock $lock_service
   *   Content lock service.
   */
  public function __construct(ContentLock $lock_service) {
    $this->lockService = $lock_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_lock')
    );
  }

  /**
   * Custom callback for the create lock route.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *
   * @see \Drupal\content_lock\Routing\ContentLockRoutes::routes()
   */
  public function createLockCall(Request $request, ContentEntityInterface $entity, $langcode, $form_op) {
    $response = new AjaxResponse();

    // Not lockable entity or entity creation.
    if (!$this->lockService->isLockable($entity, $form_op) || is_null($entity->id())) {
      $lockable = FALSE;
      $lock = FALSE;
    }
    else {
      $lockable = TRUE;
      $destination = $request->query->get('destination') ?: $entity->toUrl('edit-form')->toString();
      $lock = $lockable ? $this->lockService->locking($entity->id(), $langcode, $form_op, $this->currentUser()->id(), $entity->getEntityTypeId(), FALSE, $destination) : FALSE;

      // Render status messages from locking service.
      $response->addCommand(new PrependCommand('', ['#type' => 'status_messages']));

      if ($lock) {
        $language = $this->languageManager()->getLanguage($langcode);
        $url = $entity->toUrl('canonical', ['language' => $language]);
        $unlock_button = $this->lockService->unlockButton($entity->getEntityTypeId(), $entity->id(), $langcode, $form_op, $url->toString());
        $response->addCommand(new AppendCommand('.content-lock-actions.form-actions', $unlock_button));
      }
    }
    $response->addCommand(new LockFormCommand($lockable, $lock));

    return $response;
  }

  /**
   * Custom access checker for the create lock requirements route.
   *
   * @see \Drupal\content_lock\Routing\ContentLockRoutes::routes()
   */
  public function access(ContentEntityInterface $entity, AccountInterface $account) {
    return $entity->access('update', $account, TRUE);
  }

}
