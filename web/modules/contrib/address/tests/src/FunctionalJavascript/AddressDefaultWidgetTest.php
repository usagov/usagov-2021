<?php

namespace Drupal\Tests\address\FunctionalJavascript;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the default address widget.
 *
 * @group address
 */
class AddressDefaultWidgetTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'language',
    'user',
    'field',
    'field_ui',
    'node',
    'address',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with permission to administer entities.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Address field instance.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $field;

  /**
   * Entity form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * URL to add new content.
   *
   * @var string
   */
  protected $nodeAddUrl;

  /**
   * URL to field's configuration form.
   *
   * @var string
   */
  protected $fieldConfigUrl;

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
   * The address format repository.
   *
   * @var \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface
   */
  protected $addressFormatRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create node bundle for tests.
    $type = NodeType::create(['name' => 'Article', 'type' => 'article']);
    $type->save();

    // Create user that will be used for tests.
    $this->adminUser = $this->createUser([
      'create article content',
      'edit own article content',
      'administer content types',
      'administer node fields',
    ]);
    $this->drupalLogin($this->adminUser);

    // Add the address field to the article content type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_address',
      'entity_type' => 'node',
      'type' => 'address',
    ]);
    $field_storage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Address',
      'default_value' => [
        [
          'country_code' => 'US',
        ],
      ],
    ]);
    $this->field->save();

    // Set article's form display.
    $this->formDisplay = EntityFormDisplay::load('node.article.default');

    if (!$this->formDisplay) {
      EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ])->save();
      $this->formDisplay = EntityFormDisplay::load('node.article.default');
    }
    $this->formDisplay->setComponent($this->field->getName(), [
      'type' => 'address_default',
    ])->save();

    $this->nodeAddUrl = 'node/add/article';
    $this->fieldConfigUrl = 'admin/structure/types/manage/article/fields/node.article.' . $this->field->getName();

    $this->countryRepository = \Drupal::service('address.country_repository');
    $this->subdivisionRepository = \Drupal::service('address.subdivision_repository');
    $this->addressFormatRepository = \Drupal::service('address.address_format_repository');
  }

  /**
   * Tests the country field.
   *
   * Checked:
   * - required/optional status.
   * - available_countries instance setting.
   */
  public function testCountries() {
    $field_name = $this->field->getName();
    // Optional field: Country should be optional and set to default_country.
    $this->drupalGet($this->nodeAddUrl);
    $this->assertEmpty((bool) $this->xpath('//select[@name="' . $field_name . '[0][address][country_code]" and boolean(@required)]'), 'Country is shown as optional.');
    $this->assertOptionSelected($field_name . '[0][address][country_code]', 'US', 'The configured default_country is selected.');

    // Required field: Country should be required and set to default_country.
    $this->field->setRequired(TRUE);
    $this->field->save();
    $this->drupalGet($this->nodeAddUrl);
    $this->assertNotEmpty((bool) $this->xpath('//select[@name="' . $field_name . '[0][address][country_code]" and boolean(@required)]'), 'Country is shown as required.');
    $this->assertOptionSelected($field_name . '[0][address][country_code]', 'US', 'The configured default_country is selected.');

    // All countries should be present in the form.
    $countries = array_keys($this->countryRepository->getList());
    $this->assertOptions($field_name . '[0][address][country_code]', $countries, 'All countries are present.');

    // Limit the list of available countries.
    $countries = ['US', 'FR', 'BR', 'JP'];
    $edit = [];
    $edit['settings[available_countries][]'] = array_map(function ($country) {
      return $country;
    }, $countries);
    $this->drupalGet($this->fieldConfigUrl);
    $this->submitForm($edit, t('Save settings'));
    $this->drupalGet($this->nodeAddUrl);
    $this->assertOptions($field_name . '[0][address][country_code]', $countries, 'The restricted list of available countries is present.');

    // Create an article with one of them.
    $country_code = 'US';
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $address = [
      'given_name' => 'John',
      'family_name' => 'Smith',
      'organization' => 'Some Organization',
      'address_line1' => '1098 Alta Ave',
      'locality' => 'Mountain View',
      'administrative_area' => 'CA',
      'postal_code' => '94043',
    ];
    $edit = [];
    $edit['title[0][value]'] = $this->randomMachineName(8);
    foreach ($address as $property => $value) {
      $path = $field_name . '[0][address][' . $property . ']';
      $edit[$path] = $value;
    }
    $this->submitForm($edit, t('Save'));
    // Check that the article has been created.
    $node = $this->getNodeByTitle($edit['title[0][value]']);
    $this->assertNotEmpty($node, 'Created article ' . $edit['title[0][value]']);

    // Now remove 'US' from the list of available countries.
    $countries = ['FR'];
    $edit = [];
    $edit['settings[available_countries][]'] = array_map(function ($country) {
      return $country;
    }, $countries);
    $this->drupalPostForm($this->fieldConfigUrl, $edit, t('Save settings'));

    // Access the article's edit form and confirm the values are unchanged.
    // 'US' should be in the list along with the available countries and should
    // be selected.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][given_name]', $address['given_name']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][family_name]', $address['family_name']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][organization]', $address['organization']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][address_line1]', $address['address_line1']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][locality]', $address['locality']);
    $this->assertOptionSelected($field_name . '[0][address][administrative_area]', $address['administrative_area']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][postal_code]', $address['postal_code']);
    $this->assertOptionSelected($field_name . '[0][address][country_code]', $country_code);
    // Confirm that it is possible to switch the country to France, and back.
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', 'FR');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists($field_name . '[0][address][administrative_area]');
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists($field_name . '[0][address][administrative_area]');

    // Test the widget with only one available country.
    // Since the field is required, the country selector should be hidden.
    $countries = ['US'];
    $edit = [];
    $edit['settings[available_countries][]'] = array_map(function ($country) {
      return $country;
    }, $countries);
    $this->drupalPostForm($this->fieldConfigUrl, $edit, t('Save settings'));

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->fieldNotExists($field_name . '[0][address][country_code]');
    // Submitting the form should result in no data loss.
    $this->submitForm([], t('Save'));
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][given_name]', $address['given_name']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][family_name]', $address['family_name']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][organization]', $address['organization']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][address_line1]', $address['address_line1']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][locality]', $address['locality']);
    $this->assertOptionSelected($field_name . '[0][address][administrative_area]', $address['administrative_area']);
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][postal_code]', $address['postal_code']);
  }

  /**
   * Tests the default value functionality.
   */
  public function testDefaultValue() {
    $this->drupalGet($this->fieldConfigUrl);
    // Confirm that the US is selected by default.
    $this->assertSession()->fieldValueEquals('default_value_input[field_address][0][address][country_code]', 'US');
    // Confirm that it is possible to switch the country to France.
    $this->getSession()->getPage()->fillField('default_value_input[field_address][0][address][country_code]', 'FR');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists('default_value_input[field_address][0][address][administrative_area]');
    // Confirm that it is possible to fill-in only certain fields.
    $edit = [
      'default_value_input[field_address][0][address][given_name]' => 'John',
      'default_value_input[field_address][0][address][family_name]' => 'Smith',
    ];
    $this->submitForm($edit, t('Save settings'));
    $this->assertSession()->pageTextContains('Saved Address configuration.');

    $this->container->get('entity_type.manager')->getStorage('field_config')->resetCache();
    $this->field = FieldConfig::load($this->field->id());
    $default_value = $this->field->getDefaultValueLiteral();
    $expected_default_value = [
      'country_code' => 'FR',
      'given_name' => 'John',
      'family_name' => 'Smith',
    ];
    $this->assertCount(1, $default_value);
    $this->assertEquals($expected_default_value, array_filter($default_value[0]));

    // Confirm that the default value is used on the node form.
    $this->drupalGet($this->nodeAddUrl);
    $this->assertSession()->fieldValueEquals('field_address[0][address][country_code]', 'FR');
    $this->assertSession()->fieldValueEquals('field_address[0][address][given_name]', 'John');
    $this->assertSession()->fieldValueEquals('field_address[0][address][family_name]', 'Smith');
    $this->assertSession()->fieldValueEquals('field_address[0][address][postal_code]', '');
  }

  /**
   * Tests the alter events.
   */
  public function testEvents() {
    $field_name = $this->field->getName();
    // The address_test module is installed here, not in setUp().
    // This way the module's events will not affect other tests.
    self::$modules[] = 'address_test';
    $this->container->get('module_installer')->install(self::$modules);
    $this->container = $this->kernel->rebuildContainer();

    // Confirm that the list of available countries was altered.
    $subscriber = \Drupal::service('address_test.event_subscriber');
    $available_countries = array_keys($subscriber->getAvailableCountries());
    $this->drupalGet($this->nodeAddUrl);
    $this->assertOptions($field_name . '[0][address][country_code]', $available_countries);

    // Confirm that counties for Great Britain were added.
    $expected_counties = [
      'Anglesey', 'Blaenau Gwent', 'Bridgend', 'Caerphilly', 'Cardiff',
      'Carmarthenshire', 'Ceredigion', 'Conwy', 'Denbighshire', 'Flintshire',
      'Gwynedd', 'Merthyr Tydfil', 'Monmouthshire', 'Neath Port Talbot',
      'Newport', 'Pembrokeshire', 'Powys', 'Rhondda Cynon Taf', 'Swansea',
      'Tarfaen', 'Vale of Glamorgan', 'Wrexham',
    ];
    $this->drupalGet($this->nodeAddUrl);
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', 'GB');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains(t('County'));
    $this->assertSession()->fieldExists($field_name . '[0][address][administrative_area]');
    $this->assertOptions($field_name . '[0][address][administrative_area]', $expected_counties);

    // Uninstall and remove the address_test module.
    $this->container->get('module_installer')->uninstall(['address_test']);
    $this->container = $this->kernel->rebuildContainer();
    array_pop(self::$modules);
  }

  /**
   * Tests expected and overridden fields.
   */
  public function testFields() {
    $field_name = $this->field->getName();
    // Keys are field names from the field instance.
    // Values are corresponding field names from add article form.
    $all_fields = [
      'administrativeArea' => $field_name . '[0][address][administrative_area]',
      'locality' => $field_name . '[0][address][locality]',
      'dependentLocality' => $field_name . '[0][address][dependent_locality]',
      'postalCode' => $field_name . '[0][address][postal_code]',
      'sortingCode' => $field_name . '[0][address][sorting_code]',
      'addressLine1' => $field_name . '[0][address][address_line1]',
      'addressLine2' => $field_name . '[0][address][address_line2]',
      'organization' => $field_name . '[0][address][organization]',
      'givenName' => $field_name . '[0][address][given_name]',
      'familyName' => $field_name . '[0][address][family_name]',
    ];

    // US has all fields except sorting code and dependent locality.
    // France has sorting code, and China has dependent locality, so these
    // countries cover all fields.
    $this->drupalGet($this->nodeAddUrl);
    foreach (['US', 'FR', 'CN'] as $country) {
      /** @var \CommerceGuys\Addressing\AddressFormat\AddressFormat $address_format */
      $address_format = $this->addressFormatRepository->get($country);
      $used_fields = $address_format->getUsedFields();

      $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', $country);
      $this->assertSession()->assertWaitOnAjaxRequest();
      // Compare the found fields to the address format.
      // Make one assert instead of many asserts for each field's existence.
      $elements = $this->xpath('//input[starts-with(@name,"' . $field_name . '")] | //select[starts-with(@name,"' . $field_name . '")]');
      $form_fields = [];
      foreach ($elements as $element) {
        if ($field = array_search($element->getAttribute('name'), $all_fields)) {
          $form_fields[] = $field;
        }
      }
      $this->assertFieldValues($used_fields, $form_fields, 'Expected fields ' . implode(', ', $used_fields) . ' exists for country ' . $country . ", only found " . implode(', ', $form_fields));
    }

    // Test field overrides.
    $edit = [
      'settings[field_overrides][givenName][override]' => 'optional',
      'settings[field_overrides][familyName][override]' => 'optional',
      'settings[field_overrides][organization][override]' => 'required',
      'settings[field_overrides][postalCode][override]' => 'hidden',
    ];
    $this->drupalGet($this->fieldConfigUrl);
    $this->submitForm($edit, t('Save settings'));

    $this->drupalGet($this->nodeAddUrl);
    $this->assertEmpty((bool) $this->xpath('//input[@name="field_address[0][address][given_name]" and contains(@required, "required")]'));
    $this->assertEmpty((bool) $this->xpath('//input[@name="field_address[0][address][family_name]" and contains(@required, "required")]'));
    $this->assertNotEmpty((bool) $this->xpath('//input[@name="field_address[0][address][organization]" and contains(@required, "required")]'));
    $this->assertEmpty((bool) $this->xpath('//input[@name="field_address[0][address][postal_code]"]'));

    // Test creating an address without the optional and hidden fields.
    $edit = [];
    $edit['title[0][value]'] = $this->randomMachineName(8);

    // Use javascript to fill country_code so other fields can be loaded.
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $edit[$field_name . '[0][address][organization]'] = 'Some Organization';
    $edit[$field_name . '[0][address][address_line1]'] = '1098 Alta Ave';
    $edit[$field_name . '[0][address][address_line2]'] = 'Street 2';
    $edit[$field_name . '[0][address][locality]'] = 'Mountain View';
    $edit[$field_name . '[0][address][administrative_area]'] = 'CA';
    $this->submitForm($edit, t('Save'));
    $node = $this->getNodeByTitle($edit['title[0][value]']);
    $this->assertNotEmpty($node, 'Created article ' . $edit['title[0][value]']);
  }

  /**
   * Tests the presence of subdivision dropdowns where expected.
   */
  public function testSubdivisions() {
    $field_name = $this->field->getName();
    // Using China since it has predefined subdivisions on all three levels.
    $country = 'CN';
    $administrative_area = 'Hebei Sheng';
    $locality = 'Chengde Shi';
    $administrative_areas = $this->subdivisionRepository->getList([$country]);
    $localities = $this->subdivisionRepository->getList([$country, $administrative_area]);
    $dependent_localities = $this->subdivisionRepository->getList([$country, $administrative_area, $locality]);
    // Confirm the presence and format of the administrative area dropdown.
    $this->drupalGet($this->nodeAddUrl);
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', $country);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertOptions($field_name . '[0][address][administrative_area]', array_keys($administrative_areas), 'All administrative areas for country ' . $country . ' are present.');

    // Confirm the presence and format of the locality dropdown.
    $this->getSession()->getPage()->fillField($field_name . '[0][address][administrative_area]', $administrative_area);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertOptionSelected($field_name . '[0][address][administrative_area]', $administrative_area, 'Selected administrative area ' . $administrative_areas[$administrative_area]);
    $this->assertOptions($field_name . '[0][address][locality]', array_keys($localities), 'All localities for administrative area ' . $administrative_areas[$administrative_area] . ' are present.');

    // Confirm the presence and format of the dependent locality dropdown.
    $this->getSession()->getPage()->fillField($field_name . '[0][address][locality]', $locality);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertOptionSelected($field_name . '[0][address][locality]', $locality, 'Selected locality ' . $localities[$locality]);
    $this->assertOptions($field_name . '[0][address][dependent_locality]', array_keys($dependent_localities), 'All dependent localities for locality ' . $localities[$locality] . ' are present.');
  }

  /**
   * Tests that changing the country clears the expected values.
   */
  public function testClearValues() {
    $field_name = $this->field->getName();
    // Create an article with all fields filled.
    $this->drupalGet($this->nodeAddUrl);
    $edit = [];
    $edit['title[0][value]'] = $this->randomMachineName(8);

    // Use javascript to fill country_code so other fields can be loaded.
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $edit[$field_name . '[0][address][given_name]'] = 'John';
    $edit[$field_name . '[0][address][family_name]'] = 'Smith';
    $edit[$field_name . '[0][address][organization]'] = 'Some Organization';
    $edit[$field_name . '[0][address][address_line1]'] = '1098 Alta Ave';
    $edit[$field_name . '[0][address][address_line2]'] = 'Street 2';
    $edit[$field_name . '[0][address][locality]'] = 'Mountain View';
    $edit[$field_name . '[0][address][administrative_area]'] = 'CA';
    $edit[$field_name . '[0][address][postal_code]'] = '94043';
    $this->submitForm($edit, t('Save'));
    $node = $this->getNodeByTitle($edit['title[0][value]']);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][country_code]', 'US');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][administrative_area]', 'CA');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][locality]', 'Mountain View');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][postal_code]', '94043');

    // Now change the country to China, subdivision fields should be cleared.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->getSession()->getPage()->fillField($field_name . '[0][address][country_code]', 'CN');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->submitForm([], t('Save'));
    // Check that values are cleared.
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][country_code]', 'CN');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][administrative_area]', '');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][locality]', '');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][dependent_locality]', '');
    $this->assertSession()->fieldValueEquals($field_name . '[0][address][postal_code]', '');
  }

  /**
   * Asserts that a select field has all of the provided options.
   *
   * Core only has assertOption(), this helper decreases the number of needed
   * assertions.
   *
   * @param string $id
   *   ID of select field to assert.
   * @param array $options
   *   Options to assert.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   */
  protected function assertOptions($id, array $options, $message = '') {
    $elements = $this->xpath('//select[@name="' . $id . '"]/option');
    $found_options = [];
    foreach ($elements as $element) {
      if ($option = $element->getValue()) {
        $found_options[] = $option;
      }
    }
    $this->assertFieldValues($found_options, $options, $message);
  }

  /**
   * Asserts that a select field has a selected option.
   *
   * @param string $id
   *   ID of select field to assert.
   * @param string $option
   *   Option to assert.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   */
  protected function assertOptionSelected($id, $option, $message = '') {
    $elements = $this->xpath('//select[@name=:id]//option[@value=:option]', [':id' => $id, ':option' => $option]);
    foreach ($elements as $element) {
      $this->assertNotEmpty($element->isSelected(), $message ? $message : new FormattableMarkup('Option @option for field @id is selected.', ['@option' => $option, '@id' => $id]));
    }
  }

  /**
   * Asserts that the passed field values are correct.
   *
   * Ignores differences in ordering.
   *
   * @param array $field_values
   *   The field values.
   * @param array $expected_values
   *   The expected values.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   */
  protected function assertFieldValues(array $field_values, array $expected_values, $message = '') {
    $valid = TRUE;
    if (count($field_values) == count($expected_values)) {
      foreach ($expected_values as $value) {
        if (!in_array($value, $field_values)) {
          $valid = FALSE;
          break;
        }
      }
    }
    else {
      $valid = FALSE;
    }

    $this->assertTrue($valid, $message);
  }

}
