<?php

namespace Drupal\address\Repository;

use CommerceGuys\Addressing\Country\CountryRepository as ExternalCountryRepository;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Defines the country repository.
 *
 * Countries are stored on disk in JSON and cached inside Drupal.
 */
class CountryRepository extends ExternalCountryRepository {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Creates a CountryRepository instance.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(CacheBackendInterface $cache, LanguageManagerInterface $language_manager) {
    parent::__construct();

    $this->cache = $cache;
    // The getCurrentLanguage() fallback is a workaround for core bug #2684873.
    $language = $language_manager->getConfigOverrideLanguage() ?: $language_manager->getCurrentLanguage();
    $this->defaultLocale = $language->getId();
  }

  /**
   * {@inheritdoc}
   */
  protected function loadDefinitions($locale) {
    if (isset($this->definitions[$locale])) {
      return $this->definitions[$locale];
    }

    $cache_key = 'address.countries.' . $locale;
    if ($cached = $this->cache->get($cache_key)) {
      $this->definitions[$locale] = $cached->data;
    }
    else {
      $filename = $this->definitionPath . $locale . '.json';
      $this->definitions[$locale] = json_decode(file_get_contents($filename), TRUE);
      $this->cache->set($cache_key, $this->definitions[$locale], CacheBackendInterface::CACHE_PERMANENT, ['countries']);
    }

    return $this->definitions[$locale];
  }

}
