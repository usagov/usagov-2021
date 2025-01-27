<?php

namespace Drupal\usa_workflow;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Check if a certain permission exist for current user.
 */
class UsaWorkflowPermissionChecker implements ContainerInjectionInterface {
  /**
   * Approve own content permission.
   *
   * @var bool
   */
  private $usaApproveOwnContent = FALSE;

  /**
   * Delete own content permission.
   *
   * @var bool
   */
  private $usaDeleteOwnContent = FALSE;

  public function __construct(
    private RouteMatchInterface $routeMatch,
    private EntityTypeManagerInterface $entityTypeManager,
    private AccountProxyInterface $currentUser,
    private LoggerInterface $logger,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      routeMatch: $container->get('current_route_match'),
      entityTypeManager: $container->get('entity_type.manager'),
      currentUser: $container->get('current_user'),
      logger: $container->get('logger.factory')->get('usa_workflow'),
    );
  }

  /**
   * WfUserPermission.
   *
   * @return array<string, mixed>
   *   the value should be of type array
   */
  public function wfUserPermission(): array {
    $return = [];

    $node_param = $this->routeMatch->getParameter('node');

    // Check if the user has 'usa approve own content'
    // assign TRUE as value.
    if ($this->currentUser->hasPermission('usa approve own content')) {
      $this->usaApproveOwnContent = TRUE;
    }

    // Check if the user have 'usa delete own content'
    // assign TRUE as value.
    if ($this->currentUser->hasPermission('usa delete own content')) {
      $this->usaDeleteOwnContent = TRUE;
    }

    // These are valid regardless of whether we have an existing node:
    $return['usaApproveOwnContent'] = $this->usaApproveOwnContent ?: FALSE;
    $return['usaDeleteOwnContent'] = $this->usaDeleteOwnContent ?: FALSE;
    $return['currentUser']['id'] = $this->currentUser->id();
    $return['currentUser']['roles'] = $this->currentUser->getRoles();

    // Default revisionUser to anonymous. This way it won't match if there is no revisionUser
    // (e.g., new page or some edge case.)
    $return['revisionUser']['id'] = 0;
    $return['revisionUser']['roles'] = [];

    if ($node_param) {
      // Get the user who last revised this node.
      $return['isNewPage'] = FALSE;
      $rev_uid = $node_param->getRevisionUserId();

      $storage = $this->entityTypeManager->getStorage('user');

      $revisionUser = $storage->load($rev_uid);

      if ($revisionUser) {
        $return['revisionUser']['id'] = $revisionUser->id();
        $return['revisionUser']['roles'] = $revisionUser->getRoles(); // Do we ever need these?
      }
      else {
        // $rev_uid is invalid or $storage->load($rev_uid) failed
        $this->logger->error('$rev_uid (@rev_uid) is invalid or $storage->load($rev_uid) failed',
          ['@rev_uid' => $rev_uid ?? '']);
      }
    }
    else {
      $return['isNewPage'] = TRUE;
    }

    return $return;
  }

}
