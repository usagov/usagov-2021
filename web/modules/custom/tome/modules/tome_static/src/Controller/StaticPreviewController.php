<?php

namespace Drupal\tome_static\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\tome_static\EventSubscriber\StaticPreviewRequestSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Allows a user to exit a static preview.
 *
 * @internal
 */
class StaticPreviewController extends ControllerBase {

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * StaticPreviewForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   */
  public function __construct(Session $session) {
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('session')
    );
  }

  /**
   * Removes the session variable and redirects a user back to the form.
   */
  public function build() {
    $this->session->remove(StaticPreviewRequestSubscriber::SESSION_KEY);
    $url = Url::fromRoute('tome_static.preview_form')->setAbsolute()->toString();
    return new RedirectResponse($url);
  }

}
