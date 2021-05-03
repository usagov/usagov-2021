<?php

namespace Drupal\stepbystep\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\stepbystep\Form\SequenceIntroductionForm;
use Drupal\stepbystep\Form\SequenceResetForm;
use Drupal\stepbystep\Plugin\SequenceInterface;
use Drupal\stepbystep\SequenceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for displaying Step by Step sequences.
 */
class SequenceController extends ControllerBase {

  /**
   * The Step by Step sequence manager service.
   *
   * @var \Drupal\stepbystep\SequenceManager
   */
  protected $sequenceManager;

  /**
   * SequenceIntroductionForm constructor.
   *
   * @param \Drupal\stepbystep\SequenceManager $sequence_manager
   *   The Step by Step sequence manager service.
   */
  public function __construct(SequenceManager $sequence_manager) {
    $this->sequenceManager = $sequence_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.stepbystep_sequence')
    );
  }

  /**
   * Handles a Step by Step page request.
   *
   * @param string $sequence
   *   The active sequence plugin ID.
   * @param string $step
   *   The ID of the step to show, or one of the following special values:
   *    - NULL: shows the sequence introduction form.
   *    - 'reset': shows the sequence reset form.
   *    - 'next': Redirects to the first available step that needs to be done.
   */
  public function build($sequence = NULL, $step = NULL) {
    if (!$this->currentUser()->hasPermission('use_stepbystep')) {
      throw new AccessDeniedHttpException();
    }

    /** @var \Drupal\stepbystep\Plugin\SequenceInterface $sequence */
    $sequence = $this->sequenceManager->createInstance($sequence);
    if ($step == 'reset') {
      return $this->formBuilder()->getForm(SequenceResetForm::class, $sequence);
    }
    elseif ($step == 'next') {
      // Find the URL of the next uncompleted step and redirect to it.
      return new RedirectResponse($sequence->getNextUrl()->toString());
    }
    elseif (empty($step)) {
      return $this->formBuilder()->getForm(SequenceIntroductionForm::class, $sequence);
    }
    else {
      // Reset all the steps marked 'n/a' so that they get re-tried once.
      $sequence->resetProgress(SequenceInterface::NOT_APPLICABLE);
      // Find the URL of the requested step and redirect to it.
      return new RedirectResponse($sequence->getUrl($step)->toString());
    }
  }

}
