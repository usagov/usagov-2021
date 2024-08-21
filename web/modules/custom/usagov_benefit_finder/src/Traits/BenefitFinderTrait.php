<?php

/**
 * @file
 * Contains \Drupal\usagov_benefit_finder\Traits\BenefitFinderTrait.
 */

namespace Drupal\usagov_benefit_finder\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;

/**
 * Provides common functions for benefit finder modules.
 *
 * @ingroup usagov_benefit_finder
 */
trait BenefitFinderTrait {

  /**
   * Gets life event node by life event ID and content mode.
   *
   * @param string $id
   *   The Life Event ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return NodeInterface
   *   The life event node.
   */
  public function getLifeEventById($id, $mode) {
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', 'bears_life_event')
      ->condition('field_b_id', $id)
      ->range(0, 1)
      ->accessCheck(TRUE);

    $node_id = $query->execute();
    $node_id = reset($node_id);

    return $this->getNode($node_id, $mode);
  }

  /**
   * Gets life event form node by life event form ID and content mode.
   *
   * @param string $id
   *   The Life Event form ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return NodeInterface
   *   The life event form node.
   */
  public function getLifeEventFormById($id, $mode) {
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', 'bears_life_event_form')
      ->condition('field_b_id', $id)
      ->range(0, 1)
      ->accessCheck(TRUE);

    $node_id = $query->execute();
    $node_id = reset($node_id);

    return $this->getNode($node_id, $mode);
  }

  /**
   * Gets benefits node by life event form node ID and content mode.
   *
   * @param int $nid
   *    The life event form node ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return NodeInterface[]
   *   The benefit nodes.
   */
  public function getBenefitsByLifeEventForm($nid, $mode) {
    $nodes = [];

    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', 'bears_benefit')
      ->condition('field_b_life_event_forms', $nid, 'CONTAINS')
      ->accessCheck(TRUE);

    $node_ids = $query->execute();
    foreach ($node_ids as $node_id) {
      $nodes[] = $this->getBenefit($node_id, $mode);
    }
    return $nodes;
  }

  /**
   * Gets agency node by agency node ID and content mode.
   *
   * @param int $nid
   *   The agency node ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return NodeInterface
   *   The agency node.
   */
  public function getAgency($nid, $mode) {
    return $this->getNode($nid, $mode);
  }

  /**
   * Gets criteria node by criteria node ID and content mode.
   *
   * @param int $nid
   *   The criteria node ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return NodeInterface
   *   The criteria node.
   */
  public function getCriteria($nid, $mode) {
    return $this->getNode($nid, $mode);
  }

  /**
   * Gets benefit node by benefit node ID and content mode.
   *
   * @param int $nid
   *   The benefit node ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return NodeInterface
   *   The benefit node.
   */
  public function getBenefit($nid, $mode) {
    return $this->getNode($nid, $mode);
  }

  /**
   * Gets life event form node by life event form node ID and content mode.
   *
   * @param int $nid
   *   The life event form node ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return NodeInterface
   *   The life event form node.
   */
  public function getLifeEventForm($nid, $mode) {
    return $this->getNode($nid, $mode);
  }

  /**
   * Gets node by node ID and content mode.
   *
   * @param int $nid
   *   The node ID.
   * @param string $mode
   *   The benefit finder content mode.
   * @return EntityInterface|NodeInterface
   *   The node revision entity.
   */
  public function getNode($nid, $mode) {
    if ($mode == "published") {
      $query = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->allRevisions()
        ->condition('nid', $nid)
        ->condition('status', 1)
        ->sort('vid', 'DESC')
        ->range(0, 1)
        ->accessCheck(TRUE);
    }
    elseif ($mode == "draft") {
      $query = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->allRevisions()
        ->condition('nid', $nid)
        ->sort('vid', 'DESC')
        ->range(0, 1)
        ->accessCheck(TRUE);
    }

    $result = $query->execute();

    if (!empty($result)) {
      $revision_id = key($result);
      $revision = $this->entityTypeManager->getStorage('node')->loadRevision($revision_id);
      if ($revision instanceof NodeInterface) {
        return $revision;
      }
      else {
        return NULL;
      }
    }
    else {
      return NULL;
    }
  }

}
