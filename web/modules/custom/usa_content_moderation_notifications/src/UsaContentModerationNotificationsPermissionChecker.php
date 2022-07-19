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
  private $usaapproveowncontent = FALSE;

  /**
   * Delete own content permission.
   *
   * @var bool
   */
  private $usaapdeleteowncontent = FALSE;

  /**
   * WfUserpermission.
   *
   * @return array
   *   the value should be of type array
   */
  public function wfUserpermission() {
    $return = [];
    // $reviseduser = '';

    // Check if the user have 'usa approve own content'
    // assign TRUE as value.
    if (\Drupal::currentUser()->hasPermission('usa approve own content')) {
      $this->usaapproveowncontent = TRUE;
    }

    // Check if the user have 'usa delete own content'
    // assign TRUE as value.
    if (\Drupal::currentUser()->hasPermission('usa delete own content')) {
      $this->usadeleteowncontent = TRUE;
    }

    // Users and their roles from current node.
    $currentUser = \Drupal::currentUser();
    if (\Drupal::routeMatch()->getParameter('node')) {
      $node = \Drupal::routeMatch()->getParameter('node');
    }
    if (isset($node)) {
      if (($node !== NULL && !empty($node)) && get_class($node) == 'Drupal\node\Entity\Node') {
        $reviseduser = \Drupal::entityTypeManager()->getStorage('user')->load(\Drupal::routeMatch()->getParameter('node')->getRevisionUserId());
        // Current logged in user.
        $return['currentUser']['id'] = $currentUser->id();
        $return['currentUser']['roles'] = $currentUser->getRoles();
        $return['currentUser']['usaapproveowncontent'] = $this->usaapproveowncontent ?? FALSE;
        $return['currentUser']['usadeleteowncontent'] = $this->usadeleteowncontent ?? FALSE;
        // Reviseduser.
        if ($reviseduser !== NULL) {
          $return['reviseduser']['id'] = $reviseduser->id();
          $return['reviseduser']['roles'] = $reviseduser->getRoles();
        }
      }
    }
    return $return;
  }

}
