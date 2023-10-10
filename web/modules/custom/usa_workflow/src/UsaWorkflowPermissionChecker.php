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
      if ($node_param) {

        $rev_uid = $node_param->getRevisionUserId();
        $entityTypeManager = \Drupal::service('entity_type.manager');
        if ( $entityTypeManager ) {

          $storage = $entityTypeManager->getStorage('user');
          if ($storage) {

            $revisedUser = $storage->load($rev_uid);

            if ( $revisedUser ) {

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
            } else {
              // $rev_uid is invalid or $storage->load($rev_uid) failed
              \Drupal::logger('usa_workflow')->error('$rev_uid (@rev_uid) is invalid or $storage->load($rev_uid) failed',
                ['@rev_uid' => isset($rev_uid) ? $rev_uid : '' ]);
            }
          } else {
            \Drupal::logger('usa_workflow')->error("getStorage('user') failed");
          }
        } else {
          \Drupal::logger('usa_workflow')->error("Drupal::service('entity_type.manager') failed");
        }
      } else {
        \Drupal::logger('usa_workflow')->error('\Drupal::routeMatch()->getParameter("node") failed');
      }
    } else {
      \Drupal::logger('usa_workflow')->error('\Drupal::currentUser() failed');
    }

    return $return;
  }

}
