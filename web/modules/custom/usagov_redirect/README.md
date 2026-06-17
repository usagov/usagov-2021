# USAGov Redirect module

This module creates an event subscriber that watches the RESPONSE event, which occurs once a response is created to respond to a request. It does a few things that are not conceptually related, except that they all act on RESPONSE.

## Modify "redirect pages" in static site output

The initial purpose of this module was to add `<meta name="robots" content="noindex" />` to the HTML pages Tome creates for URL redirects. Basically, it replaces the Symfony template for a redirect with a very similar bit of HTML that includes the desired `meta` tag. This is implemented in `src/RedirectSubscriber`.

Resources about this approach:

 - https://symfony.com/doc/current/components/http_kernel.html#the-request-response-lifecycle
 - https://symfony.com/doc/current/components/http_foundation.html#request
 - https://www.drupal.org/docs/develop/creating-modules/subscribe-to-and-dispatch-events#s-drupal-hooks
 - https://stackoverflow.com/questions/18205039/is-it-possible-to-change-the-default-redirect-message-in-symfony
 - https://drupalize.me/blog/responding-events-drupal
 - https://drupalsun.com/philipnorton42/2022/05/22/drupal-9-correct-way-redirect-page#

## Short-circuit processing of a malformed path

We caught some 500 errors in response to requests for a path like `/index.phpsssieddgdpathxsx` -- that is, `index.php` with some characters appended but no slash. `src/PathProcessor/UsagovInboundPathProcessor.php` redirects such requests to `/`. There's a bit more detail about why in the comments of that file.

## Don't serve /blog view from /es/blog

This is what `src/EventSubscriber/BlogLanguageSubscriber.php` does.
