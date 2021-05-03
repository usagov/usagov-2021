<?php

namespace Drupal\term_reference_fancytree\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\term_reference_fancytree\Element\TermReferenceFancytree;

/**
 * Exposes a list of terms to any JS library via JSON.
 *
 * @package Drupal\term_reference_fancytree\Controller
 */
class SubTreeController extends ControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a SubTreeController object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(Request $request) {
    $this->request = $request;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager')
    );
  }

  /**
   * JSON callback for subtree.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON object with list of terms.
   */
  public function json() {
    $list = [];
    // The parent being expanded.
    $parent = $this->request->get('parent');
    // Flag to indicate if the parent is a vocabulary instead of a term.
    $vocab = $this->request->get('vocab');

    // If the parent is a vocabulary, we want to load the first level of terms
    // of that vocabulary.
    if ($vocab) {
      $taxonomy_vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($parent);
      $terms = TermReferenceFancytree::loadTerms($taxonomy_vocabulary, 0, -1);
      $list = TermReferenceFancytree::getNestedListJsonArray($terms, []);
    }
    // Otherwise, it's a term and we want it's children.
    else {
      $term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($parent);
      if ($term) {
        $taxonomy_vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($term->bundle());
        if ($taxonomy_vocabulary) {
          $terms = TermReferenceFancytree::loadTerms($taxonomy_vocabulary, $parent);
          $list = TermReferenceFancytree::getNestedListJsonArray($terms, []);
        }
      }
    }

    return new JsonResponse($list);
  }

}
