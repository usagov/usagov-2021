<?php

namespace Drupal\stepbystep;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\stepbystep\Plugin\SequenceInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// phpcs:disable Drupal.Commenting.InlineComment.SpacingBefore

/**
 * Provides a breadcrumb builder for Step by Step sequences.
 */
class SequenceBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The Step by Step sequence manager service.
   *
   * @var \Drupal\stepbystep\SequenceManager
   */
  protected $sequenceManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs the BookBreadcrumbBuilder.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\stepbystep\SequenceManager $sequence_manager
   *   The Step by Step sequence manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(TranslationInterface $string_translation, SequenceManager $sequence_manager, RequestStack $request_stack) {
    $this->stringTranslation = $string_translation;
    $this->sequenceManager = $sequence_manager;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Only build custom breadcrumbs if a Step by Step sequence is active for
    // the current request.
    return $this->sequenceManager::isSequenceActive($this->request);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $sequence = $this->sequenceManager->createInstanceFromRequest($this->request);
    $title = $sequence->getName();
    $route = $sequence->getRoute();

    // Build the breadcrumb trail.
    // Do not add the current page to the end of the breadcrumbs. Its visibility
    // is controlled on a global level by the active theme.
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($title, $route));

    // Add an additional link to the current step if needed.
    // Each step has a "primary" page whose URL looks something like:
    //    /admin/people?stepbystep=x&step=y
    // Each step can also have one or more "secondary" pages in the form:
    //    /admin?destination=/admin/people%3Fstepbystep%3Dx%26step%3Dy
    // When the user is on a secondary page, add a breadcrumb link that goes
    // to the primary page.
    //
    // If we are building breadcrumbs here and the 'stepbystep' parameter is not
    // found in the current request, the user must be on a secondary page.
    if (!$this->request->query->has(SequenceInterface::SEQUENCE)) {
      $step = $sequence->getStep();
      $step_title = $step['title'];
      $step_url = $sequence->getUrl($step['id']);
      $breadcrumb->addLink(Link::fromTextAndUrl($step_title, $step_url));
    }

    return $breadcrumb;
  }

}
