<?php

namespace Drupal\webform_devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides route responses for webform devel schema.
 */
class WebformDevelSchemaController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The webform devel schema generator.
   *
   * @var \Drupal\webform_devel\WebformDevelSchemaInterface
   */
  protected $schema;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configFactory = $container->get('config.factory');
    $instance->schema = $container->get('webform_devel.schema');
    return $instance;
  }

  /**
   * Returns a webform's schema as a CSV.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform to be exported.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   A streamed response containing webform's schema as a CSV.
   */
  public function index(WebformInterface $webform) {
    $multiple_delimiter = $this->configFactory->get('webform.settings')->get('export.multiple_delimiter') ?: ';';

    // From: http://obtao.com/blog/2013/12/export-data-to-a-csv-file-with-symfony/
    $response = new StreamedResponse(function () use ($webform, $multiple_delimiter) {
      $handle = fopen('php://output', 'r+');

      // Header.
      fputcsv($handle, $this->schema->getColumns());

      // Rows.
      $elements = $this->schema->getElements($webform);
      foreach ($elements as $element) {
        $element['options_text'] = implode($multiple_delimiter, $element['options_text']);
        $element['options_value'] = implode($multiple_delimiter, $element['options_value']);
        fputcsv($handle, $element);
      }

      fclose($handle);
    });
    $response->headers->set('Content-Type', 'application/force-download');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $webform->id() . '.schema.csv"');
    return $response;
  }

}
