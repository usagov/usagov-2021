<?php

namespace Drupal\ckeditor_templates\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "Templates" plugin.
 *
 * @CKEditorPlugin(
 *   id = "templates",
 *   label = @Translation("Templates")
 * )
 */
class CkeditorTemplates extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Configuration Factory Service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $configFactoryService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition, $container->get('config.factory')
    );
  }

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactory $configFactoryService
   *   Drupal Configuration Factory Service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactory $configFactoryService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configFactoryService = $configFactoryService;
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->getTemplatesPluginPath() . '/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return ['ckeditor_templates/ckeditor.templates.dialog'];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'Templates' => [
        'label' => t('Templates'),
        'image' => $this->getTemplatesPluginPath() . '/icons/templates.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $config = [];
    $settings = $editor->getSettings();
    // Set replace content default value if set.
    if (isset($settings['plugins']['templates']['replace_content'])) {
      $config['templates_replaceContent'] = $settings['plugins']['templates']['replace_content'];
    }
    // Set template files default value if set.
    if (isset($settings['plugins']['templates']['template_path']) && !empty($settings['plugins']['templates']['template_path'])) {
      $config['templates_files'] = [$settings['plugins']['templates']['template_path']];
    }
    else {
      // Use templates plugin default file.
      $config['templates_files'] = $this->getTemplatesDefaultPath();
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    // Defaults.
    $config = [
      'replace_content' => FALSE,
      'template_path' => '',
    ];

    $settings = $editor->getSettings();

    if (isset($settings['plugins']['templates'])) {
      $config = $settings['plugins']['templates'];
    }

    $form['template_path'] = [
      '#title' => t('Template definition file'),
      '#type' => 'textfield',
      '#default_value' => $config['template_path'],
      '#description' => t('Path to the javascript file defining the templates, relative to drupal root (starting with "/"). By default, it looks in your default theme directory for a file named "templates/ckeditor_templates.js"'),
    ];

    $form['replace_content'] = [
      '#title' => t('Replace content default value'),
      '#type' => 'checkbox',
      '#default_value' => $config['replace_content'],
      '#description' => t('Whether the "Replace actual contents" checkbox is checked by default in the Templates dialog'),
    ];

    $form['#attached']['library'][] = 'ckeditor_templates/ckeditor.templates.admin';

    return $form;
  }

  /**
   * Return ckeditor templates plugin path relative to drupal root.
   *
   * @return string
   *   Relative path to the ckeditor plugin folder
   */
  private function getTemplatesPluginPath() {
    $pluginPath = 'libraries/ckeditor/plugins/templates';

    if (!file_exists(DRUPAL_ROOT . '/' . $pluginPath)) {
      // keep supporting module legacy path to avoid breaking change
      // using this path is deprecated
      $pluginPath = 'libraries/templates';
    }

    return $pluginPath;
  }

  /**
   * Generate the path to the template file.
   *
   * The file will be picked from :
   * - the default theme if the file exists
   * - the ckeditor template directory otherwise.
   *
   * @return array
   *   List of path to the template file
   */
  private function getTemplatesDefaultPath() {
    // Default to module folder.
    $defaultPath = base_path() . $this->getTemplatesPluginPath() . '/templates/default.js';

    // Get site default theme name.
    $defaultThemConfig = $this->configFactoryService->get('system.theme');
    $defaultThemeName = $defaultThemConfig->get('default');

    $defaultThemeFileAbsolutePath = DRUPAL_ROOT . '/' . drupal_get_path('theme', $defaultThemeName) . '/templates/ckeditor_templates.js';
    if (file_exists($defaultThemeFileAbsolutePath)) {
      $defaultPath = base_path() . drupal_get_path('theme', $defaultThemeName) . '/templates/ckeditor_templates.js';
    }

    return [$defaultPath];
  }

}
