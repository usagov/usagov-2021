<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\scheduled_transitions\Plugin\Menu\LocalTask\ScheduledTransitionsLocalTask;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests local task.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\Plugin\Menu\LocalTask\ScheduledTransitionsLocalTask
 * @group scheduled_transitions
 */
class ScheduledTransitionsLocalTaskUnitTest extends UnitTestCase {

  /**
   * Tests operation not handled by hook.
   *
   * @covers ::getTitle
   */
  public function testLocalTaskTitle(): void {
    $configuration = [];
    $plugin_id = '';
    $plugin_definition = [
      'base_route' => 'entity.st_entity_test.canonical',
      'title' => 'Scheduled transitions',
    ];
    $assertCount = 42;
    $currentUserLanguage = 'de';
    $entityId = 64;

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn('st_entity_test');
    $entity->expects($this->any())
      ->method('id')
      ->willReturn($entityId);

    $parameters = $this->createMock(ParameterBag::class);
    $parameters->expects($this->any())
      ->method('all')
      ->willReturn(['st_entity_test' => $entity]);

    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->expects($this->any())
      ->method('getParameters')
      ->willReturn($parameters);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->at(0))
      ->method('condition')
      ->with('entity__target_type', 'st_entity_test')
      ->willReturnSelf();
    $query->expects($this->at(1))
      ->method('condition')
      ->with('entity__target_id', $entityId)
      ->willReturnSelf();
    $query->expects($this->any())
      ->method('count')
      ->willReturnSelf();
    // Return the fake *count* here.
    $query->expects($this->any())
      ->method('execute')
      ->willReturn($assertCount);
    $query->expects($this->at(2))
      ->method('condition')
      ->with('entity_revision_langcode', $currentUserLanguage)
      ->willReturn($query);

    $transitionStorage = $this->createMock(EntityStorageInterface::class);
    $transitionStorage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('scheduled_transition')
      ->willReturn($transitionStorage);

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->expects($this->once())
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => $currentUserLanguage]));

    $stringTranslation = $this->getStringTranslationStub();

    $localTask = new ScheduledTransitionsLocalTask($configuration, $plugin_id, $plugin_definition, $routeMatch, $entityTypeManager, $languageManager, $stringTranslation);
    $this->assertEquals(sprintf('Scheduled transitions (%s)', $assertCount), (string) $localTask->getTitle());
  }

}
