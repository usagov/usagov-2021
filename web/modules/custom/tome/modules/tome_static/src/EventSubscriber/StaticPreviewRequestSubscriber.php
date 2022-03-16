<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tome_base\PathTrait;
use Drupal\tome_static\StaticGeneratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Routes valid requests to the static build.
 *
 * @internal
 */
class StaticPreviewRequestSubscriber implements EventSubscriberInterface {

  use PathTrait;
  use StringTranslationTrait;

  /**
   * The key used to identify a static session.
   */
  const SESSION_KEY = 'tome_static_preview';

  /**
   * The static generator.
   *
   * @var \Drupal\tome_static\StaticGeneratorInterface
   */
  protected $static;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Constructs a new StaticPreviewRequestSubscriber object.
   *
   * @param \Drupal\tome_static\StaticGeneratorInterface $static
   *   The static generator.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The current route match.
   */
  public function __construct(StaticGeneratorInterface $static, Session $session, CurrentRouteMatch $route_match) {
    $this->static = $static;
    $this->session = $session;
    $this->routeMatch = $route_match;
  }

  /**
   * Sets a response in case of a Dynamic Page Cache hit.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event to process.
   */
  public function onRequest(GetResponseEvent $event) {
    $request = $event->getRequest();
    if (!$this->session->get(static::SESSION_KEY) || $this->routeMatch->getRouteName() === 'tome_static.preview_exit') {
      return;
    }
    $path = realpath($this->joinPaths($this->static->getStaticDirectory(), $request->getPathInfo()));
    if (is_dir($path)) {
      $path = $this->joinPaths($path, 'index.html');
    }
    $exit_message = $this->t('<a style="@style" href=":link">Exit preview</a>', [
      ':link' => Url::fromRoute('tome_static.preview_exit')->setAbsolute(TRUE)->toString(),
      '@style' => 'position: fixed;top: 0;left: calc(50% - 60px);z-index: 2147483647;text-align: center;padding: 10px 20px;background: #00000063;border: 0;border-radius: 0 0 10px 10px;color: white;text-decoration: none;font-size: 13px;font-weight: bold;font-family: "Source Sans Pro","Lucida Grande",Verdana,sans-serif;',
    ]);
    if (strpos($path, realpath($this->static->getStaticDirectory())) === 0 && file_exists($path)) {
      $contents = file_get_contents($path);
      if (pathinfo($path, PATHINFO_EXTENSION) === 'html') {
        if (strpos($contents, '</body>') !== FALSE) {
          $contents = str_replace('</body>', $exit_message . '</body>', $contents);
        }
        else {
          $contents .= $exit_message;
        }
      }
      $mime_type = (new File($path))->getMimeType();
      $code = 200;
    }
    else {
      $contents = $this->t('<p>Request path not present in the static build.</p>') . $exit_message;
      $mime_type = 'text/html';
      $code = 404;
    }
    $event->setResponse(new Response($contents, $code, [
      'Content-Type' => $mime_type,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::REQUEST][] = ['onRequest', 30];
    return $events;
  }

}
