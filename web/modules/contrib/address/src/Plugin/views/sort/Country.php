<?php

namespace Drupal\address\Plugin\views\sort;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;

/**
 * Sort handler for sorting by either country code or name.
 *
 * Allows sorting by name, since country codes don't necessarily reflect the
 * first characters of the country name, especially if translations are used.
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort("country")
 */
class Country extends SortPluginBase {

  /**
   * Sort by country name.
   */
  const COUNTRY_NAME = 'name';

  /**
   * Sort by country code.
   */
  const COUNTRY_CODE = 'code';

  /**
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected $countryRepository;

  /**
   * The current language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * Constructs a new Country object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The id of the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CountryRepositoryInterface $country_repository, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->countryRepository = $country_repository;
    $this->langcode = $language_manager->getCurrentLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('address.country_repository'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['sort_by'] = ['default' => self::COUNTRY_NAME];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->options['sort_by'] === self::COUNTRY_NAME) {
      $this->ensureMyTable();

      // Map country codes to a sorting key using WHEN ... THEN clauses.
      $country_list = $this->countryRepository->getList($this->langcode);
      $field_name = $this->tableAlias . '.' . $this->realField;
      $when = [];
      $i = 0;
      foreach (array_keys($country_list) as $country_code) {
        // Use only the country codes which are in the expected format.
        if (strlen($country_code) == 2) {
          $when[] = "WHEN $field_name = '$country_code' THEN " . $i++;
        }
      }
      $this->query->addField(NULL, 'CASE ' . implode(' ', $when) . ' END', 'address_sort_country_name');
      $this->query->addOrderBy(NULL, NULL, $this->options['order'], 'address_sort_country_name');
    }
    else {
      parent::query();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['sort_by'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sort by'),
      '#options' => [
        self::COUNTRY_NAME => $this->t('Country name'),
        self::COUNTRY_CODE => $this->t('Country code'),
      ],
      '#default_value' => $this->options['sort_by'],
      '#weight' => -0.5,
    ];
  }

}
