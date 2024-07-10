<?php

namespace Drupal\usagov_redirect\RedirectListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class RedirectListener {

  public function onKernelResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();

    if (!($response instanceof RedirectResponse)) {
      return;
    }

    $uri  = $response->getTargetUrl();
    $html = `<!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8" />
            <meta name="robots" content="noindex" />
            <meta http-equiv="refresh" content="0;url=\'$uri\'" />

            <title>Redirecting to $uri</title>
        </head>
        <body>
            Redirecting to <a href="$uri">$uri</a>.
        </body>
    </html>`;

    $response->setContent($html);
  }

}
