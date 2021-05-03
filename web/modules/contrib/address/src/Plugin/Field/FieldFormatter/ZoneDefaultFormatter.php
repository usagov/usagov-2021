<?php

namespace Drupal\address\Plugin\Field\FieldFormatter;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface;
use CommerceGuys\Addressing\Zone\Zone;
use Drupal\address\LabelHelper;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'address_zone_default' formatter.
 *
 * @FieldFormatter(
 *   id = "address_zone_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "address_zone"
 *   }
 * )
 */
class ZoneDefaultFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The address format repository.
   *
   * @var \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface
   */
  protected $addressFormatRepository;

  /**
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected $countryRepository;

  /**
   * The subdivision repository.
   *
   * @var \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface
   */
  protected $subdivisionRepository;

  /**
   * Constructs a ZoneDefaultFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface $address_format_repository
   *   The address format repository.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   * @param \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface $subdivision_repository
   *   The subdivision repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AddressFormatRepositoryInterface $address_format_repository, CountryRepositoryInterface $country_repository, SubdivisionRepositoryInterface $subdivision_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->addressFormatRepository = $address_format_repository;
    $this->countryRepository = $country_repository;
    $this->subdivisionRepository = $subdivision_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('address.address_format_repository'),
      $container->get('address.country_repository'),
      $container->get('address.subdivision_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    if (!empty($items)) {
      $elements = [
        '#type' => 'container',
        '#cache' => [
          'contexts' => [
            'languages:' . LanguageInterface::TYPE_INTERFACE,
          ],
        ],
      ];
      foreach ($items as $delta => $item) {
        $elements[$delta] = $this->viewElement($item->value, $langcode);
      }
    }

    return $elements;
  }

  /**
   * Builds a renderable array for a single zone item.
   *
   * @param \CommerceGuys\Addressing\Zone\Zone $zone
   *   The zone.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array.
   */
  protected function viewElement(Zone $zone, $langcode) {
    $countries = $this->countryRepository->getList();
    $element = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['zone'],
      ],
    ];
    if ($label = $zone->getLabel()) {
      $element['label'] = [
        '#type' => 'item',
        '#attributes' => [
          'class' => ['label'],
        ],
        '#plain_text' => $label,
      ];
    }
    foreach ($zone->getTerritories() as $index => $territory) {
      $country_code = $territory->getCountryCode();
      $address_format = $this->addressFormatRepository->get($country_code);
      $labels = LabelHelper::getFieldLabels($address_format);

      $element['territories'][$index] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['zone-territory'],
        ],
      ];
      $element['territories'][$index]['country'] = [
        '#type' => 'item',
        '#title' => $this->t('Country'),
        '#attributes' => [
          'class' => ['country'],
        ],
        '#plain_text' => $countries[$country_code],
      ];
      if ($administrative_area = $territory->getAdministrativeArea()) {
        $administrative_areas = $this->subdivisionRepository->getList([$country_code]);
        $administrative_area_name = $administrative_area;
        if (isset($administrative_areas[$administrative_area])) {
          $administrative_area_name = $administrative_areas[$administrative_area];
        }

        $element['territories'][$index]['administrative_area'] = [
          '#type' => 'item',
          '#title' => $labels[AddressField::ADMINISTRATIVE_AREA],
          '#attributes' => [
            'class' => ['administrative-area'],
          ],
          '#plain_text' => $administrative_area_name,
        ];
      }
      if ($locality = $territory->getLocality()) {
        $localities = $this->subdivisionRepository->getList([$country_code, $administrative_area]);
        $locality_name = $locality;
        if (isset($localities[$locality])) {
          $locality_name = $localities[$locality];
        }

        $element['territories'][$index]['locality'] = [
          '#type' => 'item',
          '#title' => $labels[AddressField::LOCALITY],
          '#attributes' => [
            'class' => ['locality'],
          ],
          '#plain_text' => $locality_name,
        ];
      }
      if ($dependent_locality = $territory->getDependentLocality()) {
        $dependent_localities = $this->subdivisionRepository->getList([$country_code, $administrative_area, $locality]);
        $dependent_locality_name = $dependent_locality;
        if (isset($dependent_localities[$dependent_locality])) {
          $dependent_locality_name = $dependent_localities[$dependent_locality];
        }

        $element['territories'][$index]['dependent_locality'] = [
          '#type' => 'item',
          '#title' => $labels[AddressField::DEPENDENT_LOCALITY],
          '#attributes' => [
            'class' => ['dependent-locality'],
          ],
          '#plain_text' => $dependent_locality_name,
        ];
      }
      if ($included_postal_codes = $territory->getIncludedPostalCodes()) {
        $element['territories'][$index]['included_postal_codes'] = [
          '#type' => 'item',
          '#title' => $this->t('Included postal codes'),
          '#attributes' => [
            'class' => ['included-postal-codes'],
          ],
          '#plain_text' => $included_postal_codes,
        ];
      }
      if ($excluded_postal_codes = $territory->getExcludedPostalCodes()) {
        $element['territories'][$index]['excluded_postal_codes'] = [
          '#type' => 'item',
          '#title' => $this->t('Excluded postal codes'),
          '#attributes' => [
            'class' => ['excluded-postal-codes'],
          ],
          '#plain_text' => $excluded_postal_codes,
        ];
      }
    }

    return $element;
  }

}
