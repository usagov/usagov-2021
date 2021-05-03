<?php

namespace Drupal\scheduled_publish\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\scheduled_publish\Plugin\Field\FieldType\ScheduledPublish;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'scheduled_publish_generic_formatter'
 * formatter.
 *
 * @FieldFormatter(
 *   id = "scheduled_publish_generic_formatter",
 *   label = @Translation("Scheduled publish generic formatter"),
 *   field_types = {
 *     "scheduled_publish"
 *   }
 * )
 */
class ScheduledPublishGenericFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * ScheduledPublishGenericFormatter constructor.
   *
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   * @param array $settings
   * @param $label
   * @param $view_mode
   * @param array $third_party_settings
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $loggerChannelFactory, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->logger = $loggerChannelFactory->get('scheduled_publish');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_type.manager'));
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        // Implement default settings.
        'date_format' => 'html_datetime',
        'text_pattern' => '%moderation_state% - %date%',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [
        'date_format' => [
          '#title' => $this->t('Date format'),
          '#type' => 'select',
          '#options' => $this->getDateFormats(),
          '#default_value' => $this->getSetting('date_format'),
        ],
        'text_pattern' => [
          '#title' => $this->t('Text replace pattern'),
          '#type' => 'textfield',
          '#default_value' => $this->getSetting('text_pattern'),
        ],
      ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [
      '#markup' => $this->t('Displays date in a custom format')
        ->render(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $strDateFormat = $this->getSetting('date_format');
    $strTextPattern = $this->getSetting('text_pattern');

    foreach ($items as $delta => $item) {
      /**
       * @var $item \Drupal\scheduled_publish\Plugin\Field\FieldType\ScheduledPublish
       */
      $rawValue = $item->getValue();
      $dateTime = $rawValue['value'];
      $moderationState = new TranslatableMarkup($rawValue['moderation_state']);
      $elements[$delta] = ['#markup' => $this->parseData($dateTime, $strDateFormat, $moderationState, $strTextPattern)];
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item): string {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return nl2br(Html::escape($item->value));
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getDateFormats(): array {
    $formats = [];
    $dateFormats = $this->entityTypeManager->getStorage('date_format')
      ->loadMultiple();
    foreach ($dateFormats as $dateFormat) {
      $formats[$dateFormat->id()] = $dateFormat->get('label');
    }
    return $formats;
  }

  /**
   * @param string $strDateTime
   * @param string $strDateFormat
   * @param string $moderationState
   * @param string $pattern
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function parseData(string $strDateTime, string $strDateFormat, string $moderationState, string $pattern): string {
    $date = $this->parseDate($strDateTime, $strDateFormat);
    return str_replace(['%moderation_state%', '%date%'], [
      $moderationState,
      $date,
    ], $pattern);
  }

  /**
   * @param string $strDateTime
   *
   * @param string $strDateFormat
   *
   * @return \DateTime
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function parseDate(string $strDateTime, string $strDateFormat): string {
    $dateFormat = $this->entityTypeManager->getStorage('date_format')
      ->load($strDateFormat);
    if ($dateFormat !== NULL) {
      $pattern = $dateFormat->getPattern();
      $drupalDateTime = DrupalDateTime::createFromFormat(ScheduledPublish::DATETIME_STORAGE_FORMAT, $strDateTime, ScheduledPublish::STORAGE_TIMEZONE);
      $drupalDateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
      return $drupalDateTime->format($pattern);
    }
    $this->logger->error($this->t('Date format: "' . $this->getSetting('date_format') . '" could not be found!'));
    return NULL;
  }
}
