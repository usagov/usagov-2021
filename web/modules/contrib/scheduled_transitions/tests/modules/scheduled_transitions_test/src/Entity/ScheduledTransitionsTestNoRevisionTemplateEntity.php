<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions_test\Entity;

use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;

/**
 * Defines the test entity class.
 *
 * @ContentEntityType(
 *   id = "st_entity_test_norevtemplate",
 *   label = @Translation("ST test entity (no revision template)"),
 *   base_table = "st_entity_test_norevtemplate",
 *   revision_table = "st_entity_test_norevtemplate_revision",
 *   admin_permission = "administer st_entity_test_norevtemplate entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/st_entity_test_norevtemplate/{st_entity_test_norevtemplate}",
 *     "edit-form" = "/st_entity_test_norevtemplate/{st_entity_test_norevtemplate}/edit",
 *   }
 * )
 */
class ScheduledTransitionsTestNoRevisionTemplateEntity extends EntityTestWithRevisionLog {

}
