<?php

namespace Drupal\samlauth;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\externalauth\Authmap;
use Drupal\externalauth\ExternalAuth;
use Drupal\key\KeyRepositoryInterface;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserLinkEvent;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\UserInterface;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error as SamlError;
use OneLogin\Saml2\Utils as SamlUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Governs communication between the SAML toolkit and the IdP / login behavior.
 *
 * There's no formal interface here, only a promise to not change things in
 * breaking ways in the 3.x releases. The division in responsibilities between
 * this class and SamlController (which calls most of its public methods) is
 * partly arbitrary. It's roughly "Controller contains code dealing with
 * redirects; SamlService contains the other logic". Code will likely be moved
 * around to new classes in 4.x.
 */
class SamlService {
  use StringTranslationTrait;

  /**
   * Auth objects (usually 0 or 1) representing the current request state.
   *
   * @var \OneLogin\Saml2\Auth[]
   */
  protected $samlAuth;

  /**
   * The ExternalAuth service.
   *
   * @var \Drupal\externalauth\ExternalAuth
   */
  protected $externalAuth;

  /**
   * The Authmap service.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $authmap;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Private store for SAML session data.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $privateTempStore;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Key repository service.
   *
   * This is set when the key module is installed. (Not when the key_asymmetric
   * module is installed. The latter is necessary for entering public/private
   * keys but reading them will work fine without it, it seems.)
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * Constructs a new SamlService.
   *
   * @param \Drupal\externalauth\ExternalAuth $external_auth
   *   The ExternalAuth service.
   * @param \Drupal\externalauth\Authmap $authmap
   *   The Authmap service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   A temp data store factory object.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(ExternalAuth $external_auth, Authmap $authmap, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, EventDispatcherInterface $event_dispatcher, RequestStack $request_stack, PrivateTempStoreFactory $temp_store_factory, FloodInterface $flood, AccountInterface $current_user, MessengerInterface $messenger, TranslationInterface $translation) {
    $this->externalAuth = $external_auth;
    $this->authmap = $authmap;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->requestStack = $request_stack;
    $this->privateTempStore = $temp_store_factory->get('samlauth');
    $this->flood = $flood;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->setStringTranslation($translation);

    $config = $this->configFactory->get('samlauth.authentication');
    // setProxyVars lets the SAML PHP Toolkit use 'X-Forwarded-*' HTTP headers
    // for identifying the SP URL, but we should pass the Drupal/Symfony base
    // URL to into the toolkit instead. That uses headers/trusted values in the
    // same way as the rest of Drupal (as configured in settings.php).
    // @todo remove this in v4.x
    if ($config->get('use_proxy_headers') && !$config->get('use_base_url')) {
      // Use 'X-Forwarded-*' HTTP headers for identifying the SP URL.
      SamlUtils::setProxyVars(TRUE);
    }
  }

  /**
   * Set the Key repository service.
   *
   * This has a separate setter (unlike all other dependent objects mentioned
   * in the constructor) because it's an optional dependency.
   *
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   the Key repository service.
   */
  public function setKeyRepository(KeyRepositoryInterface $key_repository) {
    $this->keyRepository = $key_repository;
  }

  /**
   * Show metadata about the local sp. Use this to configure your saml2 IdP.
   *
   * @param int|null $validity
   *   (Optional) 'validUntil' property of the metadata (which is a date, not
   *   an interval) will be this many seconds into the future. If left empty,
   *   the SAML PHP Toolkit will assign a value.
   * @param int|null $cache_duration
   *   (Optional) number of seconds used for the 'cacheDuration' property of
   *   the metadata. If left empty, the SAML PHP Toolkit will assign a value.
   *
   * @return mixed
   *   XML string representing metadata.
   *
   * @throws \OneLogin\Saml2\Error
   *   If the metatdad is invalid.
   */
  public function getMetadata($validity = NULL, $cache_duration = NULL) {
    // It's actually strange how we need to instantiate an Auth object when
    // we only need the Settings object. We may refactor that when refactoring
    // getSamlAuth().
    $settings = $this->getSamlAuth('metadata')->getSettings();
    $metadata = $settings->getSPMetadata(FALSE, $validity, $cache_duration);
    $errors = $settings->validateMetadata($metadata);

    if (empty($errors)) {
      return $metadata;
    }
    else {
      throw new SamlError('Invalid SP metadata: ' . implode(', ', $errors), SamlError::METADATA_SP_INVALID);
    }
  }

  /**
   * Initiates a SAML2 authentication flow and redirects to the IdP.
   *
   * @param string $return_to
   *   (optional) The path to return the user to after successful processing by
   *   the IdP.
   * @param array $parameters
   *   (optional) Extra query parameters to add to the returned redirect URL.
   *
   * @return string
   *   The URL of the single sign-on service to redirect to, including query
   *   parameters.
   */
  public function login($return_to = NULL, array $parameters = []) {
    $config = $this->configFactory->get('samlauth.authentication');
    $url = $this->getSamlAuth('login')->login($return_to, $parameters, FALSE, FALSE, TRUE, $config->get('request_set_name_id_policy') ?? TRUE);
    if ($config->get('debug_log_saml_out')) {
      $this->logger->debug('Sending SAML authentication request: <pre>@message</pre>', ['@message' => $this->getSamlAuth('login')->getLastRequestXML()]);
    }
    return $url;
  }

  /**
   * Processes a SAML response (Assertion Consumer Service).
   *
   * First checks whether the SAML request is OK, then takes action on the
   * Drupal user (logs in / maps existing / create new) depending on attributes
   * sent in the request and our module configuration.
   *
   * @return bool
   *   TRUE if the response was correctly processed; FALSE if an error was
   *   encountered while processing but there's a currently logged-in user and
   *   we decided not to throw an exception for this case.
   *
   * @throws \Exception
   */
  public function acs() {
    $config = $this->configFactory->get('samlauth.authentication');
    if ($config->get('debug_log_in')) {
      if (isset($_POST['SAMLResponse'])) {
        $response = base64_decode($_POST['SAMLResponse']);
        if ($response) {
          $this->logger->debug("ACS received 'SAMLResponse' in POST request (base64 decoded): <pre>@message</pre>", ['@message' => $response]);
        }
        else {
          $this->logger->warning("ACS received 'SAMLResponse' in POST request which could not be base64 decoded: <pre>@message</pre>", ['@message' => $_POST['SAMLResponse']]);
        }
      }
      else {
        // Not sure if we should be more detailed...
        $this->logger->warning("HTTP request to ACS is not a POST request, or contains no 'SAMLResponse' parameter.");
      }
    }

    // Perform flood control. This is not to guard against failed login
    // attempts per se; that is the IdP's job. It's just protection against
    // a flood of bogus (DDoS-like) requests because this route performs
    // computationally expensive operations. So: just IP based flood control,
    // using the limit / window values that Core uses for regular login.
    $flood_config = $this->configFactory->get('user.flood');
    if (!$this->flood->isAllowed('samlauth.failed_login_ip', $flood_config->get('ip_limit'), $flood_config->get('ip_window'))) {
      throw new TooManyRequestsHttpException(NULL, 'Access is blocked because of IP based flood prevention.');
    }

    // Process the ACS response message and check if we can derive a linked
    // account, but don't process errors yet. (The following code is a kludge
    // because we may need the linked account / may ignore errors later.)
    try {
      $this->processLoginResponse();
    }
    catch (\Exception $acs_exception) {
    }
    if (!isset($acs_exception)) {
      $unique_id = $this->getAttributeByConfig('unique_id_attribute');
      if ($unique_id) {
        $account = $this->externalAuth->load($unique_id, 'samlauth') ?: NULL;
      }
    }

    $logout_different_user = $config->get('logout_different_user');
    if ($this->currentUser->isAuthenticated()) {
      // Either redirect or log out so that we can log a different user in.
      // 'Redirecting' is done by the caller - so we can just return from here.
      if (isset($account) && $account->id() === $this->currentUser->id()) {
        // Noting that we were already logged in probably isn't useful. (Core's
        // user/reset link isn't a good case to compare: it always logs the
        // user out and presents the "Reset password" form with a login button.
        // 'drush uli' links, at least on D7, display an info message "please
        // reset your password" because they land on the user edit form.)
        return !isset($acs_exception);
      }
      if (!$logout_different_user) {
        // Message similar to when a user/reset link is followed.
        $this->messenger->addWarning($this->t('Another user (%other_user) is already logged into the site on this computer, but you tried to log in as user %login_user through an external authentication provider. Please <a href=":logout">log out</a> and try again.', [
          '%other_user' => $this->currentUser->getAccountName(),
          '%login_user' => $account ? $account->getAccountName() : '?',
          // Point to /user/logout rather than /saml/logout because we don't
          // want to make people log out from all their logged-in sites.
          ':logout' => Url::fromRoute('user.logout')->toString(),
        ]));
        return !isset($acs_exception);
      }
      // If the SAML response indicates (/ if the processing generated) an
      // error, we don't want to log the current user out but we want to
      // clearly indicate that someone else is still logged in.
      if (isset($acs_exception)) {
        $this->messenger->addWarning($this->t('Another user (%other_user) is already logged into the site on this computer. You tried to log in through an external authentication provider, which failed, so the user is still logged in.', [
          '%other_user' => $this->currentUser->getAccountName(),
        ]));
      }
      else {
        $this->drupalLogoutHelper();
        $this->messenger->addStatus($this->t('Another user (%other_user) was already logged into the site on this computer, and has now been logged out.', [
          '%other_user' => $this->currentUser->getAccountName(),
        ]));
      }
    }

    if (isset($acs_exception)) {
      $this->flood->register('samlauth.failed_login_ip', $flood_config->get('ip_window'));
      throw $acs_exception;
    }
    if (!$unique_id) {
      throw new \RuntimeException('Configured unique ID is not present in SAML response.');
    }

    $this->doLogin($unique_id, $account);

    // Remember SAML session values that may be necessary for logout.
    $auth = $this->getSamlAuth('acs');
    $values = [
      'session_index' => $auth->getSessionIndex(),
      'session_expiration' => $auth->getSessionExpiration(),
      'name_id' => $auth->getNameId(),
      'name_id_format' => $auth->getNameIdFormat(),
    ];
    foreach ($values as $key => $value) {
      if (isset($value)) {
        $this->privateTempStore->set($key, $value);
      }
      else {
        $this->privateTempStore->delete($key);
      }
    }

    return TRUE;
  }

  /**
   * Processes a SAML authentication response; throws an exception if invalid.
   *
   * The mechanics of checking whether there are any errors are not so
   * straightforward, so this helper function hopes to abstract that away.
   *
   * @todo should we also check a Response against the ID of the request we
   *   sent earlier? Seems to be not absolutely required on top of the validity
   *   / signature checks which the library already does - but every extra
   *   check is good. Maybe make it optional.
   */
  protected function processLoginResponse() {
    $config = $this->configFactory->get('samlauth.authentication');
    $auth = $this->getSamlAuth('acs');
    // This call can throw various kinds of exceptions if the 'SAMLResponse'
    // request parameter is not present or cannot be decoded into a valid SAML
    // (XML) message, and can also set error conditions instead - if the XML
    // contains data that is not considered valid. We should likely treat all
    // error conditions the same.
    $auth->processResponse();
    if ($config->get('debug_log_saml_in')) {
      $this->logger->debug('ACS received SAML response: <pre>@message</pre>', ['@message' => $auth->getLastResponseXML()]);
    }
    $errors = $auth->getErrors();
    if ($errors) {
      // We have one or multiple error types / short descriptions, and one
      // 'reason' for the last error.
      throw new \RuntimeException('Error(s) encountered during processing of authentication response. Type(s): ' . implode(', ', array_unique($errors)) . '; reason given for last error: ' . $auth->getLastErrorReason());
    }
    if (!$auth->isAuthenticated()) {
      // Looking at the current code, isAuthenticated() just means "response
      // is valid" because it is mutually exclusive with $errors and exceptions
      // being thrown. So we should never get here. We're just checking it in
      // case the library code changes - in which case we should reevaluate.
      throw new \RuntimeException('SAML authentication response was apparently not fully validated even when no error was provided.');
    }
  }

  /**
   * Logs a user in, creating / linking an account; synchronizes attributes.
   *
   * Split off from acs() to... have at least some kind of split.
   *
   * @param string $unique_id
   *   The unique ID (attribute value) contained in the SAML response.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The existing user account derived from the unique ID, if any.
   */
  protected function doLogin($unique_id, AccountInterface $account = NULL) {
    $config = $this->configFactory->get('samlauth.authentication');
    $first_saml_login = FALSE;
    if (!$account) {
      $this->logger->debug('No matching local users found for unique SAML ID @saml_id.', ['@saml_id' => $unique_id]);

      // Try to link an existing user: first through a custom event handler,
      // then by name, then by email.
      if ($config->get('map_users')) {
        $event = new SamlauthUserLinkEvent($this->getAttributes());
        $this->eventDispatcher->dispatch(SamlauthEvents::USER_LINK, $event);
        $account = $event->getLinkedAccount();
        if ($account) {
          $this->logger->info('Existing user @name (@uid) was newly matched to SAML login attributes; linking user and logging in.', [
            '@name' => $account->getAccountName(),
            '@uid' => $account->id(),
          ]);
        }
      }
      // Linking by name / email: we also select accounts if they are blocked
      // (and throw an exception later on) because 1) we don't want the
      // selection to be dependent on the current account's state; 2) name and
      // email are unique and would otherwise lead to another error while
      // trying to create a new account with duplicate values.
      if (!$account) {
        $name = $this->getAttributeByConfig('user_name_attribute');
        if ($name && $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $name])) {
          $account = current($account_search);
          if ($config->get('map_users_name')) {
            $this->logger->info('SAML login for name @name (as provided in a SAML attribute) matches existing Drupal account @uid; linking account and logging in.', [
              '@name' => $name,
              '@uid' => $account->id(),
            ]);
          }
          else {
            // We're not configured to link the account by name, but we still
            // looked it up by name so we can give a better error message than
            // the one caused by trying to save a new account with a duplicate
            // name, later.
            $this->logger->warning('Denying login: SAML login for unique ID @saml_id matches existing Drupal account name @name and we are not configured to automatically link accounts.', [
              '@saml_id' => $unique_id,
              '@name' => $account->getAccountName(),
            ]);
            throw new UserVisibleException('A local user account with your login name already exists, and we are disallowed from linking it.');
          }
        }
      }
      if (!$account) {
        $mail = $this->getAttributeByConfig('user_mail_attribute');
        if ($mail && $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $mail])) {
          $account = current($account_search);
          if ($config->get('map_users_mail')) {
            $this->logger->info('SAML login for email @mail (as provided in a SAML attribute) matches existing Drupal account @uid; linking account and logging in.', [
              '@mail' => $mail,
              '@uid' => $account->id(),
            ]);
          }
          else {
            // Treat duplicate email same as duplicate name above.
            $this->logger->warning('Denying login: SAML login for unique ID @saml_id matches existing Drupal account email @mail and we are not configured to automatically link the account.', [
              '@saml_id' => $unique_id,
              '@mail' => $account->getEmail(),
            ]);
            throw new UserVisibleException('A local user account with your login email address name already exists, and we are disallowed from linking it.');
          }
        }
      }

      if ($account) {
        $this->linkExistingAccount($unique_id, $account);
        $first_saml_login = TRUE;
      }
    }

    // If we haven't found an account to link, create one from the SAML
    // attributes.
    if (!$account) {
      if ($config->get('create_users')) {
        // The register() call will save the account. We want to:
        // - add values from the SAML response into the user account;
        // - not save the account twice (because if the second save fails we do
        //   not want to end up with a user account in an undetermined state);
        // - reuse code (i.e. call synchronizeUserAttributes() with its current
        //   signature, which is also done when an existing user logs in).
        // Because of the third point, we are not passing the necessary SAML
        // attributes into register()'s $account_data parameter, but we want to
        // hook into the save operation of the user account object that is
        // created by register(). It seems we can only do this by implementing
        // hook_user_presave() - which calls our synchronizeUserAttributes().
        $account_data = ['name' => $this->getAttributeByConfig('user_name_attribute')];
        $account = $this->externalAuth->register($unique_id, 'samlauth', $account_data);

        $this->externalAuth->userLoginFinalize($account, $unique_id, 'samlauth');
      }
      else {
        throw new UserVisibleException('No existing user account matches the SAML ID provided. This authentication service is not configured to create new accounts.');
      }
    }
    elseif ($account->isBlocked()) {
      throw new UserVisibleException('Requested account is blocked.');
    }
    else {
      // Synchronize the user account with SAML attributes if needed.
      $this->synchronizeUserAttributes($account, FALSE, $first_saml_login);

      $this->externalAuth->userLoginFinalize($account, $unique_id, 'samlauth');
    }
  }

  /**
   * Link a pre-existing Drupal user to a given authname.
   *
   * @param string $unique_id
   *   The unique ID (attribute value) contained in the SAML response.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The existing user account derived from the unique ID, if any.
   *
   * @throws \Drupal\samlauth\UserVisibleException
   *   If linking fails or is denied.
   */
  protected function linkExistingAccount($unique_id, UserInterface $account) {
    $allowed_roles = $this->configFactory->get('samlauth.authentication')->get('map_users_roles');
    $disallowed_roles = array_diff($account->getRoles(), $allowed_roles, [AccountInterface::AUTHENTICATED_ROLE]);
    if ($disallowed_roles) {
      $this->logger->warning('Denying login: SAML login for unique ID @saml_id matches existing Drupal account @uid which we are not allowed to link because it has roles @roles.', [
        '@saml_id' => $unique_id,
        '@uid' => $account->id(),
        '@roles' => implode(', ', $disallowed_roles),
      ]);
      throw new UserVisibleException('A local user account matching your login already exists, and we are disallowed from linking it.');
    }
    $this->externalAuth->linkExistingAccount($unique_id, 'samlauth', $account);

    // linkExistingAccount() does not tell us whether the link was actually
    // successful; it silently continues if the account was already linked
    // to a different unique ID. This would mean a user who has the power
    // to change their user name / email on the IdP side, potentially has
    // the power to log into different accounts (as long as they only log
    // into accounts that already are linked to a different IdP user).
    $linked_id = $this->authmap->get($account->id(), 'samlauth');
    if ($linked_id != $unique_id) {
      $this->logger->warning('Denying login: existing Drupal account @uid matches SAML login for unique ID @saml_id, but the account is already linked to SAML login ID @linked_id. If a new account should be created despite the earlier match, temporarily turn off matching. If this login should be linked to user @uid, remove the earlier link.', [
        '@uid' => $account->id(),
        '@saml_id' => $unique_id,
        '@linked_id' => $linked_id,
      ]);
      throw new UserVisibleException('Your login data match an earlier login by a different SAML user.');
    }
  }

  /**
   * Initiates a SAML2 logout flow and redirects to the IdP.
   *
   * @param string $return_to
   *   (optional) The path to return the user to after successful processing by
   *   the IdP.
   * @param array $parameters
   *   (optional) Extra query parameters to add to the returned redirect URL.
   *
   * @return string
   *   The URL of the single logout service to redirect to, including query
   *   parameters.
   */
  public function logout($return_to = NULL, array $parameters = []) {
    // Log the Drupal user out at the start of the process if they were still
    // logged in. Official SAML documentation usually specifies (as far as it
    // does) that we should log the user out after getting redirected from the
    // IdP instead, at /saml/sls. However
    // - Between calling logout() and all those redirects there is a lot that
    //   could go wrong which would then influence users' ability to log out of
    //   Drupal.
    // - There's no real downside to doing it now, either for the user or for
    //   our code (which already explicitly supports handling users who were
    //   previously logged out of Drupal).
    // - Site administrators may also want this endpoint to work for logging
    //   out non-SAML users. (Otherwise how are they going to display
    //   different login links for different users?) PLEASE NOTE however, that
    //   this is not the primary purpose of this method; it is to enable both
    //   logged-in and already-logged-out Drupal users to start a SAML logout
    //   process - i.e. to be redirected to the IdP. So a side effect is that
    //   non-SAML users are also redirected to the IdP unnecessarily. It may be
    //   possible to prevent this - but that will need to be tested carefully.
    $saml_session_data = $this->drupalLogoutHelper();

    // Start the SAML logout process. If the user was already logged out before
    // this method was called, we won't have any SAML session data so won't be
    // able to tell the IdP which session should be logging out. Even so, the
    // SAML Toolkit is able to create a generic LogoutRequest, and for at least
    // some IdPs that's enough to log the user out from the IdP if applicable
    // (because they have their own browser/cookie based session handling) and
    // return a SAMLResponse indicating success. (Maybe there's some way to
    // modify the Drupal logout process to keep the SAML session data available
    // but we won't explore that until there's a practical situation where
    // that's clearly needed.)
    // @todo should we check session expiration time before sending a logout
    //   request to the IdP? (What would an IdP do if it received an old
    //   session index? Is it better to not redirect, and throw an error on
    //   our side?)
    // @todo include nameId(SP)NameQualifier?
    $url = $this->getSamlAuth('logout')->logout(
      $return_to,
      $parameters,
      $saml_session_data['name_id'] ?? NULL,
      $saml_session_data['session_index'] ?? NULL,
      TRUE,
      $saml_session_data['name_id_format'] ?? NULL
    );
    if ($this->configFactory->get('samlauth.authentication')->get('debug_log_saml_out')) {
      $this->logger->debug('Sending SAML logout request: <pre>@message</pre>', ['@message' => $this->getSamlAuth('logout')->getLastRequestXML()]);
    }
    return $url;
  }

  /**
   * Does processing for the Single Logout Service.
   *
   * @return null|string
   *   Usually returns nothing. May return a URL to redirect to.
   */
  public function sls() {
    $config = $this->configFactory->get('samlauth.authentication');
    // We might at some point check if this code can be abstracted a bit...
    if ($config->get('debug_log_in')) {
      if (isset($_GET['SAMLResponse'])) {
        $response = base64_decode($_GET['SAMLResponse']);
        if ($response) {
          $this->logger->debug("SLS received 'SAMLResponse' in GET request (base64 decoded): <pre>@message</pre>", ['@message' => $response]);
        }
        else {
          $this->logger->warning("SLS received 'SAMLResponse' in GET request which could not be base64 decoded: <pre>@message</pre>", ['@message' => $_POST['SAMLResponse']]);
        }
      }
      elseif (isset($_GET['SAMLRequest'])) {
        $response = base64_decode($_GET['SAMLRequest']);
        if ($response) {
          $this->logger->debug("SLS received 'SAMLRequest' in GET request (base64 decoded): <pre>@message</pre>", ['@message' => $response]);
        }
        else {
          $this->logger->warning("SLS received 'SAMLRequest' in GET request which could not be base64 decoded: <pre>@message</pre>", ['@message' => $_POST['SAMLRequest']]);
        }
      }
      else {
        // Not sure if we should be more detailed...
        $this->logger->warning("HTTP request to SLS is not a GET request, or contains no 'SAMLResponse'/'SAMLRequest' parameters.");
      }
    }

    // Perform flood control; see acs().
    $flood_config = $this->configFactory->get('user.flood');
    if (!$this->flood->isAllowed('samlauth.failed_logout_ip', $flood_config->get('ip_limit'), $flood_config->get('ip_window'))) {
      throw new TooManyRequestsHttpException(NULL, 'Access is blocked because of IP based flood prevention.');
    }
    try {
      // This line means we're extracting logic previously encapsulated inside
      // the Auth class. That's slightly unfortunate but doesn't compare to all
      // other considerations still needing to be made re. refactoring logic.
      $purpose = isset($_GET['SAMLResponse']) ? 'sls-response' : 'sls-request';
      // Unlike the 'logout()' route, we only log the user out if we have a
      // valid request/response, so first have the SAML Toolkit check things.
      // Don't have it do any session actions, because nothing is needed
      // besides our own logout actions (if any). This call can either set an
      // error condition or throw a \OneLogin_Saml2_Error, depending on whether
      // we are processing a POST request; don't catch anything.
      // @todo should we check a LogoutResponse against the ID of the
      //   LogoutRequest we sent earlier? Seems to be not absolutely required on
      //   top of the validity / signature checks which the library already does
      //   - but every extra check is good. Maybe make it optional.
      $url = $this->getSamlAuth($purpose)->processSLO(TRUE, NULL, (bool) $config->get('security_logout_reuse_sigs'), NULL, TRUE);
    }
    catch (\Exception $e) {
      $this->flood->register('samlauth.failed_logout_ip', $flood_config->get('ip_window'));
      throw $e;
    }

    if ($config->get('debug_log_saml_in')) {
      // There should be no way we can get here if neither GET parameter is set;
      // if nothing gets logged, that's a bug.
      if (isset($_GET['SAMLResponse'])) {
        $this->logger->debug('SLS received SAML response: <pre>@message</pre>', ['@message' => $this->getSamlAuth($purpose)->getLastResponseXML()]);
      }
      elseif (isset($_GET['SAMLRequest'])) {
        $this->logger->debug('SLS received SAML request: <pre>@message</pre>', ['@message' => $this->getSamlAuth($purpose)->getLastRequestXML()]);
      }
    }
    // Now look if there were any errors and also throw.
    $errors = $this->getSamlAuth($purpose)->getErrors();
    if (!empty($errors)) {
      // We have one or multiple error types / short descriptions, and one
      // 'reason' for the last error.
      throw new \RuntimeException('Error(s) encountered during processing of SLS response. Type(s): ' . implode(', ', array_unique($errors)) . '; reason given for last error: ' . $this->getSamlAuth($purpose)->getLastErrorReason());
    }

    // Remove SAML session data, log the user out of Drupal, and return a
    // redirect URL if we got any. Usually,
    // - a LogoutRequest means we need to log out and redirect back to the IdP,
    //   for which the SAML Toolkit returned a URL.
    // - after a LogoutResponse we don't need to log out because we already did
    //   that at the start of the process, in logout() - but there's nothing
    //   against checking. We did not get an URL returned and our caller can
    //   decide what to do next.
    $this->drupalLogoutHelper();

    return $url;
  }

  /**
   * Synchronizes user data with attributes in the SAML request.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user to synchronize attributes into.
   * @param bool $skip_save
   *   (optional) If TRUE, skip saving the user account.
   * @param bool $first_saml_login
   *   (optional) Indicator of whether the account is newly registered/linked.
   */
  public function synchronizeUserAttributes(UserInterface $account, $skip_save = FALSE, $first_saml_login = FALSE) {
    // Dispatch a user_sync event.
    $event = new SamlauthUserSyncEvent($account, $this->getAttributes(), $first_saml_login);
    $this->eventDispatcher->dispatch(SamlauthEvents::USER_SYNC, $event);

    if (!$skip_save && $event->isAccountChanged()) {
      $account->save();
    }
  }

  /**
   * Returns all attributes in a SAML response.
   *
   * This method will return valid data after a response is processed (i.e.
   * after samlAuth->processResponse() is called).
   *
   * @return array
   *   An array with all returned SAML attributes..
   */
  public function getAttributes() {
    $attributes = $this->getSamlAuth('acs')->getAttributes();
    $friendly_attributes = $this->getSamlAuth('acs')->getAttributesWithFriendlyName();

    return $attributes + $friendly_attributes;
  }

  /**
   * Returns value from a SAML attribute whose name is configured in our module.
   *
   * This method will return valid data after a response is processed (i.e.
   * after samlAuth->processResponse() is called).
   *
   * @param string $config_key
   *   A key in the module's configuration, containing the name of a SAML
   *   attribute.
   *
   * @return mixed|null
   *   The SAML attribute value; NULL if the attribute value, or configuration
   *   key, was not found.
   */
  public function getAttributeByConfig($config_key) {
    $attribute_name = $this->configFactory->get('samlauth.authentication')->get($config_key);
    if ($attribute_name) {
      $attribute = $this->getSamlAuth('acs')->getAttribute($attribute_name);
      if (!empty($attribute[0])) {
        return $attribute[0];
      }

      $friendly_attribute = $this->getSamlAuth('acs')->getAttributeWithFriendlyName($attribute_name);
      if (!empty($friendly_attribute[0])) {
        return $friendly_attribute[0];
      }
    }
  }

  /**
   * Returns an initialized Auth class from the SAML Toolkit.
   *
   * @param string $purpose
   *   (Optional) purpose for the config: 'metadata' / 'login' / 'acs' /
   *   'logout' / 'sls-request' / 'sls-response'. Empty string means 'any', but
   *   likely shouldn't be used anywhere. (The way many callers hardcode this
   *   argument may seem strange, until you realize that _these callers_ only
   *   have one possible purpose too, in practice. This is almost sure to be
   *   refactored away in a future version.)
   */
  protected function getSamlAuth($purpose = '') {
    if (!isset($this->samlAuth[$purpose])) {
      $base_url = '';
      $config = $this->configFactory->get('samlauth.authentication');
      if (TRUE || $config->get('use_base_url')) {
        $request = $this->requestStack->getCurrentRequest();
        // The 'base url' for the SAML Toolkit is apparently 'all except the
        // last part of the endpoint URLs'. (Whoever wants a better explanation
        // can try to extract it from e.g. Utils::getSelfRoutedURLNoQuery().)
        $scheme = 'https://';
        $http_host = $request->getHttpHost();
        $base_url = $scheme . $http_host . $request->getBaseUrl() . '/saml';
        //$base_url = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/saml';
      }
      $this->logger->debug('Generate metadata: <pre>@purpose - base:@base</pre>', ['@purpose' => $purpose, '@base' => $base_url]);
      $lib_config = static::reformatConfig($config, $base_url, $purpose, $this->keyRepository);
      $this->logger->debug('Config: <pre>@config</pre>', ['@config' => json_encode($lib_config)]);
      $this->samlAuth[$purpose] = new Auth(static::reformatConfig($config, $base_url, $purpose, $this->keyRepository));
    }

    return $this->samlAuth[$purpose];
  }

  /**
   * Ensures the user is logged out from Drupal; returns SAML session data.
   *
   * @param bool $delete_saml_session_data
   *   (optional) whether to delete the SAML session data. This depends on:
   *   - how bad (privacy sensitive) it is to keep around? Answer: not.
   *   - whether we expect the data to ever be reused. That is: could a SAML
   *     logout attempt be done for the same SAML session multiple times?
   *     Answer: we don't know. Unlikely, because it is not accessible anymore
   *     after logout, so the user would need to log in to Drupal locally again
   *     before anything could be done with it.
   *
   * @return array
   *   Array of data about the 'SAML session' that we stored at login. (The
   *   SAML toolkit itself does not store any data / implement the concept of a
   *   session.)
   */
  protected function drupalLogoutHelper($delete_saml_session_data = TRUE) {
    $data = [];

    if ($this->currentUser->isAuthenticated()) {
      // Get data from our temp store which is not accessible after logout.
      // DEVELOPER NOTE: It depends on our session storage, whether we want to
      // try this for unauthenticated users too. At the moment, we are sure
      // only authenticated users have any SAML session data - and trying to
      // get() a value from our privateTempStore can unnecessarily start a new
      // PHP session for unauthenticated users.
      $keys = [
        'session_index',
        'session_expiration',
        'name_id',
        'name_id_format',
      ];
      foreach ($keys as $key) {
        $data[$key] = $this->privateTempStore->get($key);
        if ($delete_saml_session_data) {
          $this->privateTempStore->delete($key);
        }
      }

      // @todo properly inject this... after #2012976 lands.
      user_logout();
    }

    return $data;
  }

  /**
   * Returns a configuration array as used by the external library.
   *
   * Some of these arguments are just added because the method is static (which
   * will change in v4.x).
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   * @param string $base_url
   *   (Optional) base URL to set.
   * @param string $purpose
   *   (Optional) purpose for the config: 'metadata' / 'login' / 'acs' /
   *   'logout' / 'sls-request' / 'sls-response'.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   (Optional) the service's Key repository.
   *
   * @return array
   *   The library configuration array.
   */
  protected static function reformatConfig(ImmutableConfig $config, $base_url = '', $purpose = '', KeyRepositoryInterface $key_repository = NULL) {
    $sp_urls_opts = ['absolute' => TRUE];

    if ($config->get('sp_force_https')) {
      $sp_urls_opts['https'] = TRUE;
    }

    $library_config = [
      'debug' => (bool) $config->get('debug_phpsaml'),
      'sp' => [
        'entityId' => $config->get('sp_entity_id'),
        'assertionConsumerService' => [
          // See ExecuteInRenderContextTrait if curious why the long chained
          // call is necessary.
          'url' => Url::fromRoute('samlauth.saml_controller_acs', [], $sp_urls_opts)->toString(TRUE)->getGeneratedUrl(),
        ],
        'singleLogoutService' => [
          'url' => Url::fromRoute('samlauth.saml_controller_sls', [], $sp_urls_opts)->toString(TRUE)->getGeneratedUrl(),
        ],
        'NameIDFormat' => $config->get('sp_name_id_format') ?: NULL,
      ],
      'idp' => [
        'entityId' => $config->get('idp_entity_id'),
        'singleSignOnService' => [
          'url' => $config->get('idp_single_sign_on_service'),
        ],
        'singleLogoutService' => [
          'url' => $config->get('idp_single_log_out_service'),
        ],
      ],
      'security' => [
        // Used for metadata (adds a whole signature section):
        'signMetadata' => (bool) $config->get('security_metadata_sign'),
        // Used for metadata (value goes into attribute
        // AuthnRequestsSigned="true/false" of SPSSODescriptor) / login(*):
        'authnRequestsSigned' => (bool) $config->get('security_authn_requests_sign'),
        // Used for logout(*):
        'logoutRequestSigned' => (bool) $config->get('security_logout_requests_sign'),
        // Used for SLO response, sent after processing incoming SLO request(*):
        'logoutResponseSigned' => (bool) $config->get('security_logout_responses_sign'),
        // Used for logout; also influences Settings:__construct() checks for
        // presence of IDP cert:
        'nameIdEncrypted' => (bool) $config->get('security_nameid_encrypt'),
        // Used for acs:
        // TRUE by default AND must be TRUE on existing installations that
        // didn't have the setting before, so it's the first one to get a
        // default value. (If we didn't have the (bool) operator, we wouldn't
        // necessarily need the default - but leaving it out would just invite
        // a bug later on.)
        'wantNameId' => (bool) ($config->get('security_want_name_id') ?? TRUE),
        // Used for login / acs(*); indirectly influences metadata(**):
        'wantNameIdEncrypted' => (bool) $config->get('security_nameid_encrypted'),
        // Used for acs(*); indirectly influences metadata(**):
        'wantAssertionsEncrypted' => (bool) $config->get('security_assertions_encrypt'),
        // Used for metadata (value goes into attribute
        // WantAssertionsSigned="true/false" of SPSSODescriptor) / acs(*):
        'wantAssertionsSigned' => (bool) $config->get('security_assertions_signed'),
        // Used for acs / sls (processing incoming SLO responses and requests):
        'wantMessagesSigned' => (bool) $config->get('security_messages_sign'),
        // Used for login:
        'requestedAuthnContext' => (bool) $config->get('security_request_authn_context'),
        // Used for login / logout / SLO response, sent after processing
        // incoming SLO request; should be deprecated:
        'lowercaseUrlencoding' => (bool) $config->get('security_lowercase_url_encoding'),
        // Allow duplicated Attribute Names. Used for acs.
        'allowRepeatAttributeName' => (bool) $config->get('security_allow_repeat_attribute_name'),
        // (*): also influences Settings:__construct() checks for SP cert+key.
        // (**): if either of these properties is true, an extra 'encryption'
        // certificate is always included in the metadata. (With the same value
        // as the 'signing' certificate; we don't support different ones.)
      ],
      'strict' => (bool) $config->get('strict'),
    ];
    $sig_alg = $config->get('security_signature_algorithm');
    if ($sig_alg) {
      $library_config['security']['signatureAlgorithm'] = $sig_alg;
    }
    $enc_alg = $config->get('security_encryption_algorithm');
    if ($enc_alg) {
      // Not a typo; this is snake_case in the library too.
      $library_config['security']['encryption_algorithm'] = $enc_alg;
    }
    if ($base_url) {
      $library_config['baseurl'] = $base_url;
    }

    // We want to read cert/key values from whereever they are stored, only
    // when we actually need them. This may lead to us creating a custom
    // \OneLogin\Saml2\Settings child class that contains the logic of 'just in
    // time' reading key/cert values from storage. However (leaving aside the
    // fact that we can't do that in v3.x because it will break compatibility),
    // - the Auth class cannot use Settings subclasses: its constructor only
    //   accepts an array and its $_settings variable is private (so we cannot
    //   get around this by subclassing Auth).
    // - while the design of Settings supports 'just in time reading', (and the
    //   Settings class even does it, in some cases), its setup _in principle_
    //   isn't well suited for handling that. So if we end up doing this, we'll
    //   need to add good comments about e.g. getSPData() not returning
    //   complete data, format*() not working, ... (Basically, my conclusion is
    //   like my conclusion elsewhere about the Auth object: from the design of
    //   the Settings object it isn't clear whether it wants to be a value
    //   object or whether it wants to contain the logic of how to read the
    //   key/cert values. It would be nice if the code was... clearer.)
    // So for now, we are adding logic to this method that 'knows' when the key
    // / certs are used.
    $add_key = $add_cert = $add_idp_cert = TRUE;
    $add_new_cert = in_array($purpose, ['metadata', '']);
    $add_idp_encryption_cert = FALSE;
    switch ($purpose) {
      case 'metadata':
        // signMetadata / wantNameIdEncrypted are not implemented yet but that
        // doesn't mean we can't already use them here: they potentially
        // influence metadata but then we can't turn off $add_key.
        $add_key = !empty($library_config['security']['signMetadata'])
          || !empty($library_config['security']['wantNameIdEncrypted'])
          || !empty($library_config['security']['wantAssertionsEncrypted']);
        // We cannot prevent Settings::checkIdPSettings() from being called
        // while using the standard Auth class, so cannot do this:
        // $add_idp_cert = FALSE;.
        // @todo this is a good enough reason for opening a PR to amend
        //   Auth::construct() (to accept a Settings that was constructed with
        //   (, TRUE)), regardless of considerations outlined in AuthVolatile.
        break;

      case 'login':
        // We don't need a cert for operation; we just need to set it to a
        // value if certain security settings are set (which need the private
        // key), or otherwise checkSPCerts() will freak out.
        $add_cert = !empty($library_config['security']['authnRequestsSigned'])
          || !empty($library_config['security']['wantNameIdEncrypted'])
          ? 'FAKE' : FALSE;
        // We cannot prevent Settings::checkIdPSettings() from being called
        // while using the standard Auth class, so cannot do this:
        // $add_idp_cert = FALSE;.
        break;

      case 'logout':
        // We don't need a cert for operation; we just need to set it to a
        // value if certain security settings are set (which need the private
        // key), or otherwise checkSPCerts() will freak out.
        $add_cert = !empty($library_config['security']['logoutRequestSigned'])
          ? 'FAKE' : FALSE;
        // We cannot prevent Settings::checkIdPSettings() from being called
        // while using the standard Auth class, so cannot do this:
        // $add_idp_cert=!empty($library_config['security']['nameIdEncrypted']);
        // ^ This would also need the 2nd parameter to Settings::__construct()
        // to be !$add_idp_cert.
        // That just means we should generally add at least 1 IdP cert, though.
        // We don't need to add the encryption cert specifically - only if:
        $add_idp_encryption_cert = !empty($library_config['security']['nameIdEncrypted']);
        break;

      case 'acs':
        // $add_key depends on the presence of any EncryptedAssertion elements,
        // which is too bothersome for us to check (and which is a reason to
        // have the key reading inside a child Settings object instead) so
        // we'll assume it's TRUE. Same with $add_idp_cert which depends on
        // the presence of various signed elements.
        $add_cert = !empty($library_config['security']['wantAssertionsEncrypted'])
          || !empty($library_config['security']['wantAssertionsSigned'])
          || !empty($library_config['security']['wantNameIdEncrypted'])
          ? 'FAKE' : FALSE;
        break;

      case 'sls-request':
        // We need to both interpret the incoming request and probably create a
        // new outgoing one, so we need key and cert.
        // We cannot prevent Settings::checkIdPSettings() from being called
        // while using the standard Auth class, so cannot do this:
        // $add_idp_cert = isset($_GET['Signature']); // This would also need
        // the 2nd parameter to Settings::__construct() to be !$add_idp_cert.
        break;

      case 'sls-response':
        $add_key = $add_cert = FALSE;
        // We cannot prevent Settings::checkIdPSettings() from being called
        // while using the standard Auth class, so cannot do this:
        // $add_idp_cert = isset($_GET['Signature']); // This would also need
        // the 2nd parameter to Settings::__construct() to be !$add_idp_cert.
    }
    if (!$add_key || !$add_cert) {
      // If these are set, checkSPCerts() will get called, which requires key +
      // cert presence. Neither of these two variables are (should have been)
      // set to FALSE if any of these security settings is both true and
      // relevant for our $purpose. (The full logic including the switch{}
      // implies the library default is FALSE for all these security settings.)
      unset($library_config['security']['authnRequestsSigned']);
      unset($library_config['security']['logoutRequestSigned']);
      unset($library_config['security']['logoutResponseSigned']);
      unset($library_config['security']['wantAssertionsEncrypted']);
      unset($library_config['security']['wantNameIdEncrypted']);
    }
    if (!$add_idp_cert) {
      // Same for IdP cert.
      unset($library_config['security']['nameIdEncrypted']);
    }

    // Check if we want to load the certificates from a folder. Either folder
    // or cert+key settings should be defined. If both are defined, "folder" is
    // the preferred method and we ignore cert/path values; we don't do more
    // complicated validation like checking whether the cert/key files exist.
    // Initializing cert/key properties to '' (rather than not setting them at
    // all) should be fine, because the standard Settings also does that.
    $cert_folder = $config->get('sp_cert_folder');
    if ($cert_folder) {
      // Set the folder so the SAML toolkit knows where to look. This is the
      // old method which only reads key/cert when we actually need it, because
      // the logic for it is inside the Settings class. We're phasing it out in
      // favor of the hardcoded above $add_* logic and reading the key/cert
      // files inside this function, because this old method relies on a
      // specific hardcoded folder name / file names in the Settings class.
      // @todo remove in 4.x: not applicable after samlauth_update_8304().
      if (!defined('ONELOGIN_CUSTOMPATH')) {
        define('ONELOGIN_CUSTOMPATH', "$cert_folder/");
      }
    }
    else {
      if ($add_key) {
        $key = $config->get('sp_private_key');
        if (isset($key) && !is_string($key)) {
          throw new SamlError('SP private key setting is not a string.', SamlError::SETTINGS_INVALID);
        }
        $type = strstr($key, ':', TRUE);
        if ($type === 'key') {
          if ($key_repository) {
            $key = substr($key, 4);
            $key_entity = $key_repository->getKey($key);
            if (!$key_entity) {
              throw new SamlError("SP private key '$key' not found.", SamlError::SETTINGS_INVALID);
            }
            $key = $key_entity->getKeyValue();
          }
          else {
            throw new SamlError('SP private key setting is of type "key" but the Key module is not installed.', SamlError::SETTINGS_INVALID);
          }
        }
        elseif ($type === 'file') {
          $key = file_get_contents(substr($key, 5));
          if ($key === FALSE) {
            throw new SamlError('SP private key not found.', SamlError::PRIVATE_KEY_FILE_NOT_FOUND);
          }
        }
        $library_config['sp']['privateKey'] = $key;
      }
      if ($add_cert) {
        $cert = $add_cert === 'FAKE' ? 'dummy-value-to-subvert-validation' : $config->get('sp_x509_certificate');
        if (isset($cert) && !is_string($cert)) {
          throw new SamlError('SP public cert setting is not a string.', SamlError::SETTINGS_INVALID);
        }
        if ($cert) {
          $type = strstr($cert, ':', TRUE);
          if ($type === 'key') {
            if ($key_repository) {
              $cert = substr($cert, 4);
              $key_entity = $key_repository->getKey($cert);
              if (!$key_entity) {
                throw new SamlError("SP public cert '$cert' not found.", SamlError::SETTINGS_INVALID);
              }
              $cert = $key_entity->getKeyValue();
            }
            else {
              throw new SamlError('SP public cert setting is of type "key" but the Key module is not installed.', SamlError::SETTINGS_INVALID);
            }
          }
          elseif ($type === 'file') {
            $cert = file_get_contents(substr($cert, 5));
            if ($cert === FALSE) {
              throw new SamlError('SP public cert not found.', SamlError::PUBLIC_CERT_FILE_NOT_FOUND);
            }
          }
          $library_config['sp']['x509cert'] = $cert;
        }
      }
      if ($add_new_cert) {
        $cert = $config->get('sp_new_certificate');
        if (isset($cert) && !is_string($cert)) {
          throw new SamlError('SP new public cert setting is not a string.', SamlError::SETTINGS_INVALID);
        }
        if ($cert) {
          $type = strstr($cert, ':', TRUE);
          if ($type === 'key') {
            if ($key_repository) {
              $cert = substr($cert, 4);
              $key_entity = $key_repository->getKey($cert);
              if (!$key_entity) {
                throw new SamlError("SP new public cert '$cert' not found.", SamlError::SETTINGS_INVALID);
              }
              $cert = $key_entity->getKeyValue();
            }
            else {
              throw new SamlError('SP new public cert setting is of type "key" but the Key module is not installed.', SamlError::SETTINGS_INVALID);
            }
          }
          elseif ($type === 'file') {
            $cert = file_get_contents(substr($cert, 5));
            if ($cert === FALSE) {
              throw new SamlError('SP new public cert not found.', SamlError::PUBLIC_CERT_FILE_NOT_FOUND);
            }
          }
          $library_config['sp']['x509certNew'] = $cert;
        }
      }
    }

    $encryption_cert = '';
    $certs = [];
    if ($add_idp_encryption_cert) {
      $encryption_cert = $config->get('idp_cert_encryption');
      if (isset($encryption_cert) && !is_string($encryption_cert)) {
        throw new SamlError('IdP encryption cert setting is not a string.', SamlError::SETTINGS_INVALID);
      }
      $type = strstr($encryption_cert, ':', TRUE);
      if ($type === 'key') {
        if ($key_repository) {
          $encryption_cert = substr($encryption_cert, 4);
          $key_entity = $key_repository->getKey($encryption_cert);
          if (!$key_entity) {
            throw new SamlError("IdP encryption cert '$encryption_cert' not found.", SamlError::SETTINGS_INVALID);
          }
          $encryption_cert = $key_entity->getKeyValue();
        }
        else {
          throw new SamlError('IdP encryption cert setting is of type "key" but the Key module is not installed.', SamlError::SETTINGS_INVALID);
        }
      }
      elseif ($type === 'file') {
        $encryption_cert = file_get_contents(substr($encryption_cert, 5));
        if ($encryption_cert === FALSE) {
          throw new SamlError('IdP encryption cert not found.', SamlError::PRIVATE_KEY_FILE_NOT_FOUND);
        }
      }
    }
    if ($add_idp_cert || ($add_idp_encryption_cert && !$encryption_cert)) {
      $certs = $config->get('idp_certs');
      foreach ($certs as $i => $cert) {
        if (isset($certs[$i]) && !is_string($certs[$i])) {
          $nr = ($i ? " $i" : '');
          throw new SamlError("IdP cert setting$nr is not a string.", SamlError::SETTINGS_INVALID);
        }
        $type = strstr($cert, ':', TRUE);
        if ($type === 'key') {
          if ($key_repository) {
            $cert = substr($cert, 4);
            $key_entity = $key_repository->getKey($cert);
            if (!$key_entity) {
              throw new SamlError("IdP cert '$cert' not found.", SamlError::SETTINGS_INVALID);
            }
            $certs[$i] = $key_entity->getKeyValue();
          }
          else {
            throw new SamlError('IdP cert setting is of type "key" but the Key module is not installed.', SamlError::SETTINGS_INVALID);
          }
        }
        elseif ($type === 'file') {
          $certs[$i] = file_get_contents(substr($cert, 5));
          if ($certs[$i] === FALSE) {
            $nr = ($i ? " $i" : '');
            throw new SamlError("IdP cert$nr not found.", SamlError::PRIVATE_KEY_FILE_NOT_FOUND);
          }
        }
      }
    }
    // @todo remove in 4.x: not applicable after samlauth_update_8304().
    // @todo at the same time as removing this, uncomment samlauth_update_8400.
    if (!$certs && !$encryption_cert) {
      $old_cert = $config->get('idp_x509_certificate');
      $old_cert_multi = $config->get('idp_x509_certificate_multi');
      if ($old_cert || $old_cert_multi) {
        $certs = $old_cert ? [$old_cert] : [];
        if ($old_cert_multi) {
          if ($config->get('idp_cert_type') === 'encryption') {
            $encryption_cert = $old_cert_multi;
          }
          else {
            $certs[] = $old_cert_multi;
          }
        }
      }
    }
    // If we don't set a separate 'x509certMulti > encryption' cert, the
    // 'main' cert (not 'x509certMulti > signing') is used for encryption so
    // it must be set. If we have a single 'main/signing' cert, we can set it
    // in either 'x509certMulti > signing' or as the main cert - both is not
    // necessary. This can be encoded in several arbitrary ways, e.g.:
    if ($encryption_cert) {
      $library_config['idp']['x509certMulti'] = [
        // This is an array, but the library never uses anything but the
        // first value.
        'encryption' => [$encryption_cert],
        'signing' => $certs,
      ];
    }
    elseif ($certs) {
      if (count($certs) == 1 || $add_idp_encryption_cert) {
        $library_config['idp']['x509cert'] = reset($certs);
      }
      if (count($certs) > 1) {
        $library_config['idp']['x509certMulti']['signing'] = $certs;
      }
    }

    return $library_config;
  }

}
