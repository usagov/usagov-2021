<?php

namespace Drupal\workbench_email;

/**
 * A value object for queued email.
 */
class QueuedEmail {

  /**
   * Template to use.
   *
   * @var \Drupal\workbench_email\TemplateInterface
   */
  protected $template;

  /**
   * UUID of entity to send.
   *
   * @var string
   */
  protected $uuid;

  /**
   * Email address to send to.
   *
   * @var string
   */
  protected $to;

  /**
   * Constructs a new QueuedEmail object.
   *
   * @param \Drupal\workbench_email\TemplateInterface $template
   *   Template to use.
   * @param string $uuid
   *   Entity to use for token replacement.
   * @param string $to
   *   Email to send to.
   */
  public function __construct(TemplateInterface $template, $uuid, $to) {
    $this->template = $template;
    $this->uuid = $uuid;
    $this->to = $to;
  }

  /**
   * Gets value of template.
   *
   * @return \Drupal\workbench_email\TemplateInterface
   *   Value of template
   */
  public function getTemplate() {
    return $this->template;
  }

  /**
   * Gets value of uuid.
   *
   * @return string
   *   Value of uuid
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * Gets value of to.
   *
   * @return string
   *   Value of to
   */
  public function getTo() {
    return $this->to;
  }

}
