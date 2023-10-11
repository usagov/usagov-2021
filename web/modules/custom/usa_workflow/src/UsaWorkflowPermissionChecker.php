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

    $return = [];

    $currentUser = \Drupal::currentUser();
    if ($currentUser) {
      $node_param = \Drupal::routeMatch()->getParameter('node');

      // Check if the user has 'usa approve own content'
      // assign TRUE as value.
      if ($currentUser->hasPermission('usa approve own content')) {
        $this->usaApproveOwnContent = TRUE;
      }

      // Check if the user have 'usa delete own content'
      // assign TRUE as value.
      if ($currentUser->hasPermission('usa delete own content')) {
        $this->usaDeleteOwnContent = TRUE;
      }

      // These are valid regardless of whether we have an existing node:
      $return['usaApproveOwnContent'] = $this->usaApproveOwnContent ?? FALSE;
      $return['usaDeleteOwnContent'] = $this->usaDeleteOwnContent ?? FALSE;
      $return['currentUser']['id'] = $currentUser->id();
      $return['currentUser']['roles'] = $currentUser->getRoles();

      // Default revisionUser to anonymous. This way it won't match if there is no revisionUser
      // (e.g., new page or some edge case.)
      $return['revisionUser']['id'] = 0;
      $return['revisionUser']['roles'] = [];

      if ($node_param) {
        // Get the user who last revised this node.
        $return['isNewPage'] = FALSE;
        $rev_uid = $node_param->getRevisionUserId();
        $entityTypeManager = \Drupal::service('entity_type.manager');
        if ($entityTypeManager) {

          $storage = $entityTypeManager->getStorage('user');
          if ($storage) {
            $revisionUser = $storage->load($rev_uid);

            if ($revisionUser) {
              $return['revisionUser']['id'] = $revisionUser->id();
              $return['revisionUser']['roles'] = $revisionUser->getRoles(); // Do we ever need these?
            }
            else {
              // $rev_uid is invalid or $storage->load($rev_uid) failed
              \Drupal::logger('usa_workflow')->error('$rev_uid (@rev_uid) is invalid or $storage->load($rev_uid) failed',
                ['@rev_uid' => $rev_uid ?? '']);
            }
          }
          else {
            \Drupal::logger('usa_workflow')->error("getStorage('user') failed");
          }
        }
        else {
          \Drupal::logger('usa_workflow')->error("Drupal::service('entity_type.manager') failed");
        }
      }
      else {
        $return['isNewPage'] = TRUE;
      }
    }
    else {
      \Drupal::logger('usa_workflow')->error('\Drupal::currentUser() failed');
    }

    return $return;
  }

}
