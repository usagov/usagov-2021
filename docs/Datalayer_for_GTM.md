# Datalayer for Google Tag Manager

The `datalayer` is a JSON payload with customized attributes about a page read by Google Tag Manager from a `<script id="taxonomy-data">` in the `<head>` of every page.

## What it Does

The `datalayer` provides additional information about each page for use in Google Tag Manager triggers and events.

## Approach

This updated approach moves preparing the datalayer out of twig files and provides a way for modules to add or change what is rendered.

## Drupal Structures

Alterations and additions are handled by Drupal's Event system. The final payload is then rendered in twig.

## Altering or Adding to the Datalayer

The `usa_twig_vars` module prepares the initial datalayer array, which sets basic information about the content type, subpage type, node id, and the taxonomy breadcrumb.

Other modules can register an Event listener to change or add items to the datalayer.

First, register the listener in `<MODULE>.services.yml`

```yml
  MODULE_datalayer_alter_subscriber:
    class: '\Drupal\<MODULE>\EventSubscriber\DatalayerAlterSubscriber'
    tags:
      - { name: 'event_subscriber' }

```

You'll need a file in your `src` folder in the `EventSubscriber` directory. For the service above, the file name is `DatalayerAlterSubscriber.php` so that Drupal will autoload it.

```php
<?php

namespace Drupal\<MODULE>\EventSubscriber;

use Drupal\node\Entity\Node;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add benefit category info to datalayer.
 */
class DatalayerAlterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      DatalayerAlterEvent::EVENT_NAME => 'onDatalayerAlter',
    ];
  }

  /**
   * Adds category information to the datalayer.
   */
  public function onDatalayerAlter(DatalayerAlterEvent $event): void {
    $node = \Drupal::routeMatch()->getParameter('node');
    $event->datalayer['hasBenefitCategory'] = FALSE;

    // make any changes need to $event->datalayer array
    $event->datalayer['foo'] = 'bar';
  }

}
```

## Twig Templates

The `html.html.twig` template outputs the payload as JSON. There are a number of flags required to render properly, the line should look like the line below. I've wrapped the line here for readability and added comments

```php
{{ datalayer |
    // don't render accented characters as unicode entities
    json_encode(constant('JSON_UNESCAPED_UNICODE')
        // don't escape slashes in taxonomy URL and elsewhere
        b-or constant('JSON_UNESCAPED_SLASHES')
        b-or constant('JSON_PRETTY_PRINT'))
    | raw }}
```

## Testing Changes

You can use `scripts/drush/verify-pagelayer-data.php` to validate that your changes don't change the datalayer output. This is more useful for refactoring the event subscribers or ensuring that additions to the data don't introduce unintended changes. Using the script takes some preparation:

1. *Create Reference Output* The script compares Drupal outputs against a "known-good" data set, the published page CSV file created by a tome export. To prepare this file, you should export your local site using the code from the `dev` branch. Make sure the URLs in your published_pages.csv point to your development server and not to the default www.usa.gov.
2. *Add to the datalayer* In a new branch, create an event listener to make your changes.
3. *Run verify script* use `drush php:script scripts/drush/verify-pagelayer-data.php`. It will fetch pages from your local site and compare the output of your datalayer to the expected values in the CSV file.

The verify script takes two optional options, specified after `--`. Use `--files` if you need to use a different CSV file for testing`. Use the `--sample` flag for a quicker run that doesn't check every URL in the CSV file.

```sh
drush php:script scripts/drush/verify-pagelayer-data.php \
  -- --base https://example.com/ \
  --sample
```
## Known Issues and Concerns

### Taxonomy Data

The taxonomy URLs are generated via a system block for breadcrumbs. The output there is further processed in the theme at `usagov/templates/block--usagov-taxonomy-links.html.twig`. That template renders the taxonomy data as JSON which used to be output in the html template. Now, we still use that block to get the desired taxonomy data. However, the `usa_twig_vars` module parses the JSON from the block and adds it to the datalayer object it builds. Ideally, we should be able to get the breadcrumb info directly without having to render a block. We could do this via a separate event subscriber or as part of the initial datalayer build

### Past Approaches

All the manipulation of the datalayer was previously done in the theme layer, when rendering the datalayer properties and then the output of the taxonomy links block. Doing so results in potential code duplication and code that is difficult to maintain when new modules or features want to modify what is sent. A primary issue is if we end up with multiple twig files rendering the datalayer depending on what page is shown, then each file must output all the variables that datalayer must send. Adding one new property could require editing more than one template to make the change consistent.
