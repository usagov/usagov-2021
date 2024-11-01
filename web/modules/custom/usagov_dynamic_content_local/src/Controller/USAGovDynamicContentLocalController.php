<?php

namespace Drupal\usagov_dynamic_content_local\Controller;

use Drupal\node\Entity\Node;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\devel\Plugin\Devel\Dumper\Kint;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller routines for USA Contact Center API routes.
 */
class USAGovDynamicContentLocalController extends ControllerBase {


  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a USAGovDynamicContentLocalController object.
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
   * Get all the static content from the docs page.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function getDocsPage() {
    // Get the docs page
    $node = Node::load(2287);
    // dump($node);
    $page_content[] = [
        'title' => $node->getTitle(),
        'body' => $node->get('body')->value
    ];
    return new JsonResponse($page_content);
  }

   /**
   * Get all the static content from the docs page.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function getAllPages() {
    // Get the docs page
    $node = Node::load(2287);
    dump($node);
    $page_content[] = [
        'title' => $node->getTitle(),
        'body' => $node->get('body')->value
    ];
    return new JsonResponse($page_content);
  }

   /**
   * Get all the static content from the docs page.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function getPage() {
    // Get the docs page
    $node = Node::load(2289);
    // dump($node->field_content_list->entity);
    $page_content[] = [
        'title' => $node->getTitle(),
        'body' => $node->get('body')->value,
        'content_list' => $node->field_content_list[0]
    ];
    return new JsonResponse($page_content);
  }

}
