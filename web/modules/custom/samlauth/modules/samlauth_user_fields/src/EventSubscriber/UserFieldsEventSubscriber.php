<?php

namespace Drupal\samlauth_user_fields\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserLinkEvent;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Drupal\samlauth\UserVisibleException;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Synchronizes SAML attributes into user fields / links new users during login.
 */
class UserFieldsEventSubscriber implements EventSubscriberInterface {

  /**
   * Name of the configuration object containing the setting used by this class.
   */
  const CONFIG_OBJECT_NAME = 'samlauth_user_fields.mappings';

  /**
   * The configuration factory service.
   *
   * We're doing $configFactory->get() all over the place to access our
   * configuration, which is actually a little more efficient than storing the
   * config object in a variable in this class.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The typed data manager service.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * UserFieldsEventSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, TypedDataManagerInterface $typed_data_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->typedDataManager = $typed_data_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SamlauthEvents::USER_LINK][] = ['onUserLink'];
    $events[SamlauthEvents::USER_SYNC][] = ['onUserSync'];
    return $events;
  }

  /**
   * Tries to link an existing user based on SAML attribute values.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserLinkEvent $event
   *   The event being dispatched.
   */
  public function onUserLink(SamlauthUserLinkEvent $event) {
    $match_expressions = $this->getMatchExpressions($event->getAttributes());
    $config = $this->configFactory->get(static::CONFIG_OBJECT_NAME);
    foreach ($match_expressions as $match_expression) {
      $query = $this->entityTypeManager->getStorage('user')->getQuery();
      if ($config->get('ignore_blocked')) {
        $query->condition('status', 1);
      }
      foreach ($match_expression as $field_name => $value) {
        $query->condition($field_name, $value);
      }
      $results = $query->accessCheck()->execute();
      // @todo we should figure out what we want to do with users that are
      //   already 'linked' in the authmap table. Maybe we want to exclude
      //   them from the query results; maybe we want to include them and
      //   (optionally) give an error if we encounter them. At this point, we
      //   include them without error. The main module will just "link" this
      //   user, which will silently fail (because of the existing link) and
      //   be repeated on the next login. This is consistent with existing
      //   behavior for name/email. I may want to wait with refining this
      //   behavior, until the behavior of ExternalAuth::linkExistingAccount()
      //   is clear and stable. (IMHO it currently is not / I think there are
      //   outstanding issues which will influence its behavior.)
      // @todo when we change that, change "existing (local|Drupal)? user" to
      //   "existing non-linked (local|Drupal)? user" in descriptions.
      $count = count($results);
      if ($count) {
        if ($count > 1) {
          $query = [];
          foreach ($match_expression as $field_name => $value) {
            $query[] = "$field_name=$value";
          }
          if ($config->get('ignore_blocked')) {
            $query[] = "status=1";
          }
          if (!$config->get('link_first_user')) {
            $this->logger->error(
              "Denying login because SAML data match is ambiguous: @count matching users (@uids) found for @query", [
                '@count' => $count,
                '@uids' => implode(',', $results),
                '@query' => implode(',', $query),
              ]);
            throw new UserVisibleException('It is unclear which user should be logged in. Please contact an administrator.');
          }
          $this->logger->notice("Selecting first of @count matching users to link (@uids) for @query", [
            '@count' => $count,
            '@uids' => implode(',', $results),
            '@query' => implode(',', $query),
          ]);
        }
        $account = $this->entityTypeManager->getStorage('user')->load(reset($results));
        if (!$account) {
          throw new \RuntimeException('Found user %uid to link on login, but it cannot be loaded.');
        }

        $event->setLinkedAccount($account);
        break;
      }
    }
  }

  /**
   * Constructs expressions that should be used for user matching attempts.
   *
   * Logs a warning if the configuration data is 'corrupt'.
   *
   * @param array $attributes
   *   The complete set of SAML attributes in the assertion. (The attributes
   *   can currently be duplicated, keyed both by their name and friendly name.)
   *
   * @return array[]
   *   Sets of field expressions to be used for matching; each set can contain
   *   one or multiple expressions and is keyed and sorted by the order given
   *   in the configuration. (The key values don't have a particular meaning;
   *   only their order does.) Individual expressions are fieldname-value pairs.
   */
  protected function getMatchExpressions(array $attributes) {
    $config = $this->configFactory->get(static::CONFIG_OBJECT_NAME);
    $mappings = $config->get('field_mappings');
    $match_fields = [];
    if (is_array($mappings)) {
      foreach ($mappings as $mapping) {
        if (isset($mapping['link_user_order'])) {
          // 'Sub fields' (":") are currently not allowed for linking. We
          // disallow them in the UI, so we hope that no 'sub field' is ever
          // configured here. But if it is... we give the generic warning below.
          // (Why they are disallowed: because I simply haven't checked yet,
          // whether the entity query logic works/can work for them.)
          if (isset($mapping['field_name'])
              && strpos($mapping['field_name'], ':') === FALSE
              && isset($mapping['attribute_name'])
          ) {
            $match_id = $mapping['link_user_order'];
            $value = $this->getAttribute($mapping['attribute_name'], $attributes);
            if (!isset($value)) {
              // Skip this match; ignore other mappings that are part of it.
              $match_fields[$match_id] = FALSE;
            }
            if (!isset($match_fields[$match_id])) {
              $match_fields[$match_id] = [$mapping['field_name'] => $value];
            }
            elseif ($match_fields[$match_id]) {
              if (isset($match_fields[$match_id][$mapping['field_name']])) {
                // The same match cannot define two attributes/values for the
                // same user field. Spam logs until configuration gets fixed.
                $this->logger->debug("Match attempt %id for linking users has multiple SAML attributes tied to the same user field, which is impossible. We'll ignore attribute %attribute.", [
                  '%id' => $match_id,
                  '%attribute' => $mapping['attribute_name'],
                ]);
              }
              else {
                $match_fields[$match_id][$mapping['field_name']] = $value;
              }
            }
          }
          else {
            $this->logger->warning('Partially invalid %name configuration value; user linking may be partially skipped.', ['%name' => 'field_mappings']);
          }
        }
      }
    }
    elseif (isset($mappings)) {
      $this->logger->warning('Invalid %name configuration value; skipping user linking.', ['%name' => 'field_mappings']);
    }
    ksort($match_fields);

    return array_filter($match_fields);
  }

  /**
   * Saves configured SAML attribute values into user fields.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   The event being dispatched.
   */
  public function onUserSync(SamlauthUserSyncEvent $event) {
    $account = $event->getAccount();
    $config = $this->configFactory->get(static::CONFIG_OBJECT_NAME);
    $mappings = $config->get('field_mappings');
    $validation_errors = [];
    if (is_array($mappings)) {
      $compound_field_values = [];
      $changed_compound_field_values = [];
      foreach ($mappings as $mapping) {
        // If the attribute name is invalid, or the field does not exist, spam
        // the logs on every login until the mapping is fixed.
        if (empty($mapping['attribute_name']) || !is_string($mapping['attribute_name'])) {
          $this->logger->warning('Invalid SAML attribute %attribute detected in mapping; the mapping must be fixed.');
        }
        elseif (empty($mapping['field_name']) || !is_string($mapping['field_name'])) {
          $this->logger->warning('Invalid user field mapped from SAML attribute %attribute; the mapping must be fixed.', ['%attribute' => $mapping['attribute_name']]);
        }
        // Skip silently if the configured attribute is not present in our
        // data or if its value is considered 'empty / not updatable'.
        $value = $this->getUpdatableAttributeValue($mapping['attribute_name'], $event->getAttributes());
        if (isset($value)) {
          $account_field_name = strstr($mapping['field_name'], ':', TRUE);
          if ($account_field_name) {
            $sub_field_name = substr($mapping['field_name'], strlen($account_field_name) + 1);
          }
          else {
            $account_field_name = $mapping['field_name'];
            $sub_field_name = '';
          }

          $field_definition = $account->getFieldDefinition($account_field_name);
          if (!$field_definition) {
            $this->logger->warning('User field %field is mapped from SAML attribute %attribute, but does not exist; the mapping must be fixed.', [
              '%field' => $mapping['field_name'],
              '%attribute' => $mapping['attribute_name'],
            ]);
          }
          elseif ($sub_field_name && $field_definition->getType() !== 'address') {
            // 'address' is the only compound field type we tested so far.
            $this->logger->warning('Unsuppoted user field type %type; skipping field mapping.', [
              '%type' => $field_definition->getType(),
            ]);
          }
          else {
            if (!$sub_field_name) {
              // Compare, validate, set single field.
              if (!$this->isInputValueEqual($value, $account->get($account_field_name)->value, $account_field_name)) {
                $valid = $this->validateAccountFieldValue($value, $account, $mapping['field_name']);
                if ($valid) {
                  $account->set($mapping['field_name'], $value);
                  $event->markAccountChanged();
                }
                else {
                  // Collect values to include below. Supposedly we have scalar
                  // values; var_export() shows their type. And identifier
                  // should include both source and destination because we can
                  // have multiple mappings defined for either.
                  $validation_errors[] = $mapping['attribute_name'] . ' (' . var_export($value, TRUE)
                    . ') > ' . $mapping['field_name'];
                }
              }
            }
            else {
              // Get/compare compound field; if it should be updated, set the
              // changed field value aside for later validation, because
              // validation needs to be done on the field as a whole, and other
              // attributes may be mapped to other sub values.
              if (!isset($compound_field_values[$account_field_name])) {
                // TypedData: this only works with multivalue fields but I
                // guess that's a given anyway. We can either get() the
                // single value (specific object) or getValue() it, in which
                // case we assume it's an array, for our purpose. In the former
                // case, I guess
                // - typedDataManager->create($field_definition, $input_value)
                //   would get us a new value if our field is NULL (which can
                //   happen)
                // - validateAccountFieldValue() likely just works if we skip
                //   the create() call when $value is an object
                // but I haven't tried that. So far we just work with arrays.
                $compound_field_values[$account_field_name] =
                  $account->get($account_field_name)->get(0)->getValue() ?? [];
              }
              if (!$this->isInputValueEqual($value, $compound_field_values[$account_field_name][$sub_field_name] ?? NULL, $mapping['field_name'])) {
                $compound_field_values[$account_field_name][$sub_field_name] = $value;
                // Just for logging if necessary:
                $changed_compound_field_values[$account_field_name][] =
                  $mapping['attribute_name'] . ' (' . var_export($value, TRUE) . ')';
              }
              // This would be a step toward working with objects - untested:
              // TypedData uncertainty: get($sub_field_name) returns StringData
              // for address subfields; get($sub_field_name)->getValue()
              // returns the string. Both would be good for our current purpose
              // provided that isInputValueEqual() could handle classes.
              // if (!$this->isInputValueEqual($value, $account_field->get($sub_field_name)->getValue(), $mapping['field_name'])) {
              // $account_field->setValue($sub_field_name, $value);
              // $compound_field_values[$account_field_name] = $account_field;.
            }
          }
        }
      }
      if ($compound_field_values) {
        foreach ($compound_field_values as $field_name => $value) {
          $valid = $this->validateAccountFieldValue($value, $account, $field_name);
          if ($valid) {
            $account->set($field_name, $value);
            $event->markAccountChanged();
          }
          else {
            $validation_errors[] = implode(' + ', $changed_compound_field_values)
              . " > $field_name";
          }
        }
      }
    }
    elseif (isset($mappings)) {
      $this->logger->warning('Invalid %name configuration value; skipping user synchronization.', ['%name' => 'field_mappings']);
    }

    if ($validation_errors) {
      // Log an extra message summarizing which values failed validation,
      // because our field validation supposedly doesn't do that. The user is
      // expected to see the correlation between the different log messages.
      $this->logger->warning('Validation errors were encountered while synchronizing SAML attributes into the user account: @values', ['@values' => implode(', ', $validation_errors)]);
    }
  }

  /**
   * Checks if a value should be updated into an existing user account field.
   *
   * Unused / deprecated in favor of getUpdatableAttributeValue(). Will likely
   * be removed in the next major version.
   *
   * @param mixed $input_value
   *   The value to (maybe) update / write into the user account field.
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   * @param string $account_field_name
   *   The field name in the user account.
   *
   * @return bool
   *   True if the account should be updated (that is: if it's different and
   *   not considered 'empty'). This does not imply the value is valid;
   *   validity should still be checked.
   */
  protected function isInputValueUpdatable($input_value, UserInterface $account, $account_field_name) {
    return $account->hasField($account_field_name)
      && !$this->isInputValueEqual($input_value, '')
      && !$this->isInputValueEqual($input_value, $account->get($account_field_name)->value, $account_field_name);
  }

  /**
   * Returns 'updatable' value from a SAML attribute; logs anything strange.
   *
   * 'Updatable' here does not necessarily mean the value will actually be
   * updated because we are not comparing it with the current destination field
   * value here. It just means the value in itself could be written into the
   * field. This standard implementation treats empty strings as "no value"
   * rather than "an empty value".
   *
   * @param string $name
   *   The name of a SAML attribute.
   * @param array $attributes
   *   The complete set of SAML attributes in the assertion. (The attributes
   *   can currently be duplicated, keyed both by their name and friendly name.)
   *
   * @return mixed|null
   *   The SAML attribute value; NULL if the attribute value was not found or
   *   should not be used for updating.
   */
  protected function getUpdatableAttributeValue($name, array $attributes) {
    $value = $this->getAttribute($name, $attributes);

    // In absence of exact detailed knowledge/trust of our input
    // value, we'll fall back to generic rules that usually work:
    // - Do not treat "" as a value - i.e. don't overwrite a field with "".
    // - Do treat some other similar values (like 0) as a value. See
    //   isInputValueEqual() for more details.
    return isset($value) && !$this->isInputValueEqual($value, '') ? $value : NULL;
  }

  /**
   * Checks if an input value is equal to a user account field value.
   *
   * This is abstracted into a separate method because the definition of
   * "equals" is not fully clear / so it's easier to override if necessary. (It
   * would be great if we could just do $input_value != $field_value but that
   * implies trust that the attribute data is properly 'typed' and does not
   * contain meaningless values.)
   *
   * @param mixed $input_value
   *   The input value.
   * @param mixed $field_value
   *   The value in a user account field.
   * @param string $account_field_name
   *   The field name in the user account.
   *
   * @return bool
   *   Indicates whether the values are considered equal.
   */
  protected function isInputValueEqual($input_value, $field_value, $account_field_name = '') {
    // This represents what is most likely for values from an unknown source:
    // - string values are equal to their numeric equivalent.
    // - NULL is equal to '', because our default assumption is that an empty
    //   string means "no value" rather than "an empty value".
    // - 0/"0"/0.00 are equal, but not equal to ''/NULL and not equal to "00"
    //   or "0x".
    return (is_scalar($input_value) || $input_value === NULL) && (is_scalar($field_value) || $field_value === NULL)
      ? (string) $input_value === (string) $field_value
      // We don't care much about the '===' below; it's just there because we
      // can't cast non-scalars to string and don't want strange input values
      // to cause PHP notices when they're cast to another type. It could
      // likely be '==' too but
      // - We don't know realistic situations where it makes a difference.
      // - Erring on the side of "inequal" seems valid - because in practice
      //   this means erring on the side of too many update calls.
      // (Also: [] == null even though [] != false. Not that we care; just
      // noting this for completeness in case this code is reused elsewhere.)
      : $input_value === $field_value;
  }

  /**
   * Validates a value as being valid to set into a certain user account field.
   *
   * This only performs validation based on the single field, so 'entity based'
   * validation (e.g. uniqueness of a value among all users) is not done. This
   * method logs validation violations.
   *
   * @param mixed $input_value
   *   The value to (maybe) update / write into the user account field.
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   * @param string $account_field_name
   *   The field name in the user account.
   *
   * @return bool
   *   True if the value validated correctly
   */
  protected function validateAccountFieldValue($input_value, UserInterface $account, $account_field_name) {
    $valid = FALSE;
    // The value can be validated by making it into a 'typed data' value that
    // contains the field definition (which supposedly contains all validation
    // constraints that could apply here).
    $field_definition = $account->getFieldDefinition($account_field_name);
    if ($field_definition) {
      $data = $this->typedDataManager->create($field_definition, $input_value);
      $violations = $data->validate();
      // Don't cancel; just log.
      foreach ($violations as $violation) {
        // We have the following options:
        // - Log just the validation message. This makes it unclear where the
        //   message comes from: it does not include the account, attribute
        //   or field name.
        // - Concatenate extra info into the validation message. This is
        //   bad for translatability of the original message.
        // - Log a second message mentioning the account and attribute name.
        //   This spams logs and isn't very clear.
        // We'll do the first, and hope that a caller will log extra info if
        // necessary, so it can choose whether or not to be 'spammy'.
        if ($violation instanceof ConstraintViolation) {
          [$message, $context] = $this->getLoggableParameters($violation);
          $this->logger->warning($message, $context);
        }
        else {
          $this->logger->debug('Validation for user field %field encountered unloggable error (which points to an internal code error).', ['%field' => $account_field_name]);
        }
      }
      $valid = !$violations->count();
    }

    return $valid;
  }

  /**
   * Extracts proper message + arguments from a violation.
   *
   * @param \Symfony\Component\Validator\ConstraintViolation $violation
   *   A violation object containing a message.
   *
   * @return array
   *   Two-element array: message + context. The message is suitable for
   *   'consumption' by a logger - specifically, Drupal's watchdog logger which
   *   wants an untranslated string + context passed.
   */
  protected function getLoggableParameters(ConstraintViolation $violation) {
    $message = $violation->getMessage();
    if ($message instanceof TranslatableMarkup && !($message instanceof PluralTranslatableMarkup)) {
      return [$message->getUntranslatedString(), $message->getArguments()];
    }
    // If this is some other kind of object, it might be
    // - A PluralTranslatableMarkup object. We can't get to the 'count'
    //   parameter, which is important to know which message (for which
    //   plurality) to extract from the message template, which contains
    //   multiple messages. (Which we'd need to do with code copied from
    //   render() - if we had the count.) Even then, this would harm
    //   translatability - because translation systems usually translate the
    //   full message at once.
    // - A FormattableMarkup object. Unfortunately this has no way to get to
    //   the separate message and arguments.
    // - Some other object, whose message + context are likely still PSR-3
    //   style; if we knew how to get to the separate arguments, we'd still
    //   need to pass them through LogMessageParser::parseMessagePlaceholders.
    // The only thing we know / can assume is, it's convertable to a simple
    // string, so we'll just log the string (which will unfortunately be
    // translated already / have its context substituted already).
    return [(string) $message, []];
  }

  /**
   * Returns value from a SAML attribute; logs anything strange.
   *
   * This is suitable for single-value attributes. For multi-value attributes,
   * we log a debug message to make clear we're dropping data (because this
   * indicates that the site owner may need to take care of getting more
   * sophisticated path mapping code).
   *
   * @param string $name
   *   The name of a SAML attribute.
   * @param array $attributes
   *   The complete set of SAML attributes in the assertion. (The attributes
   *   can currently be duplicated, keyed both by their name and friendly name.)
   *
   * @return mixed|null
   *   The SAML attribute value; NULL if the attribute value was not found.
   */
  protected function getAttribute($name, array $attributes) {
    $value = NULL;
    if (isset($attributes[$name])) {
      // Log everything unexpected about the format of the attributes. Use
      // debug() because we're not sure if the site owner would be able to fix
      // things.
      if (!is_array($attributes[$name])) {
        $this->logger->debug('SAML attribute %name has a non-array value; this points to a coding error somewhere (since the SAML standard seems to mandate this).', ['%name' => $name]);
      }
      elseif ($attributes[$name]) {
        if (count($attributes[$name]) > 1) {
          $this->logger->debug('SAML attribute %name has multiple values; we only support using the first one: @values.', [
            '%name' => $name,
            '@values' => function_exists('json_encode') ? json_encode($attributes[$name]) : var_export($attributes[$name], TRUE),
          ]);
        }
        if (!isset($attributes[$name][0])) {
          $value = reset($attributes[$name]);
          $this->logger->debug("SAML attribute %name's one-element array value has non-zero key %key, which points to a coding error somewhere; even though we are using the value, we're not sure if that's right.", ['%name' => $name]);
        }
        else {
          $value = $attributes[$name][0];
        }
      }
    }
    return $value;
  }

}
