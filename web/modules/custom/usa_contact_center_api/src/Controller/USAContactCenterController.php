<?php

namespace Drupal\usa_contact_center_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller routines for USA Contact Center API routes.
 */
class USAContactCenterController extends ControllerBase {

  /**
   * Constructs a USAContactCenterController object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  final public function __construct(
    protected Connection $connection,
    private Request $request,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      connection: $container->get('database'),
      request: $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Process the get all the deleted nodes request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function getDeletedNodes() {
    $date = $this->request->query->get('deleted');
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

  /**
   * Process the get all the archived nodes request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function getArchivedNodes() {

    $query = $this->connection->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title', 'type', 'changed'])
      ->orderBy('changed', 'DESC');

    $query->condition('status', '0', '=');
    $query->condition('type', 'basic_page', '=');

    $date = $this->request->query->get('archived');
    if (isset($date) && is_numeric($date)) {
      $query->condition('changed', $date, '>=');
    }

    $result = $query->execute()->fetchAll();

    // We want to show a property of "updated_time" instead of "changed" as per USAGOV-1936.
    foreach ($result as &$item) {
      $item = (array) $item;
      $item['updated_time'] = $item['changed'];
      unset($item['changed']);
    }

    $wrapped_result = ['data' => $result];
    return new JsonResponse($wrapped_result);
  }

}
