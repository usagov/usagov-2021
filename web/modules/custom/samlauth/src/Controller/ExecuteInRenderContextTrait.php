<?php

namespace Drupal\samlauth\Controller;

use Drupal\Core\GeneratedUrl;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

/**
 * Helper code for executing a callable inside a render context.
 *
 * This is not a well isolated trait; it requires properties to be initialized:
 * $this->renderer: a \Drupal\Core\Render\RendererInterface / the 'renderer'
 *   service.
 * $this->logger: optional, a \Psr\Log\LoggerInterface (Note: not a
 *   loggerFactory.)
 * $this->messenger: optional, a \Drupal\Core\Messenger\MessengerInterface /
 *   the 'messenger' service. Only used in case a supplied callable throws an
 *   exception during getTrustedRedirectResponse().
 */
trait ExecuteInRenderContextTrait {

  /**
   * Executes code within a render context; logs if leaked metadata is found.
   *
   * Generally, the reason for using this method is to guard against Drupal
   * throwing "leaked metadata" exceptions, by requests which are required to
   * return a response that implements CacheableResponseInterface. (In practice
   * these are e.g. TrustedRedirectResponse and various ResourceResponses.)
   *
   * Metadata is (nowadays) generally 'leaked' by completely unrelated code,
   * usually an Url::toString() call somewhere - so these fatal errors could
   * start happening by simply upgrading a contrib module. (The 'principled'
   * stance is that the cause of those errors should be traced and fixed
   * instead of ignored. However, that principled stance does not protect an
   * application from becoming unstable.)
   *
   * WARNING: since this method discards the leaked cacheability metadata, it
   * is only suitable for responses whose contents
   * - will never be cached (despite implementing CacheableResponseInterface -
   *   e.g. because "no_cache: TRUE" is set in the routing.yml)
   * - or are very predictable and well known, and there is a guarantee that
   *   those contents are not influenced by whatever bug that led to metadata
   *   being leaked.
   * Reason being: the leaked metadata is quite unlikely to corrupt the
   * contents of the current response, but it can corrupt future responses'
   * contents, by caching the response in the wrong circumstances.
   *
   * ---
   *
   * History / reason for why this is a thing:
   * 1. Url objects and Drupal rendering quick primer:
   * - It is quite important for Drupal's render cache, to receive correct
   *   metadata along with a response, indicating in exactly when / for which
   *   cases the response is cacheable. Wrong caching of rendered objects can
   *   e.g. introduce security issues. This is especially the case with URLs
   *   that could contain tokens.
   * - Most execution paths in a Drupal controller return a 'render array' (not
   *   a Response object), and execute within a 'render context'. Quote
   *   RendererInterface::executeInRenderContext phpDoc: "Within a render
   *   context, all bubbleable metadata is bubbled and hence tracked. Outside
   *   of a render context, it would be lost."
   * - Url::toString() automatically inserts related cacheability metadata into
   *   the current render context, to prevent the mentioned issues, since
   *   #2351015. Url::toString(TRUE) instead returns the metadata back to the
   *   caller along with the URL string.
   * 2. What's the problem:
   * - (Since #2450993) Drupal executes all controller routes within a render
   *   context, and afterwards checks if any metadata is present in the
   *   context. If the route/method returned a render array, the metadata is
   *   used; if it is a cacheable Response object, Drupal throws an exception
   *   in that case, saying "leaked metadata" was detected which indicates
   *   "early rendering". [*1]
   * - Nowadays, the vast(?) majority of data inadvertently 'leaked into' a
   *   render context comes from Url::toString() calls, because an average
   *   developer doesn't know about the intricacies of cacheability / what
   *   these calls do in the background. [*2]
   * - The combination of these points means that our code is going to end in a
   *   "leaked metadata" exception, each time some 'external' code happens to
   *   call Url::toString() that is not executed within a render context that's
   *   explicitly used to handle the call's metadata. This 'external' code can
   *   be event subscribers, hooks... even Url::fromRoute() and toString(TRUE)
   *   calls themselves can execute other code that then calls Url::toString()
   *   which causes the exception. [*3]
   * So:
   * The only way to reliably prevent "leaked metadata" exceptions is to create
   * a render context ourselves, and execute any of our code that could invoke
   * 'external' code.
   *
   * *1 If interested why this wording is used: see
   *    EarlyRenderingControllerWrapperSubscriber. Many discussions have been
   *    had about the effects of this, for instance:
   *    https://www.lullabot.com/articles/early-rendering-a-lesson-in-debugging-drupal-8
   *    / #2630808 / #2638686 / #2450993-133. The second issue has basically
   *    been stuck on the assertions in the latter message since January 2016,
   *    but... the majority of those assertions are incorrect, as the above
   *    summary makes clear. (I'm sure that was less clear in 2016 when more
   *   'early rendering' was still happening.)
   * *2 The issue surely is more prevalent because of Url::toString()'s lack of
   *    documentation. The casual developer doesn't know that constructing a
   *    simple URL requires them to consider cacheability, and why. The
   *    $collect_bubbleable_metadata argument to toString() is unintelligible
   *    for them (who knows what 'bubbleable metadata' is?), the code is nearly
   *    impossible for them to decipher, and the string "cache" occurs zero
   *    times in the Url class.
   * *3 Examples of Url::fromRoute() and Url::toString(TRUE) indirectly
   *    executing another offending Url::toString() call: #3161036 /
   *    #3160515-35 (both present in Rules 8.x-3.0-alpha6)
   *
   * @param callable $callable
   *   A callable whose return value will be returned by this method.
   * @param string $while
   *   (Optional) description of when we're doing this, for error logging.
   *
   * @return mixed
   *   The return value of the callable.
   */
  protected function executeInRenderContext(callable $callable, $while = '') {
    $context = new RenderContext();
    $result = $this->renderer->executeInRenderContext($context, $callable);
    if (!$context->isEmpty() && isset($this->logger)) {
      // Some code 'leaked' metadata. We cannot do anything about this / likely
      // do not suffer negative consequences from this, but it should still be
      // fixed. Log a warning, in the hope that someone sees it and traces the
      // offending code. (For anyone reading this comment while investigating
      // the below warning log: an explanation is given above, and
      // https://www.lullabot.com/articles/early-rendering-a-lesson-in-debugging-drupal-8
      // documents a tedious debugging session looking for a similar bug.)
      $prefix = $while ? "While $while, code" : 'Code';
      // There should be few distinct permutations of $while, so (while still
      // not ideal) it's OK to include in the translatable string.
      $this->logger->warning("$prefix leaked cacheability metadata. This indicates a bug somewhere (but it is hard to pinpoint where): if the same code is called in other scenarios too, it may cause fatal crashes, or bloat the render cache unnecessarily. Please investigate. Metadata: @data", [
        '@data' => $context->serialize(),
      ]);
    }

    return $result;
  }

  /**
   * Executes code in a render context; generates a TrustedRedirectResponse.
   *
   * Code which can use a 'regular' RedirectResponse (which isn't cacheable)
   * doesn't need this special protection. (This is why Drupal Core itself
   * doesn't suffer from "leaked metadata" exceptions.) However, any code that
   * wants to redirect to an external URL is forced to use a cacheable response
   * and therefore susceptible to the "leaked metadata" exception.
   * (RedirectResponseSubscriber throws an exception if we try to use a
   * non-cacheable response.)
   *
   * As noted at executeInRenderContext() comments: in general this code is
   * only suitable for responses whose responses will never be cached. However,
   * since a redirect (generally) doesn't contain much else than a URL, the
   * consequences of caching the response are probably easy enough to predict.
   *
   * @param callable $callable
   *   A callable that must return a URL to redirect to, in the form of a Url
   *   object or a string containing a full absolute URL.
   * @param string $while
   *   (Optional) description of when we're doing this, for error logging.
   * @param string $redirect_route_on_exception
   *   (Optional) Drupal route name to redirect to and return from this
   *   method as a TrustedRedirectResponse, instead of breaking, if an
   *   exception is thrown in the callable. This will call
   *   handleExceptionInRenderContext() before constructing the redirect URL -
   *   which will log and display the message (unless overridden). Suggested
   *   value: "<front>".
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   A response object.
   *
   * @see ExecuteInRenderContextTrait::executeInRenderContext()
   */
  protected function getTrustedRedirectResponse(callable $callable, $while = '', $redirect_route_on_exception = '') {
    // Since even Url::from*() and Url::toString(TRUE) calls themselves can
    // indirectly call other code that leaks metadata, we need to execute those
    // inside the render context too. So we have to wrap the callable inside
    // another anonymous function - which returns a GeneratedUrl object (unlike
    // $callable which returns either a string or a Url object).
    $function = $redirect_route_on_exception ?
      function () use ($callable, $while, $redirect_route_on_exception) {
        try {
          $url = $callable();
          if (is_object($url)) {
            if (!($url instanceof Url)) {
              throw new \RuntimeException('Object being returned/handled by getTrustedRedirectResponse() callable is not a Url.');
            }
            // We do things 'correctly' ourselves: where a toString() call
            // would bubble up the cacheability metadata in the render context
            // (and cause a warning to be logged), toString() returns it in the
            // GeneratedUrl object and passes it back for the caller to do
            // something appropriate with.
            $url = $url->toString(TRUE);
          }
        }
        catch (\Exception $e) {
          // If this call throws any kind of exception, that's a fatal error.
          $url = $this->handleExceptionInRenderContext($e, $redirect_route_on_exception, $while);
          if (is_object($url)) {
            if (!($url instanceof Url)) {
              throw new \RuntimeException('Object handleExceptionInRenderContext() callable is not a Url.');
            }
            $url = $url->toString(TRUE);
          }
        }

        return $url;
      } :
      function () use ($callable) {
        $url = $callable();
        if (is_object($url)) {
          if (!($url instanceof Url)) {
            throw new \RuntimeException('Object being returned/handled by getTrustedRedirectResponse() callable is not a Url.');
          }
          $url = $url->toString(TRUE);
        }

        return $url;
      };

    $url = $this->executeInRenderContext($function, $while);

    if (is_object($url)) {
      if (!($url instanceof GeneratedUrl)) {
        // This can only happen if executeInRenderContext() is wrongly extended.
        throw new \RuntimeException('Object being returned/handled by executeInRenderContext() callable is not a GeneratedUrl.');
      }
      $response = new TrustedRedirectResponse($url->getGeneratedUrl());
      $response->addCacheableDependency($url);
    }
    else {
      $response = new TrustedRedirectResponse($url);
    }

    return $response;
  }

  /**
   * Displays and/or logs exception message if the wrapped callable fails.
   *
   * Only called by getTrustedRedirectResponse() so far. Can be overridden to
   * implement other ways of logging and redirect to other paths. (Yes, those
   * are two separate functions that are just stuck together for convenient
   * overriding...)
   *
   * @param \Exception $exception
   *   The exception thrown.
   * @param string $default_redirect_route
   *   The route to redirect to, by default.
   * @param string $while
   *   (Optional) description of when the error was encountered.
   *
   * @return \Drupal\Core\Url|string
   *   URL to redirect to.
   */
  protected function handleExceptionInRenderContext(\Exception $exception, $default_redirect_route, $while = '') {
    if (isset($this->logger)) {
      $this->logger->error($exception->getMessage());
    }
    if (isset($this->messenger)) {
      $this->messenger->addError($exception->getMessage());
    }
    return Url::fromRoute($default_redirect_route);
  }

}
