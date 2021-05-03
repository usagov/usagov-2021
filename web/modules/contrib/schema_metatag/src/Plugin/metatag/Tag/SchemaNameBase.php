<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;
use Drupal\schema_metatag\Plugin\schema_metatag\PropertyTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * All Schema.org tags should extend this class.
 */
class SchemaNameBase extends MetaNameBase implements ContainerFactoryPluginInterface {

  /**
   * The SchemaMetatagManager service.
   *
   * @var \Drupal\schema_metatag\SchemaMetatagManager
   */
  protected $schemaMetatagManager;

  /**
   * The PropertyTypeManager service.
   *
   * @var \Drupal\schema_metatag\Plugin\schema_metatag\PropertyTypeManager
   */
  protected $propertyTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->setSchemaMetatagManager($container->get('schema_metatag.schema_metatag_manager'));
    $instance->setPropertyTypeManager($container->get('plugin.manager.schema_property_type'));
    return $instance;
  }

  /**
   * Sets schemaMetatagManager service.
   *
   * @param \Drupal\schema_metatag\SchemaMetatagManager $schemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  public function setSchemaMetatagManager(SchemaMetatagManager $schemaMetatagManager) {
    $this->schemaMetatagManager = $schemaMetatagManager;
  }

  /**
   * Sets schemaMetatagManager service.
   *
   * @param \Drupal\schema_metatag\Plugin\schema_metatag\PropertyTypeManager $propertyTypeManager
   *   The PropertyTypeManager service.
   */
  public function setPropertyTypeManager(PropertyTypeManager $propertyTypeManager) {
    $this->propertyTypeManager = $propertyTypeManager;
  }

  /**
   * Return the SchemaMetatagManager service.
   *
   * @return \Drupal\schema_metatag\SchemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  protected function schemaMetatagManager() {
    return $this->schemaMetatagManager;
  }

  /**
   * Return the PropertyTypeManager service.
   *
   * @return \Drupal\schema_metatag\Plugin\schema_metatag\PropertyTypeManager
   *   The PropertyTypeManager service.
   */
  protected function propertyTypeManager() {
    return $this->propertyTypeManager;
  }

  /**
   * The #states base visibility selector for this element.
   */
  protected function visibilitySelector() {
    return $this->getPluginId();
  }

  /**
   * Generate a form element for this meta tag.
   *
   * This method should be overridden in classes that extend this base by
   * creating a form element using the property type manager.
   *
   * @param array $element
   *   The existing form element to attach to.
   *
   * @return array
   *   The completed form element.
   *
   * @see \Drupal\schema_metatag\Plugin\schema_metatag\PropertyTypeBase::form()
   */
  public function form(array $element = []) {

    $property_type = !empty($this->pluginDefinition['property_type']) ? $this->pluginDefinition['property_type'] : 'text';
    $tree_parent = !empty($this->pluginDefinition['tree_parent']) ? $this->pluginDefinition['tree_parent'] : '';
    $tree_depth = !empty($this->pluginDefinition['tree_depth']) ? $this->pluginDefinition['tree_depth'] : -1;

    $input_values = $this->getInputValues();
    if (!empty($tree_parent)) {
      $input_values['tree_parent'] = $tree_parent;
      $input_values['tree_depth'] = $tree_depth;
    }

    $form = $this->propertyTypeManager()
      ->createInstance($property_type)
      ->form($input_values);

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function output() {

    $value = $this->schemaMetatagManager()->unserialize($this->value());

    // If this is a complex array of values, process the array.
    if (is_array($value)) {

      // Clean out empty values.
      $value = $this->schemaMetatagManager()->arrayTrim($value);
    }

    if (empty($value)) {
      return '';
    }
    // If this is a complex array of value, process the array.
    elseif (is_array($value)) {

      // If the item is an array of values,
      // walk the array and process the values.
      array_walk_recursive($value, 'static::processItem');

      // Recursively pivot each branch of the array.
      $value = $this->pivotItem($value);

    }
    // Process a simple string.
    else {
      $this->processItem($value);
    }
    $output = [
      '#tag' => 'meta',
      '#attributes' => [
        'name' => $this->name,
        'content' => $this->outputValue($value),
        'group' => $this->group,
        'schema_metatag' => TRUE,
      ],
    ];

    return $output;
  }

  /**
   * Transform input value to its display output.
   *
   * Types that need to transform the output to something different than the
   * stored value should extend this method and do the transformation here.
   *
   * @param mixed $input_value
   *   Input value, could be either a string or array. This will be the
   *   value after token replacement.
   *
   * @return mixed
   *   Return the (possibly expanded) value which will be rendered in JSON-LD.
   */
  public function outputValue($input_value) {

    $property_type = !empty($this->pluginDefinition['property_type']) ? $this->pluginDefinition['property_type'] : 'text';

    return $this->propertyTypeManager()
      ->createInstance($property_type)
      ->outputValue($input_value);

  }

  /**
   * The serialized value for the metatag.
   *
   * Metatag expects a string value, so use the serialized value
   * without unserializing it. Manually unserialize it when needed.
   */
  public function value() {
    return $this->value;
  }

  /**
   * Metatag expects a string value, so serialize any array of values.
   */
  public function setValue($value) {
    $this->value = $this->schemaMetatagManager()->serialize($value);
  }

  /**
   * Get default values used to create a form element.
   *
   * @return array
   *   An array of values.
   *
   * @see Drupal\schema_metatag\SchemaMetatagManager::defaultInputValues();
   */
  public function getInputValues() {
    $value = $this->schemaMetatagManager()->unserialize($this->value());
    $default_values = $this->schemaMetatagManager()->defaultInputValues();
    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      'visibility_selector' => $this->visibilitySelector(),
      'multiple' => $this->multiple(),
    ];
    return array_merge($default_values, $input_values);
  }

  /**
   * {@inheritdoc}
   */
  public function pivotItem($array) {
    // See if any nested items need to be pivoted.
    // If pivot is set to 0, it would have been removed as an empty value.
    if (array_key_exists('pivot', $array)) {
      unset($array['pivot']);
      $array = $this->schemaMetatagManager()->pivot($array);
    }
    foreach ($array as &$value) {
      if (is_array($value)) {
        $value = $this->pivotItem($value);
      }
    }
    return $array;
  }

  /**
   * Nested elements that cannot be exploded.
   *
   * @return array
   *   Array of keys that might contain commas, or otherwise cannot be exploded.
   */
  protected function neverExplode() {
    return [
      'streetAddress',
      'reviewBody',
      'recipeInstructions',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function processItem(&$value, $key = 0) {

    $explode = $key === 0 ? $this->multiple() : !in_array($key, $this->neverExplode());

    // Parse out the image URL, if needed.
    $value = $this->parseImageUrlValue($value, $explode);

    $value = trim($value);

    // If tag must be secure, convert all http:// to https://.
    if ($this->secure() && strpos($value, 'http://') !== FALSE) {
      $value = str_replace('http://', 'https://', $value);
    }
    if ($explode) {
      $value = $this->schemaMetatagManager()->explode($value);
      // Clean out any empty values that might have been added by explode().
      if (is_array($value)) {
        $value = array_filter($value);
      }
    }
  }

  /**
   * Parse the image url out of image markup.
   *
   * A copy of the base method of the same name, but where $value is passed
   * in instead of assumed to be $this->value().
   */
  protected function parseImageUrlValue($value, $explode) {

    // If this contains embedded image tags, extract the image URLs.
    if ($this->type() === 'image') {
      // If image tag src is relative (starts with /), convert to an absolute
      // link.
      global $base_root;
      if (strpos($value, '<img src="/') !== FALSE) {
        $value = str_replace('<img src="/', '<img src="' . $base_root . '/', $value);
      }

      if (strip_tags($value) != $value) {
        if ($explode) {
          $values = explode(',', $value);
        }
        else {
          $values = [$value];
        }

        // Check through the value(s) to see if there are any image tags.
        foreach ($values as $key => $val) {
          $matches = [];
          preg_match('/src="([^"]*)"/', $val, $matches);
          if (!empty($matches[1])) {
            $values[$key] = $matches[1];
          }
        }
        $value = implode(',', $values);

        // Remove any HTML tags that might remain.
        $value = strip_tags($value);
      }
    }

    return $value;
  }

}
