<?php

namespace Drupal\dynamic_entity_reference_test_entity_provider\Entity;

use Drupal\entity_test\Entity\EntityTest;

/**
 * A test entity with a different provider.
 *
 * @ContentEntityType(
 *   id = "entity_test_provider",
 *   label = @Translation("Entity Test Provider"),
 *   entity_keys = {
 *     "uuid" = "uuid",
 *     "id" = "id",
 *     "label" = "name",
 *   }
 * )
 */
class EntityTestProvider extends EntityTest {

}
