<?php

namespace Drupal\usa_workflow;

/**
 * Check if a certain permission exist for current user.
 */
class UsaWorkflowPermissionChecker {
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

  /**
   * WfUserPermission.
   *
   * @return array
   *   the value should be of type array
   */
  public function wfUserPermission() {
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $currentUser = \Drupal::currentUser();
    $revisedUser = $entityTypeManager->getStorage('user')->load(\Drupal::routeMatch()->getParameter('node')->getRevisionUserId());
    $return = [];

    // Check if the user have 'usa approve own content'
    // assign TRUE as value.
    if ($currentUser->hasPermission('usa approve own content')) {
      $this->usaApproveOwnContent = TRUE;
    }

    // Check if the user have 'usa delete own content'
    // assign TRUE as value.
    if ($currentUser->hasPermission('usa delete own content')) {
      $this->usaDeleteOwnContent = TRUE;
    }

    // Users and their roles from current node.
    if ($currentUser->id() == $revisedUser->id()) {
      $return['usaApproveOwnContent'] = $this->usaApproveOwnContent ?? FALSE;
      $return['usaDeleteOwnContent'] = $this->usaDeleteOwnContent ?? FALSE;
    }
    return $return;
  }

}
