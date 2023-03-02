<?php

namespace Drupal\usa_contact_center_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller routines for USA Contact Center API routes.
 */
class USAContactCenterController extends ControllerBase {


  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a USAContactCenterController object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Process the get all the deleted nodes request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function getDeletedNodes() {
    $date = \Drupal::request()->query->get('deleted');
    $query = $this->connection->select('usa_node_delete_log', 'n')
      ->fields('n', [])
      ->orderBy('deleted_time', 'DESC');

    if (isset($date) && is_numeric($date)) {
      $query->condition('deleted_time', $date, '>=');
    }
    $result = $query->execute()->fetchAll();
    $wrapped_result = ['data' => $result];
    return new JsonResponse($wrapped_result);
  }

}
