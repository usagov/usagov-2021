<?php

namespace Drupal\samlauth\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
// @todo replace by Drupal\Component\Utility\EmailValidatorInterface in time,
//   and remove the comment in the constructor.
use Egulias\EmailValidator\EmailValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that synchronizes user properties on a user_sync event.
 *
 * This is basic module functionality, partially driven by config options. It's
 * split out into an event subscriber so that the logic is easier to tweak for
 * individual sites. (Set message or not? Completely break off login if an
 * account with the same name is found, or continue with a non-renamed account?
 * etc.)
 */
class UserSyncEventSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * A configuration object containing samlauth settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new SamlauthUserSyncSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator. Note the code defines it as
   *   \Egulias\EmailValidator\EmailValidator for the time being; reason:
   *   - The default service used to be \Egulias\EmailValidator\EmailValidator,
   *     which in v1 only had one required argument. (v2 has two.)
   *   - From core 8.7, \Drupal\Component\Utility\EmailValidatorInterface was
   *     introduced, and the service now implements that interface AND still
   *     extends \Egulias\EmailValidator\EmailValidator, but makes the 2nd
   *     argument optional (and in fact, unusable) for backward compatibility.
   *   We already typehint the interface in comments, otherwise the call to
   *   isValid() will appear to contain errors. But we don't want to mandate
   *   Core >= 8.7 just yet, so the 'use' statement is still not updated.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, TypedDataManagerInterface $typed_data_manager, EmailValidator $email_validator, LoggerInterface $logger, MessengerInterface $messenger, TranslationInterface $translation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->emailValidator = $email_validator;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->typedDataManager = $typed_data_manager;
    $this->config = $config_factory->get('samlauth.authentication');
    $this->setStringTranslation($translation);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SamlauthEvents::USER_SYNC][] = ['onUserSync'];
    return $events;
  }

  /**
   * Performs actions to synchronize users with SAML data on login.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   The event.
   */
  public function onUserSync(SamlauthUserSyncEvent $event) {
    // If the account is new, we are in the middle of a user save operation;
    // the current user name is the authname as set by externalauth, and
    // e-mail is not set yet.
    $account = $event->getAccount();
    $fatal_errors = [];

    // Synchronize username.
    // @todo in v4, can/should we get rid of most of this validation code, and
    //   just call $account->validate() afterwards? (Because that supposedly
    //   also checks for duplicate e-mail addresses etc.) This should be in
    //   'base' code, likely moved into externalauth if possible. (It's
    //   mentioned in #3132453. At the moment I think we should have an option
    //   for entity level validation, and keep field level validation in here.)
    if ($account->isNew() || $this->config->get('sync_name')) {
      // Get value from the SAML attribute whose name is configured in the
      // samlauth module.
      $name = $this->getAttributeByConfig('user_name_attribute', $event);
      if ($name && $name != $account->getAccountName()) {
        // Validate the username. This shouldn't be necessary to mitigate
        // attacks; assuming our SAML setup is correct, noone can insert fake
        // data here. It protects against SAML attribute misconfigurations.
        // Invalid names will cancel the login / account creation. The code is
        // copied from user_validate_name().
        $definition = BaseFieldDefinition::create('string')->addConstraint('UserName', []);
        $data = $this->typedDataManager->create($definition);
        $data->setValue($name);
        $violations = $data->validate();
        if ($violations) {
          foreach ($violations as $violation) {
            $fatal_errors[] = $violation->getMessage();
          }
        }

        // Check if the username is not already taken by someone else. For new
        // accounts this can happen if the 'map existing users' setting is off.
        if (!$fatal_errors) {
          $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $name]);
          $existing_account = reset($account_search);
          if (!$existing_account || $account->id() == $existing_account->id()) {
            $account->setUsername($name);
            $event->markAccountChanged();
          }
          else {
            $error = 'An account with the username @username already exists.';
            if ($account->isNew()) {
              $fatal_errors[] = $this->t($error, ['@username' => $name]);
            }
            else {
              // We continue and keep the old name. A DSM should be OK here
              // since login only happens interactively.
              $error = "Error updating user name from SAML attribute: $error";
              $this->logger->error($error, ['@username' => $name]);
              $this->messenger->addError($this->t($error, ['@username' => $name]));
            }
          }
        }
      }
    }

    // Synchronize e-mail.
    if ($this->config->get('user_mail_attribute') && ($account->isNew() || $this->config->get('sync_mail'))) {
      $mail = $this->getAttributeByConfig('user_mail_attribute', $event);
      if ($mail) {
        if ($mail != $account->getEmail()) {
          // Invalid e-mail cancels the login / account creation just like name.
          if ($this->emailValidator->isValid($mail)) {

            $account->setEmail($mail);
            if ($account->isNew()) {
              // Externalauth sets init to a non e-mail value so we will fix it.
              $account->set('init', $mail);
            }
            $event->markAccountChanged();
          }
          else {
            $fatal_errors[] = $this->t('Invalid e-mail address @mail', ['@mail' => $mail]);
          }
        }
      }
      elseif ($account->isNew() && !$account->getEmail()) {
        // We won't allow new accounts with empty e-mail. If a custom event
        // subscriber wants to populate the e-mail, then (at least for now) it
        // should be registered with a higher priority than this standard one.
        $fatal_errors[] = $this->t('Email address is not provided in SAML attribute.');
      }
    }

    if ($fatal_errors) {
      // Cancel the whole login process and/or account creation.
      throw new \RuntimeException('Error(s) encountered during SAML attribute synchronization: ' . implode(' // ', $fatal_errors));
    }
  }

  /**
   * Returns value from a SAML attribute whose name is configured in our module.
   *
   * This is suitable for single-value attributes. (Most values are.)
   *
   * @param string $config_key
   *   A key in the module's configuration, containing the name of a SAML
   *   attribute.
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   The event, which holds the attributes from the SAML response.
   *
   * @return mixed|null
   *   The SAML attribute value; NULL if the attribute value was not found.
   */
  public function getAttributeByConfig($config_key, SamlauthUserSyncEvent $event) {
    $attributes = $event->getAttributes();
    $attribute_name = $this->config->get($config_key);
    return $attribute_name && !empty($attributes[$attribute_name][0]) ? $attributes[$attribute_name][0] : NULL;
  }

}
