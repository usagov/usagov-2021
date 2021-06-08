<?php

namespace Drupal\siteimprove;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\siteimprove\Plugin\SiteimproveDomainManager;
use GuzzleHttp\Client;

/**
 * Utility functions for Siteimprove.
 */
class SiteimproveUtils {

  use StringTranslationTrait;

  const TOKEN_REQUEST_URL = 'https://my2.siteimprove.com/auth/token';

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Drupal Configuration storage service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * HTTP Client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Drupal RouteMatch service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Drupal PatchMatcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Siteimprove Domain Manager.
   *
   * @var \Drupal\siteimprove\Plugin\SiteimproveDomainManager
   */
  protected $siteimproveDomainManager;

  /**
   * Drupal logging service.
   *
   * Using the 'Siteimprove' channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * SiteimproveUtils constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal Configuration storage service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \GuzzleHttp\Client $http_client
   *   HTTP Client.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Drupal RouteMatch service.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   Drupal PatchMatcher service.
   * @param \Drupal\siteimprove\Plugin\SiteimproveDomainManager $siteimproveDomainManager
   *   Siteimprove Domain Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, Client $http_client, RouteMatchInterface $routeMatch, PathMatcherInterface $pathMatcher, SiteimproveDomainManager $siteimproveDomainManager, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->httpClient = $http_client;
    $this->routeMatch = $routeMatch;
    $this->pathMatcher = $pathMatcher;
    $this->siteimproveDomainManager = $siteimproveDomainManager;
    $this->logger = $loggerChannelFactory->get('Siteimprove');
  }

  /**
   * Return Siteimprove token.
   */
  public function requestToken() {

    try {
      // Request new token.
      $response = $this->httpClient->get(self::getTokenRequestUrl(),
        ['headers' => ['Accept' => 'application/json']]);

      $data = (string) $response->getBody();
      if (!empty($data)) {
        $json = json_decode($data);
        if (!empty($json->token)) {
          return $json->token;
        }
        else {
          throw new \Exception();
        }
      }
      else {
        throw new \Exception();
      }
    }
    catch (\Exception $e) {
      $this->logger->log(RfcLogLevel::ERROR, 'There was an error requesting a new token. %type: @message in %function (line %line of %file).', Error::decodeException($e));
    }

    return FALSE;
  }

   /**
    * Prepare the token request URL.
    *
    * @return string
    *   The prepared token request URL.
    */
   public static function getTokenRequestUrl() {
     return self::TOKEN_REQUEST_URL . '?cms=Drupal-' . \Drupal::VERSION;
   }

  /**
   * Return Siteimprove js library.
   *
   * @return string
   *   Siteimprove js library.
   */
  public function getSiteimproveOverlayLibrary() {
    return 'siteimprove/siteimprove.overlay';
  }

  /**
   * Return siteimprove js library.
   */
  public function getSiteimproveLibrary() {
    return 'siteimprove/siteimprove';
  }

  /**
   * Return siteimprove js settings.
   *
   * @param array $url
   *   Urls to input or recheck.
   * @param string $type
   *   Action: recheck_url|input_url.
   * @param bool $auto
   *   Automatic calling to the defined method.
   *
   * @return array
   *   JS settings.
   */
  public function getSiteimproveSettings(array $url, $type, $auto = TRUE) {
    return [
      'url' => $url,
      'auto' => $auto,
    ];
  }

  /**
   * Return siteimprove token.
   *
   * @return array|mixed|null
   *   Siteimprove Token.
   */
  public function getSiteimproveToken() {
    return $this->configFactory->get('siteimprove.settings')->get('token');
  }

  /**
   * Save URL in session.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Node or taxonomy term entity object.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function setSessionUrl(?EntityInterface $entity) {
    // Check if user has access.
    if ($this->currentUser->hasPermission('use siteimprove')) {
      $urls = $this->getEntityUrls($entity);

      // Save friendly url in SESSION.
      foreach ($urls as $url) {
        $_SESSION['siteimprove_url'][] = $url;
      }
    }
  }

  /**
   * Return frontend urls for given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Entity to get frontend urls for.
   *
   * @return array
   *   Returns an array of frontend urls for entity.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getEntityUrls(?EntityInterface $entity) {

    if (is_null($entity) || !$entity->hasLinkTemplate('canonical')) {
      return [];
    }

    $domains = $this->getEntityDomains($entity);

    /** @var \Drupal\Core\Entity\Entity $entity */
    $url_relative = $entity->toUrl('canonical', ['absolute' => FALSE])->toString(TRUE);
    $urls = [];

    // Create urls for active frontend urls for the entity.
    foreach ($domains as $domain) {
      $urls[] = $domain . $url_relative->getGeneratedUrl();
    }

    $frontpage = $this->configFactory->get('system.site')->get('page.front');
    $current_route_name = $this->routeMatch->getRouteName();
    $node_route = in_array($current_route_name, [
      'entity.node.edit_form',
      'entity.node.latest_version',
    ]);
    $taxonomy_route = in_array($current_route_name, [
      'entity.taxonomy_term.edit_form',
      'entity.taxonomy_term.latest_version',
    ]);

    // If entity is frontpage, add base url to domains.
    if (($node_route && '/node/' . $entity->id() === $frontpage)
      || ($taxonomy_route && '/taxonomy/term/' . $entity->id() === $frontpage)
      || $this->pathMatcher->isFrontPage()
    ) {
      $front = Url::fromRoute('<front>')->toString(TRUE);
      foreach ($domains as $domain) {
        $urls[] = $domain . $front->getGeneratedUrl();
      }
    }

    return $urls;

  }

  /**
   * Get active domain names for entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to get domain names for.
   *
   * @return array
   *   Array of domain names without trailing slash.
   */
  public function getEntityDomains(EntityInterface $entity) {
    // Get the active frontend domain plugin.
    $config = $this->configFactory->get('siteimprove.settings');
    /** @var \Drupal\siteimprove\Plugin\SiteimproveDomainBase $plugin */
    $plugin = $this->siteimproveDomainManager->createInstance($config->get('domain_plugin_id'));

    // Get active domains.
    return $plugin->getUrls($entity);
  }

}
