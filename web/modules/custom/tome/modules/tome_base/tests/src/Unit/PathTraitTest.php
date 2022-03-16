<?php

namespace Drupal\Tests\tome_base\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\tome_base\PathTrait;

/**
 * @coversDefaultClass \Drupal\tome_base\PathTrait
 * @group tome_base
 */
class PathTraitTest extends UnitTestCase {

  /**
   * @covers \Drupal\tome_base\PathTrait::joinPaths
   *
   * @dataProvider dataProvider
   */
  public function testJoinPaths($paths, $expected) {
    $mock = $this->getMockForTrait(PathTrait::class);
    $reflection = new \ReflectionMethod(
      get_class($mock),
      'joinPaths'
    );
    $reflection->setAccessible(TRUE);
    $this->assertEquals($expected, $reflection->invokeArgs($mock, $paths));
  }

  /**
   * Data provider for testJoinPaths.
   *
   * @return array
   *   A data array.
   */
  public function dataProvider() {
    return [
      [
        ['foo', 'bar'],
        'foo/bar',
      ],
      [
        ['foo/', '//bar//'],
        'foo/bar',
      ],
      [
        ['http://www.foo.com', 'bar'],
        'http://www.foo.com/bar',
      ],
      [
        ['http:///www.foo.com//', '//bar/'],
        'http://www.foo.com/bar',
      ],
    ];
  }

}
