<?php

namespace Drupal\Tests\tome_sync\Unit\Normalizer;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\tome_sync\Normalizer\ContentEntityNormalizer;
use Prophecy\Argument;
use Symfony\Component\Serializer\Serializer;

/**
 * @coversDefaultClass \Drupal\tome_sync\Normalizer\ContentEntityNormalizer
 * @group tome_sync
 */
class ContentEntityNormalizerTest extends UnitTestCase {

  /**
   * Tests the normalize() method.
   *
   * @covers ::normalize
   */
  public function testNormalize() {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $normalizer = new ContentEntityNormalizer($entity_type_manager, $entity_type_repository, $entity_field_manager);
    $serializer = $this->prophesize(Serializer::class);
    $serializer->normalize(Argument::cetera())
      ->willReturn('foo');
    $normalizer->setSerializer($serializer->reveal());

    $data_definition = $this->prophesize(DataDefinitionInterface::class);
    $field_list = $this->prophesize(FieldItemListInterface::class);
    $field_list->getDataDefinition()
      ->willReturn($data_definition->reveal());
    $data_definition->isInternal(Argument::cetera())
      ->willReturn(FALSE)
      ->shouldBeCalled();
    $field_list->access(Argument::cetera())
      ->willReturn(TRUE);

    $definitions = [
      'field_foo' => $field_list,
      'id_key' => $field_list,
      'revision_key' => $field_list,
      'metatag' => $field_list,
    ];
    $typed_data = $this->prophesize(ComplexDataInterface::class);
    $typed_data->getProperties(TRUE)
      ->willReturn($definitions)
      ->shouldBeCalled();

    $content_entity = $this->prophesize(ContentEntityBase::class);
    $content_entity->getTypedData(Argument::cetera())
      ->willReturn($typed_data->reveal());

    $entity_type = $this->prophesize(ContentEntityTypeInterface::class);

    $entity_type->id(Argument::cetera())
      ->willReturn('my_type');
    $entity_type->getKey(Argument::exact('id'))
      ->willReturn('id_key');
    $entity_type->getKey(Argument::exact('revision'))
      ->willReturn('revision_key');
    $entity_type->get(Argument::any())
      ->willReturn(NULL);

    $content_entity->getEntityType(Argument::cetera())
      ->willReturn($entity_type);

    $values = $normalizer->normalize($content_entity->reveal());
    $this->assertEquals(['field_foo' => 'foo'], $values, 'The ID, revision, and denied keys are removed properly.');
  }

}
