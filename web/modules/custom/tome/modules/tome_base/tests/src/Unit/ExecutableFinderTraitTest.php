<?php

namespace Drupal\Tests\tome_base\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\tome_base\ExecutableFinderTrait;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @coversDefaultClass \Drupal\tome_base\ExecutableFinderTrait
 * @group tome_base
 */
class ExecutableFinderTraitTest extends UnitTestCase {

  /**
   * @covers \Drupal\tome_base\ExecutableFinderTrait::findExecutable
   *
   * @dataProvider dataProvider
   */
  public function testFindExecutable($first_argument, $argv, $expected) {
    $mock = $this->getMockForTrait(ExecutableFinderTrait::class);
    $input = $this->prophesize(InputInterface::class);
    $input->getFirstArgument()->willReturn($first_argument);
    $reflection = new \ReflectionMethod(
      get_class($mock),
      'findExecutable'
    );
    $reflection->setAccessible(TRUE);
    $_SERVER['argv'] = $argv;
    $this->assertEquals($expected, $reflection->invoke($mock, $input->reveal()));
  }

  /**
   * Data provider for testFindExecutable.
   *
   * @return array
   *   A data array.
   */
  public function dataProvider() {
    return [
      [
        'tome:static',
        explode(' ', 'php command.php tome:static foo'),
        'php command.php',
      ],
      [
        'tome:static',
        explode(' ', 'php command.php --backend --option tome:static foo'),
        'php command.php --option',
      ],
    ];
  }

}
