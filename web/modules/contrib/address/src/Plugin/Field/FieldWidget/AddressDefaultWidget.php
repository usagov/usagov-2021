<?php

namespace Drupal\address\Plugin\Field\FieldWidget;

use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'address_default' widget.
 *
 * @FieldWidget(
 *   id = "address_default",
 *   label = @Translation("Address"),
 *   field_types = {
 *     "address"
 *   },
 * )
 */
class AddressDefaultWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected $countryRepository;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a AddressDefaultWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, CountryRepositoryInterface $country_repository, EventDispatcherInterface $event_dispatcher, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->countryRepository = $country_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @see \Drupal\Core\Field\WidgetPluginManager::createInstance().
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('address.country_repository'),
      $container->get('event_dispatcher'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];
    $value = $item->toArray();
    // Calling initializeLangcode() every time, and not just when the field
    // is empty, ensures that the langcode can be changed on subsequent
    // edits (because the entity or interface language changed, for example).
    $value['langcode'] = $item->initializeLangcode();

    $element += [
      '#type' => 'details',
      '#open' => TRUE,
    ];
    $element['address'] = [
      '#type' => 'address',
      '#default_value' => $value,
      '#required' => $this->fieldDefinition->isRequired(),
      '#available_countries' => $item->getAvailableCountries(),
      '#field_overrides' => $item->getFieldOverrides(),
    ];
    // Make sure no properties are required on the default value widget.
    if ($this->isDefaultValueWidget($form_state)) {
      $element['address']['#after_build'][] = [get_class($this), 'makeFieldsOptional'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    $error_element = NestedArray::getValue($element['address'], $violation->arrayPropertyPath);
    return is_array($error_element) ? $error_element : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $new_values = [];
    foreach ($values as $delta => $value) {
      $new_values[$delta] = $value['address'];
    }
    return $new_values;
  }

  /**
   * Form API callback: Makes all address field properties optional.
   */
  public static function makeFieldsOptional(array $element, FormStateInterface $form_state) {
    foreach (Element::getVisibleChildren($element) as $key) {
      if (!empty($element[$key]['#required'])) {
        $element[$key]['#required'] = FALSE;
      }
    }
    return $element;
  }

}
