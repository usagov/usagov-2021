<?php

namespace Drupal\Tests\tome_sync\Unit\Normalizer;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tome_sync\Normalizer\FieldItemNormalizer;
use Prophecy\Argument;
use Symfony\Component\Serializer\Serializer;

/**
 * @coversDefaultClass \Drupal\tome_sync\Normalizer\FieldItemNormalizer
 * @group tome_sync
 */
class FieldItemNormalizerTest extends UnitTestCase {

  /**
   * Tests the normalize() method.
   *
   * @covers ::normalize
   */
  public function testNormalize() {
    $normalizer = new FieldItemNormalizer();
    $serializer = $this->prophesize(Serializer::class);
    $serializer->normalize(Argument::cetera())
      ->willReturn('foo');
    $normalizer->setSerializer($serializer->reveal());

    $data = $this->prophesize(DataDefinitionInterface::class);
    $data->isComputed()
      ->willReturn(FALSE);

    $computed_data = $this->prophesize(DataDefinitionInterface::class);
    $computed_data->isComputed()
      ->willReturn(TRUE);

    $data_definitions = [
      'field_foo' => $data,
      'field_computed' => $computed_data,
    ];

    $typed_data = $this->prophesize(TypedDataInterface::class);
    $data = $this->prophesize(DataDefinitionInterface::class);
    $data->isInternal()
      ->willReturn(FALSE);
    $typed_data->getDataDefinition()
      ->willReturn($data);

    $typed_data_definitions = [
      'field_foo' => $typed_data,
      'field_computed' => $typed_data,
    ];

    $data_definition = $this->prophesize(ComplexDataDefinitionInterface::class);
    $data_definition->getPropertyDefinitions()
      ->willReturn($data_definitions)
      ->shouldBeCalled();
    $field_item = $this->prophesize(FieldItemInterface::class);
    $field_item->getProperties(TRUE)
      ->willReturn($typed_data_definitions)
      ->shouldBeCalled();
    $field_item->getDataDefinition()
      ->willReturn($data_definition->reveal());

    $normalized = $normalizer->normalize($field_item->reveal());

    $this->assertSame(['field_foo' => 'foo'], $normalized, 'Computed fields were removed properly.');
  }

}
