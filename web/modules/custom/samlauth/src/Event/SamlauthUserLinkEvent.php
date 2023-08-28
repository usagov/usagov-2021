<?php

namespace Drupal\samlauth\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * Wraps a samlauth user link event for event listeners.
 */
class SamlauthUserLinkEvent extends Event {

  /**
   * The Drupal user account to link.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The SAML attributes received from the IdP.
   *
   * Single values are typically represented as one-element arrays.
   *
   * @var array
   */
  protected $attributes;

  /**
   * Constructs a samlouth user link event object.
   *
   * @param array $attributes
   *   The SAML attributes received from the IdP.
   */
  public function __construct(array $attributes) {
    $this->attributes = $attributes;
  }

  /**
   * Gets the Drupal user account to link.
   *
   * @return \Drupal\user\UserInterface|null
   *   The Drupal user account.
   */
  public function getLinkedAccount() {
    return $this->account;
  }

  /**
   * Sets the Drupal user account to link.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   */
  public function setLinkedAccount(UserInterface $account) {
    $this->account = $account;
  }

  /**
   * Gets the SAML attributes.
   *
   * @return array
   *   The SAML attributes received from the IdP.
   */
  public function getAttributes() {
    return $this->attributes;
  }

}
