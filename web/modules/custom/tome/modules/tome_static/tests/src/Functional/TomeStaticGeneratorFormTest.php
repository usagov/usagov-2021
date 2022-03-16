<?php

namespace Drupal\Tests\tome_static\Functional;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests the functionality of the Tome Static form.
 *
 * @group tome_static
 */
class TomeStaticGeneratorFormTest extends BrowserTestBase {

  use CronRunTrait;
  use ImageFieldCreationTrait;
  use TestFileCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'tome_static',
    'image',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createContentType([
      'type' => 'article',
    ]);

    $field_settings = [
      'file_extensions' => 'png',
    ];
    $formatter_settings = [
      'image_style' => 'large',
      'image_link' => '',
    ];
    $this->createImageField('field_image', 'article', [], $field_settings, [], $formatter_settings);

    $directory = $this->siteDirectory . '/files/tome/static';
    $settings['settings']['tome_static_directory'] = (object) [
      'value' => $directory,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    $this->drupalLogin($this->createUser(['use tome static']));
  }

  /**
   * Tests the static generator form.
   */
  public function testStaticGeneratorForm() {
    /** @var \Drupal\tome_static\StaticGeneratorInterface $static */
    $static = \Drupal::service('tome_static.generator');

    $valid_images = [];
    foreach ($this->getTestFiles('image') as $image) {
      $regex = '/\.png$/i';
      if (preg_match($regex, $image->filename)) {
        $valid_images[] = $image;
      }
    }

    $file = File::create([
      'uri' => $valid_images[0]->uri,
      'status' => FILE_STATUS_PERMANENT,
    ]);
    $file->save();

    $image_factory = $this->container->get('image.factory');
    $image = $image_factory->get($valid_images[0]->uri);
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test node',
      'field_image' => [
        'target_id' => $file->id(),
        'width' => $image->getWidth(),
        'height' => $image->getHeight(),
      ],
    ]);

    $filename = $static->getStaticDirectory() . base_path() . 'node/' . $node->id() . '/index.html';
    $image_filename = $static->getStaticDirectory() . file_url_transform_relative(file_create_url(ImageStyle::load('large')->buildUri($file->getFileUri())));
    $this->assertFileNotExists($filename);
    $this->assertFileNotExists($image_filename);

    $this->drupalGet('/admin/config/tome/static/generate');
    $this->submitForm([], 'Submit');

    $this->assertFileExists($filename);
    $this->assertFileExists($image_filename);
    $this->assertStringContainsString('Test node', file_get_contents($filename));
  }

}
