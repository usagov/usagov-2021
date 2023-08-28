<?php

namespace Drupal\samlauth\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * Wraps a samlauth user sync event for event listeners.
 */
class SamlauthUserSyncEvent extends Event {

  /**
   * The Drupal user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * An indicator of whether the user account is newly registered/linked.
   *
   * @var bool
   */
  protected $firstSamlLogin;

  /**
   * The SAML attributes received from the IdP.
   *
   * Single values are typically represented as one-element arrays.
   *
   * @var array
   */
  protected $attributes;

  /**
   * An indicator of whether the account was changed.
   *
   * @var bool
   */
  protected $accountChanged;

  /**
   * Constructs a samlauth user sync event object.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   * @param array $attributes
   *   The SAML attributes received from the IdP.
   * @param bool $first_saml_login
   *   An indicator of whether the account is newly registered/linked.
   */
  public function __construct(UserInterface $account, array $attributes, $first_saml_login = FALSE) {
    $this->account = $account;
    $this->attributes = $attributes;
    $this->firstSamlLogin = $first_saml_login;
  }

  /**
   * Gets the Drupal user entity.
   *
   * @return \Drupal\user\UserInterface
   *   The Drupal user account.
   */
  public function getAccount() {
    return $this->account;
  }

  /**
   * Sets the altered Drupal user entity.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   */
  public function setAccount(UserInterface $account) {
    $this->account = $account;
  }

  /**
   * Indicates if the SAML login is happening for the first time.
   *
   * This can mean that the user account is new, but also that an existing
   * Drupal user (which may have logged in through other means) was just linked
   * to the SAML provider. Note this is not an indicator of whether the Drupal
   * user logged in for the first time.
   *
   * There is no guarantee that the user or the link is already saved.
   * (Specifically: if the user is being newly registered, it is not saved
   * yet in practice. If an existing user was just linked, the authmap entry
   * currently is likely already saved in the Drupal database but a future
   * release will likely save the entry only after this event is dispatched.)
   *
   * @return bool
   *   TRUE if the SAML login is happening for the first time.
   */
  public function isFirstLogin() {
    // For backward compatibility with code other than the samlauth module that
    // decided to dispatch this event for some reason and is not passing
    // $first_login yet, we'll also check if the account is new.
    return $this->firstSamlLogin || $this->account->isNew();
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

  /**
   * Sets the SAML attributes.
   *
   * Event handlers typically will use only the getter, to inspect attributes
   * that are present in the SAML message. This setter provides a way to
   * override those values in edge cases but is not meant to be used often. It
   * provides no nice DX; the caller needs to make sure that all attributes are
   * set at once (probably by first using getAttributes() and changing the
   * appropriate values). The caller also needs to make sure that values are
   * structured as other event subscribers expect them (which is likely to be
   * single-value arrays).
   *
   * @param array $attributes
   *   An array containing SAML attributes.
   */
  public function setAttributes(array $attributes) {
    $this->attributes = $attributes;
  }

  /**
   * Marks the user account as changed.
   *
   * This is the way for event subscribers to make sure the user account gets
   * saved. This method exists because subscribers must never save new accounts
   * by themselves. (Non-new accounts could be saved by the event subscribers
   * but just calling markAccountChanged() will keep the account from being
   * saved multiple times by multiple subscribers.)
   */
  public function markAccountChanged() {
    $this->accountChanged = TRUE;
  }

  /**
   * Indicates if the user account was marked as changed.
   *
   * This is typically done afterwards by the code that dispatches this.
   *
   * @return bool
   *   TRUE if the user account was marked as changed.
   */
  public function isAccountChanged() {
    return $this->accountChanged;
  }

}
