<?php

namespace Drupal\usagov_ssg_postprocessing\Drush\Commands;

use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\Router;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;
use Drupal\usagov_ssg_postprocessing\Data\PublishedPagesRow;
use Drupal\usagov_wizard\WizardDataLayer;
use Drupal\views\Views;
use Drush\Attributes\Command;
use Drush\Attributes\Argument;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A Drush commandfile.
 *
 * @phpstan-import-type TaxonomyBreadcrumb from TaxonomyDatalayerBuilder
 */
final class PublishedPagesCommands extends DrushCommands {

  /**
   * @var array<string> $csvHeader
   */
  private array $csvHeader = [
    "Hierarchy Level",
    "Page Type",
    "Page Path",
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
    "Toggle URL",
    "hasBenefitCategory",
    "Page Language",
    "Categories",
  ];

  /**
   * Constructs an UsagovSsgPostprocessingCommands object.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private ConfigFactoryInterface $configFactory,
    private EventDispatcherInterface $dispatcher,
    private Request $request,
    private Router $router,
    private AliasManagerInterface $pathAliasManager,
    private ChainBreadcrumbBuilderInterface $breadcrumb,
    private LanguageManagerInterface $languageManager,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      entityTypeManager: $container->get('entity_type.manager'),
      configFactory: $container->get('config.factory'),
      dispatcher: $container->get('event_dispatcher'),
      request: $container->get('request_stack')->getCurrentRequest(),
      router:  $container->get('router.no_access_checks'),
      pathAliasManager: $container->get('path_alias.manager'),
      breadcrumb: $container->get('breadcrumb'),
      languageManager: $container->get('language_manager')
    );
  }

  /**
   * Export published pages CSV
   */
  #[Command(name: 'usagov:published-csv', aliases: ['usapubcsv'])]
  #[Argument(name: 'outfile', description: 'Path for output file')]
  #[Usage(
    name: 'usagov_ssg_postprocessing:published-csv',
    description: 'Usage description')
  ]
  public function publishedCsv(mixed $outfile): void {
    $this->output()->writeln('<info>Publishing CSV to ' . $outfile . '</info>');

    $out = fopen($outfile, 'w');

    if (!str_starts_with($outfile, '/')) {
      $this->logger()->warning('Relative path given, current working dir: {dir}', ['dir' => getcwd()]);

    }
    if (FALSE === $out) {
      $this->output()->writeln("<error>Can not write to destination file.</error>");
      exit(1);
    }
    fputcsv($out, $this->csvHeader);
    // Render published pages to output file
    $this->saveNodeRows($out);
    $this->saveWizardRows($out);
    fclose($out);
  }

  protected function saveNodeRows(mixed $out): void {
    $nids = $this->entityTypeManager
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

      // Get DataLayer information on this node
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $row = $this->getNodeRow($node)->toArray();

      // Save this row into the spreadsheet
      $this->saveNodeRow($out, $node, $row);

      // If this is a Directory-Index, add in all letter pages (USAGOV-2103)
      if ($row[1] == 'federal_directory_index') {
        $baseUrl = $row[2];
        $fullBaseUrl = $row[5];
        $view = Views::getView('federal_agencies');

        // Difference languages have different letters
        if ($node->language()->getId() == 'en') {
          $view->setDisplay('attachment_1');
        }
        else {
          $view->setDisplay('attachment_2');
        }

        $view->execute();
        foreach ($view->result as $result) {
          $letter = strtolower($result->title_truncated);
          if ($letter == 'a') {
            continue;
          }
          $row[2] = $baseUrl . '/' . $letter;
          $row[5] = $fullBaseUrl . '/' . $letter;
          $this->saveNodeRow($out, $node, $row);
        }
      }
    }
  }

  protected function saveNodeRow(mixed $out, Node $node, mixed $row): void {

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

  protected function saveWizardRows(mixed $out): void {
    $tids = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'wizard')
      ->condition('status', 1) //published
      ->sort('tid', 'ASC')
      ->accessCheck(TRUE)
      ->sort('tid')
      ->execute();

    foreach ($tids as $tid) {
      $wizard = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      $row = $this->getWizardRow($wizard);
      fputcsv($out, $row->toArray());
    }
  }

  protected function getNodeRow(NodeInterface $node): PublishedPagesRow {
    $front_uri = $this->configFactory->get('system.site')->get('page.front');
    $alias = $this->pathAliasManager->getAliasByPath('/node/' . $node->id());

    $isFront = ($alias === $front_uri);

    $pageType = usa_twig_vars_get_page_type($node);

    // The following is "dragons abound here" but Drupal does not make it possible
    // to change the language for building breadcrumbs after a request has started.
    $negotiatedProp = new \ReflectionProperty(get_class($this->languageManager), 'negotiatedLanguages');
    $value = $negotiatedProp->getValue($this->languageManager);
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
  private function getRouteMatchForNode(NodeInterface $node): RouteMatchInterface {
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

  /**
   * @param TaxonomyBreadcrumb $data
   * @return TaxonomyBreadcrumb
   */
  private function alterDatalayer(array $data): array {
    // Let other modules add to the datalayer payload.
    $datalayerEvent = new DatalayerAlterEvent($data);
    $this->dispatcher->dispatch($datalayerEvent, DatalayerAlterEvent::EVENT_NAME);
    return $datalayerEvent->datalayer;
  }

}
