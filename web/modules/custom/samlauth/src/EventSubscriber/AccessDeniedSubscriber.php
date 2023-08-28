<?php

namespace Drupal\samlauth\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Exception subscriber intercepting various "access denied" situations.
 */
class AccessDeniedSubscriber implements EventSubscriberInterface {

  /**
   * Routes which can throw TooManyRequestsHttpException.
   *
   * @var array
   */
  const FLOOD_CONTROL_ROUTES = [
    'samlauth.saml_controller_acs',
    'samlauth.saml_controller_sls',
  ];

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs a new redirect subscriber.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
  }

  /**
   * Redirects users when access is denied.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    // If our own routes threw a TooManyRequestsHttpException, don't spend time
    // redirecting to another page and rendering that. (Rendering would need to
    // be done from scratch because the page needs to include includes the
    // error message). Just a simple text string should do.
    if ($exception instanceof TooManyRequestsHttpException) {
      $route_name = $this->getCurrentRouteName($event);
      if (in_array($route_name, self::FLOOD_CONTROL_ROUTES)) {
        $event->setResponse(new Response($exception->getMessage(), $exception->getStatusCode()));
      }
    }
    // Authenticated access to /saml/login redirects to the user profile. This
    // is done in an event subscriber (rather than just opening up the route
    // and returning a redirect response from the controller route) because
    // this is what Core does for /user/login too. (Maybe it's a bit faster.
    // Maybe it's easier to override.) All our other routes do their
    // redirecting inside SamlController because there's more logic behind the
    // decision where to route.
    if ($exception instanceof AccessDeniedHttpException
        && $this->account->isAuthenticated()
        && $this->getCurrentRouteName($event) === 'samlauth.saml_controller_login') {
      $redirect_url = Url::fromRoute('entity.user.canonical', ['user' => $this->account->id()], ['absolute' => TRUE]);
      $event->setResponse(new RedirectResponse($redirect_url->toString()));
    }
  }

  /**
   * Gets the current route name.
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The event we're subscribed to.
   *
   * @return string
   *   The current route name.
   */
  private function getCurrentRouteName(KernelEvent $event) {
    // This method is just a reminder: we can either get the current request
    // from the event, or we can inject the current_route_match service if ever
    // necessary. There seems to be no consensus on what is 'better'.
    return RouteMatch::createFromRequest($event->getRequest())->getRouteName();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Use a higher priority than
    // \Drupal\Core\EventSubscriber\ExceptionLoggingSubscriber, because there's
    // no need to log the exception if we can redirect.
    $events[KernelEvents::EXCEPTION][] = ['onException', 75];
    return $events;
  }

}
