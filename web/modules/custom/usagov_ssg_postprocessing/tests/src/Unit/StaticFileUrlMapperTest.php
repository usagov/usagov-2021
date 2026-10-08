<?php

namespace Drupal\Tests\usagov_ssg_postprocessing\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\usagov_ssg_postprocessing\StaticFileUrlMapper;

/**
 * Tests the static file URL mapper against the portable fixtures.
 *
 * @coversDefaultClass \Drupal\usagov_ssg_postprocessing\StaticFileUrlMapper
 * @group usagov_ssg_postprocessing
 */
final class StaticFileUrlMapperTest extends UnitTestCase {

  /**
   * Loads the shared expectation fixtures.
   *
   * @return array<string, mixed>
   *   The fixture sections keyed by behavior.
   */
  private static function fixtures(): array {
    return require __DIR__ . '/../../fixtures/static_file_url_mapper.php';
  }

  /**
   * @covers ::publicUriFromFileUrl
   */
  public function testPublicUriFromFileUrl(): void {
    foreach (self::fixtures()['public_uri'] as $url => $expected) {
      $this->assertSame($expected, StaticFileUrlMapper::publicUriFromFileUrl($url), $url);
    }
  }

  /**
   * @covers ::normalizeStaticFileUrlPrefix
   */
  public function testNormalizeStaticFileUrlPrefix(): void {
    foreach (self::fixtures()['normalized_url'] as $url => $expected) {
      $this->assertSame($expected, StaticFileUrlMapper::normalizeStaticFileUrlPrefix($url), $url);
    }
  }

  /**
   * @covers ::staticOutputRelativePathFromFileUrl
   */
  public function testStaticOutputRelativePathFromFileUrl(): void {
    foreach (self::fixtures()['static_output_relative_path'] as $url => $expected) {
      $this->assertSame($expected, StaticFileUrlMapper::staticOutputRelativePathFromFileUrl($url), $url);
    }
  }

  /**
   * @covers ::isGeneratedStaticAssetUrl
   */
  public function testIsGeneratedStaticAssetUrl(): void {
    foreach (self::fixtures()['generated_static_asset'] as $url => $expected) {
      $this->assertSame($expected, StaticFileUrlMapper::isGeneratedStaticAssetUrl($url), $url);
    }
  }

  /**
   * @covers ::referencedUrlsFromHtml
   */
  public function testReferencedUrlsFromHtml(): void {
    foreach (self::fixtures()['referenced_urls'] as $html => $expected) {
      $actual = StaticFileUrlMapper::referencedUrlsFromHtml($html);
      sort($actual);
      sort($expected);
      $this->assertSame($expected, $actual);
    }
  }

}
