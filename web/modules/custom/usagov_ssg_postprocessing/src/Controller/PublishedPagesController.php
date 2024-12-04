<?php

namespace Drupal\usagov_ssg_postprocessing\Controller;

use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\Router;
use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;
use Drupal\usagov_ssg_postprocessing\Data\PublishedPagesRow;
use Drupal\usagov_wizard\WizardDataLayer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class PublishedPagesController extends ControllerBase {

  private array $csvHeader = [
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
    "hasBenefitCategory",
    "Categories",
  ];

  public function __construct(
    private EventDispatcherInterface $dispatcher,
    private Request $request,
    private Router $router,
    private AliasManagerInterface $pathAliasManager,
    private BreadcrumbManager $breadcrumb,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      dispatcher: $container->get('event_dispatcher'),
      request: $container->get('request_stack')->getCurrentRequest(),
      router:  $container->get('router.no_access_checks'),
      pathAliasManager: $container->get('path_alias.manager'),
      breadcrumb: $container->get('breadcrumb'),
    );
  }

  public function buildFile() {
    // Set up to echo CSV rows to STDOUT/browser.
    ob_start();
    $out = fopen('php://output', 'w');
    fputcsv($out, $this->csvHeader);
    // Render published pages to output stream
    $this->saveNodeRows($out);
    $this->saveWizardRows($out);
    // Write contents to output stream
    $content = ob_get_clean();
    fclose($out);
    // Output CSV response
    $response = new Response();
    $response->headers->set('Content-Type', 'text/plain');
    $response->setContent($content);
    return $response->send();
  }

  protected function saveNodeRows($out): void {
    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition('type', [
        'basic_page',
        'bears_life_event',
        'directory_record',
        'federal_directory_index',
        'state_directory_record',
        'wizard_step',
      ], 'IN')
      ->condition('status', 1) //published
      ->sort('nid', 'ASC')
      ->accessCheck(TRUE)
      ->sort('nid')
      ->execute();

    foreach ($nids as $nid) {
      $node = $this->entityTypeManager()->getStorage('node')->load($nid);
      $row = $this->getNodeRow($node)->toArray();

      $row = array_map(fn($col) => trim($col), $row);
      fputcsv($out, $row);

      $origLanguage = $node->language();
      if ($languages = $node->getTranslationLanguages()) {
        foreach ($languages as $lang) {
          if ($lang->getId() !== $origLanguage->getId()) {
            // export translated node
            $trNode = $node->getTranslation($lang->getId());
            $trRow = $this->getNodeRow($trNode);
            $fields = array_map(fn($field) => trim($field), $trRow->toArray());
            fputcsv($out, $fields);
          }
        }
      }
    }
  }

  protected function saveWizardRows($out): void {
    $tids = $this->entityTypeManager()
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'wizard')
      ->condition('status', 1) //published
      ->sort('tid', 'ASC')
      ->accessCheck(TRUE)
      ->sort('tid')
      ->execute();

    foreach ($tids as $tid) {
      $wizard = $this->entityTypeManager()->getStorage('taxonomy_term')->load($tid);
      $row = $this->getWizardRow($wizard);
      fputcsv($out, $row->toArray());
    }
  }

  protected function getNodeRow(Node $node): PublishedPagesRow {
    $front_uri = $this->config('system.site')->get('page.front');
    $alias = $this->pathAliasManager->getAliasByPath('/node/' . $node->id());

    $isFront = ($alias === $front_uri);

    $pageType = usa_twig_vars_get_page_type($node);

    $languageManager = $this->languageManager();
    // The following is "dragons abound here" but Drupal does not make it possible
    // to change the language for building breadcrumbs after a request has started.
    $negotiatedProp = new \ReflectionProperty(get_class($languageManager), 'negotiatedLanguages');
    $value = $negotiatedProp->getValue($languageManager);
    $value['language_content'] = $node->language();
    $negotiatedProp->setValue($this->languageManager, $value);

    // To get the right breadcrumb/active trail for this routeMatch, the menu_breadcrumb module
    // must be configured to "Derive MenuActiveTrail from RouteMatch"
    $datalayer = new TaxonomyDatalayerBuilder(
      routeMatch: $this->getRouteMatchForNode($node),
      breadcrumbManager: $this->breadcrumb,
      node: $node,
      isFront: $isFront,
      basicPagesubType: $pageType ?? NULL,
    );
    $data = $datalayer->build();

    $data = $this->alterDatalayer($data);

    $baseURL = $this->request->getSchemeAndHttpHost();
    return PublishedPagesRow::datalayerForNode($data, $node, $baseURL);
  }

  /**
   * Get a valid routeMatch object for a node
   *
   * To get the same datalayer output, we need to set up a routeMatch for each
   * entity we are exporting that the datalayer module can look up via the
   * breadcrumb manager.
  */
  private function getRouteMatchForNode(Node $node): RouteMatchInterface {
    $route = $this->router->match('/node/' . $node->id());

    return new RouteMatch(
      route_name: $route['_route'],
      route: $route['_route_object'],
      parameters: ['node' => $node],
      raw_parameters: ['node' => $node->id(), 'language' => $node->language()->getId()]
    );
  }

  protected function getWizardRow(Term $wizard): PublishedPagesRow {
    $builder = new WizardDataLayer($wizard, $this->entityTypeManager);
    $data = $builder->getData([]);

    $baseURL = $this->request->getSchemeAndHttpHost();
    return PublishedPagesRow::datalayerForWizard($data, $wizard, $baseURL);
  }

  private function alterDatalayer(array $data): array {
    // Let other modules add to the datalayer payload.
    $datalayerEvent = new DatalayerAlterEvent($data);
    $this->dispatcher->dispatch($datalayerEvent, DatalayerAlterEvent::EVENT_NAME);
    return $datalayerEvent->datalayer;
  }

}
