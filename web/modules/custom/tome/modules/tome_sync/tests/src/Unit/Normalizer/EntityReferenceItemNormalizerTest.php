<?php

namespace Drupal\Tests\tome_sync\Unit\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Tests\serialization\Unit\Normalizer\InternalTypedDataTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\tome_sync\Normalizer\EntityReferenceItemNormalizer;
use Prophecy\Argument;
use Symfony\Component\Serializer\Serializer;

/**
 * @coversDefaultClass \Drupal\tome_sync\Normalizer\EntityReferenceItemNormalizer
 * @group tome_sync
 */
class EntityReferenceItemNormalizerTest extends UnitTestCase {

  use InternalTypedDataTestTrait;

  /**
   * Tests the normalize() method.
   *
   * @covers ::normalize
   */
  public function testNormalize() {
    $entity_repository = $this->prophesize(EntityRepositoryInterface::class);
    $normalizer = new EntityReferenceItemNormalizer($entity_repository->reveal());
    $serializer = $this->prophesize(Serializer::class);
    $serializer->normalize(Argument::cetera())
      ->willReturn(1);
    $normalizer->setSerializer($serializer->reveal());

    $entity = $this->prophesize(EntityInterface::class);
    $entity->hasLinkTemplate('canonical')
      ->willReturn(FALSE)
      ->shouldBeCalled();
    $entity->uuid()
      ->willReturn('080e3add-f9d5-41ac-9821-eea55b7b42fb')
      ->shouldBeCalled();
    $entity->getEntityTypeId()
      ->willReturn('test_type')
      ->shouldBeCalled();

    $entity_reference = $this->prophesize(TypedDataInterface::class);
    $entity_reference->getValue()
      ->willReturn($entity->reveal())
      ->shouldBeCalled();

    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getSetting('target_type')
      ->willReturn('test_type');

    $field_item = $this->prophesize(EntityReferenceItem::class);
    $field_item->getIterator()
      ->willReturn(new \ArrayIterator(['target_id' => []]));
    $field_item->getFieldDefinition()
      ->willReturn($field_definition->reveal());

    $field_item->get('entity')
      ->willReturn($entity_reference)
      ->shouldBeCalled();

    $field_item->getProperties(TRUE)
      ->willReturn(['target_id' => $this->getTypedDataProperty(FALSE)])
      ->shouldBeCalled();

    $normalized = $normalizer->normalize($field_item->reveal());

    $expected = [
      'target_type' => 'test_type',
      'target_uuid' => '080e3add-f9d5-41ac-9821-eea55b7b42fb',
    ];
    $this->assertSame($expected, $normalized, 'The target_id and url keys were removed as expected.');
  }

}
