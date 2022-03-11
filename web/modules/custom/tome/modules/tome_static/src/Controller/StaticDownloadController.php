<?php

namespace Drupal\tome_static\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\tome_static\StaticGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Contains routes related to Tome Static.
 *
 * @internal
 */
class StaticDownloadController extends ControllerBase {

  /**
   * The static generator.
   *
   * @var \Drupal\tome_static\StaticGeneratorInterface
   */
  protected $static;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * StaticGeneratorForm constructor.
   *
   * @param \Drupal\tome_static\StaticGeneratorInterface $static
   *   The static generator.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(StaticGeneratorInterface $static, FileSystemInterface $file_system) {
    $this->static = $static;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tome_static.generator'),
      $container->get('file_system')
    );
  }

  /**
   * Presents a user interface to download a static build.
   */
  public function build() {
    $build = [];
    $download_url = Url::fromRoute('tome_static.download');
    if ($download_url->access()) {
      $build['description'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Download the latest static build of this site as a gzipped tar file.') . '</p>',
      ];
      $build['download'] = [
        '#type' => 'link',
        '#attributes' => [
          'class' => ['button'],
        ],
        '#title' => $this->t('Download'),
        '#url' => $download_url,
      ];
    }
    else {
      $build['description'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No static build available for download. <a href=":generate">Click here to generate one.</a>', [
          ':generate' => Url::fromRoute('tome_static.generate')->toString(),
        ]) . '</p>',
      ];
    }
    return $build;
  }

  /**
   * Downloads a tarball of the static build.
   */
  public function download() {
    $path = $this->fileSystem->getTempDirectory() . '/tome_static_export.tar.gz';
    $static_directory = $this->static->getStaticDirectory();
    $this->fileSystem->delete($path);

    $archiver = new ArchiveTar($path, 'gz');
    $archiver->addModify([$static_directory], '', $static_directory);

    $response = new BinaryFileResponse($path, 200, [], FALSE);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      basename($path)
    );
    return $response;
  }

  /**
   * Custom access callback to determine if there's anything to download.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function downloadAccess() {
    return AccessResult::allowedIf(file_exists($this->static->getStaticDirectory()) && (new \FilesystemIterator($this->static->getStaticDirectory()))->valid());
  }

}
