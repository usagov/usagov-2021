<?php

namespace Drupal\usagov_ssg_postprocessing\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;
use Drupal\usagov_ssg_postprocessing\Data\PublishedPagesRow;
use Symfony\Component\HttpFoundation\Response;

class PublishedPagesController extends ControllerBase {

  private array $CSVHeader = [
    "Hierarchy Level",
    "Page Type",
    "Page Sub Type",
    "Content Type",
    "Friendly URL",
    "Page ID",
    "Page Title",
    "Full URL",
    "Taxonomy Level 1",
    "Taxonomy Level 2",
    "Taxonomy Level 3",
    "Taxonomy Level 4",
    "Taxonomy Level 5",
    "Taxonomy Level 6",
    "Taxonomy URL Level 1",
    "Taxonomy URL Level 2",
    "Taxonomy URL Level 3",
    "Taxonomy URL Level 4",
    "Taxonomy URL Level 5",
    "Taxonomy URL Level 6",
    "Homepage?",
    "Toggle URL",
  ];
  public function buildCSV() {

    // TODO: HTTP-headers for CSV?

    // TODO: Output header row

    $this->getNodeCSV();
  }

  protected function getNodeCSV() {
    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition('status', 1) //published
      ->condition('nid', 25)
      ->sort('nid', 'ASC')
      ->accessCheck(TRUE)
      ->sort('nid')
      ->range(0, 100)
      ->execute();

    ob_start();
    $out = fopen('php://output', 'w');
    fputcsv($out, $this->CSVHeader);
    // TODO handle translated nodes (homepage in spanish)
    foreach ($nids as $nid) {
      $node = $this->entityTypeManager()->getStorage('node')->load($nid);
      $row = $this->getNodeRow($node);
      fputcsv($out, $row->toArray());

      $origLanguage = $node->language();
      if ($languages = $node->getTranslationLanguages()) {
        foreach ($languages as $lang) {
          if ($lang->getId() !== $origLanguage->getId()) {
            // export translated node
            $trNode = $node->getTranslation($lang->getId());
            $trRow = $this->getNodeRow($trNode);
            fputcsv($out, $trRow->toArray());
          }
        }
      }

    }
    $content = ob_get_clean();
    fclose($out);

    $response = new Response();
    $response->headers->set('Content-Type', 'text/plain');
    $response->setContent($content);
    return $response->send();
  }

  protected function getNodeRow(Node $node): PublishedPagesRow {
    $front_uri = $this->config('system.site')->get('page.front');
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id());

    $isFront = ($alias === $front_uri);

    $pageType = usa_twig_vars_get_page_type($node);

    // The following is "dragons abound here" but Drupal does not make it possible
    // to change the language for building breadcrumbs after a request has started.
    $languageManager = \Drupal::service('language_manager');
    $negotiatedProp = new \ReflectionProperty(get_class($languageManager), 'negotiatedLanguages');
    $value = $negotiatedProp->getValue($languageManager);
    $value['language_content'] = $node->language();
    $negotiatedProp->setValue($languageManager, $value);

    // To get the right breadcrumb/active trail for this routeMatch, the menu_breadcrumb module
    // must be configured to "Derive MenuActiveTrail from RouteMatch"
    // TODO: could we change that config only on this path??
    $datalayer = new TaxonomyDatalayerBuilder(
      routeMatch: $this->getRouteMatchForNode($node),
      breadcrumbManager: Drupal::service('breadcrumb'),
      node: $node,
      isFront: $isFront,
      basicPagesubType: $pageType ?? NULL,
    );

    $baseURL = \Drupal::request()->getSchemeAndHttpHost();
    return PublishedPagesRow::datalayerForNode($datalayer, $node, $baseURL);
  }

  /**
   * Get a valid routeMatch object for a node
   *
   * To get the same datalayer output, we need to set up a routeMatch for each
   * entity we are exporting that the datalayer module can lookup via the
   * breadcrumb manager.
  */
  private function getRouteMatchForNode(Node $node): RouteMatchInterface {
    $router = \Drupal::service('router.no_access_checks');
    $route = $router->match('/node/' . $node->id());

    return new RouteMatch(
      route_name: $route['_route'],
      route: $route['_route_object'],
      parameters: ['node' => $node],
      raw_parameters: ['node' => $node->id(), 'language' => $node->language()->getId()]
    );


  }
}
