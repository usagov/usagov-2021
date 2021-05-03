<?php

namespace Drupal\workbench_email\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines content moderation state change events.
 *
 * @todo Remove when https://www.drupal.org/project/drupal/issues/2873287 is in.
 */
class ContentModerationStateChangedEvent extends Event {

  /**
   * The entity that was moderated.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $moderatedEntity;

  /**
   * The state the content has changed to.
   *
   * @var string
   */
  protected $newState;

  /**
   * The state the content was before, or FALSE if none existed.
   *
   * @var string|FALSE
   */
  protected $originalState;

  /**
   * The ID of the workflow which allowed the state change.
   *
   * @var string
   */
  protected $workflow;

  /**
   * Create a new ContentModerationStateChangedEvent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $moderated_entity
   *   The entity that is being moderated.
   * @param string $new_state
   *   The new state the content is moving to.
   * @param string $original_state
   *   The original state of the content, before the change was made.
   * @param string $workflow
   *   The ID of the workflow that allowed the state change.
   */
  public function __construct(ContentEntityInterface $moderated_entity, $new_state, $original_state, $workflow) {
    $this->moderatedEntity = $moderated_entity;
    $this->newState = $new_state;
    $this->originalState = $original_state;
    $this->workflow = $workflow;
  }

  /**
   * Get the entity that is being moderated.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity that is being moderated.
   */
  public function getModeratedEntity() {
    return $this->moderatedEntity;
  }

  /**
   * Get the new state of the content.
   *
   * @return string
   *   The state the content has been changed to.
   */
  public function getNewState() {
    return $this->newState;
  }

  /**
   * Get the original state of the content.
   *
   * @return string
   *   The state the content was before.
   */
  public function getOriginalState() {
    return $this->originalState;
  }

  /**
   * Get the ID of the workflow which allowed this state change.
   *
   * @return string
   *   The ID of the workflow.
   */
  public function getWorkflow() {
    return $this->workflow;
  }

}
