<?php

namespace Drupal\scanner\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\scanner\Plugin\ScannerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Form for performing searching.
 */
class ScannerForm extends FormBase {

  use StringTranslationTrait;

  /**
   * The private temporary storage factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * The scanner plugin manager.
   *
   * @var Drupal\scanner\Plugin\ScannerPluginManager
   */
  protected $scannerManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(PrivateTempStoreFactory $tempStore, ScannerPluginManager $scannerManager) {
    $this->tempStore = $tempStore;
    $this->scannerManager = $scannerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('plugin.manager.scanner')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scanner_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('scanner.admin_settings');
    $mode = $form_state->getValue('scanner_mode') ? $form_state->getValue('scanner_mode') : $config->get('scanner_mode');
    $wholeword = $form_state->getValue('scanner_wholeword') ? $form_state->getValue('scanner_wholeword') : $config->get('scanner_wholeword');
    $regex = $form_state->getValue('scanner_regex') ? $form_state->getValue('scanner_regex') : $config->get('scanner_regex');
    $published = $form_state->getValue('scanner_published') ? $form_state->getValue('scanner_published') : $config->get('scanner_published');
    $language = $form_state->getValue('scanner_language') ? $form_state->getValue('scanner_language') : $config->get('scanner_language');

    $form['settings_link'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];
    $form['search'] = [
      '#type' => 'textfield',
      '#default_value' => '',
      '#title' => $this->t('Step 1: Search for'),
      '#maxlength' => 256,
    ];
    $form['submit_search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $form['replace'] = [
      '#type' => 'textfield',
      '#default_value' => '',
      '#title' => $this->t('Step 2: Replace with'),
      '#maxlength' => 256,
      '#access' => \Drupal::currentUser()->hasPermission('perform search and replace') ? TRUE : FALSE,
    ];
    $form['submit_replace'] = [
      '#type' => 'submit',
      '#value' => $this->t('Replace'),
      '#access' => \Drupal::currentUser()->hasPermission('perform search and replace') ? TRUE : FALSE,
    ];

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Search Options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['options']['surrounding'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Surrounding Text'),
      '#collapsible' => FALSE,
      '#description' => $this->t('You can limit matches by providing the text that should appear immediately before or after the search text. Remember to account for spaces.  Note: Case sensitivity and regular expression options will all apply here, too. Whole word is not recommended.'),
    ];
    $form['options']['surrounding']['preceded'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preceded by'),
      '#default_value' => '',
      '#maxlength' => 256,
    ];
    $form['options']['surrounding']['followed'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Followed by'),
      '#default_value' => '',
      '#maxlength' => 256,
    ];

    $form['options']['message'] = [
      '#type' => 'markup',
      '#markup' => $this->t('The below settings override the values configured in the <a href="@url" target="_blank">admin settings page</a>.', ['@url' => '/admin/config/content/scanner']),
    ];

    $form['options']['mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Case sensitive search'),
      '#default_value' => $mode,
      '#description' => $this->t('Check this if the search should only return results that exactly match the capitalization of your search terms.'),
    ];
    $form['options']['wholeword'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Match whole word'),
      '#default_value' => $wholeword,
      '#description' => $this->t("Check this if you don't want the search to match any partial words. For instance, if you search for 'run', a whole word search will <em>not</em> match 'running'."),
    ];
    $form['options']['regex'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use regular expressions in search'),
      '#default_value' => $regex,
      '#description' => $this->t('Check this if you want to use regular expressions in your search terms.'),
    ];
    $form['options']['published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Published nodes only'),
      '#default_value' => $published,
      '#description' => $this->t('Check this if you only want your search and replace to affect fields in nodes that are published.'),
    ];
    $form['options']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Content language'),
      '#default_value' => $language,
      '#options' => $this->getLanguages(),
      '#description' => $this->t('The language of the content you would like to search through.'),
    ];

    $scannerStore = $this->tempStore->get('scanner');
    // Empty the results on initial load, otherwise results from previous query
    // will be displayed.
    if (empty($form_state->getValues())) {
      $scannerStore->set('results', '');
    }
    else {
      $renderable = $scannerStore->get('results');
      $markup = \Drupal::service('renderer')->render($renderable);
      $form['results'] = [
        '#type' => 'markup',
        '#markup' => $markup,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation logic here.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $scannerStore = $this->tempStore->get('scanner');
    $op = $form_state->getUserInput()['op'];

    // Save the $form_state values into the user tempstore for later.
    foreach ($form_state->getValues() as $key => $value) {
      $scannerStore->set($key, $form_state->getValue($key));
    }

    $scannerStore->set('op', $op);

    if ($op == $this->t('Search')) {
      $fields = \Drupal::config('scanner.admin_settings')->get('fields_of_selected_content_type');

      // Build an array of batch operation jobs.
      // Batch job will need the field and the $form_state values.
      $operations = [];
      foreach ($fields as $key => $field) {
        $operations[] = [
          '\Drupal\scanner\Form\ScannerForm::batchSearch',
          [
            $field,
            $form_state->getValues(),
          ],
        ];
      }

      $batch = [
        'title' => $this->t('Scanner Search Batch'),
        'operations' => $operations,
        'finished' => '\Drupal\scanner\Form\ScannerForm::batchFinished',
        'progress_message' => $this->t('Processed @current out of @total'),
      ];
      batch_set($batch);
      $form_state->setRebuild(TRUE);
    }
    elseif ($op == $this->t('Replace')) {
      // Redirect to the confirmation form.
      $form_state->setRedirect('scanner.admin_confirm');
    }
  }

  /**
   * Batch operation function.
   *
   * @param string $field
   *   The name of the field.
   * @param array $values
   *   The $form_state values.
   * @param array $context
   *   An array containin data that is persisted across batch jobs.
   *
   * @see https://api.drupal.org/api/drupal/core%21includes%21form.inc/group/batch/8.5.x
   */
  public static function batchSearch($field, array $values, array &$context) {
    $pluginManager = \Drupal::service('plugin.manager.scanner');
    list($entityType, $bundle, $fieldname) = explode(':', $field);

    // Attempt to load the plugin.
    try {
      $plugin = $pluginManager->createInstance('scanner_entity');
    }
    catch (PluginException $e) {
      // The instance could not be found so fail gracefully and let the user
      // know.
      \Drupal::logger('scanner')->error($e->getMessage());
      \Drupal::messenger()->addError($this->t('An error occured @e:', ['@e' => $e->getMessage()]));
    }

    $results = $plugin->search($field, $values);
    if (!empty($results)) {
      $context['results'][$entityType][$bundle][$fieldname] = $results;
      // Number of entities with search term.
      $context['results']['count']['entities'] += count($results);
      foreach ($results as $data) {
        // Number of matches within each field of each entity.
        $context['results']['count']['matches'] += count($data['field']);
      }
      $context['message'] = 'Searching through field...';
    }
  }

  /**
   * The batch process has finished.
   *
   * @param bool $success
   *   Indicates whether the batch process finish successfully.
   * @param array $results
   *   Contains the output from the batch operations.
   * @param array $operations
   *   A list of operations that were processed.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success && isset($results['count'])) {
      $count = $results['count'];
      $count_for_theme = NULL;
      if (isset($results['count']['matches'])) {
        // Handle regex results.
        $count_for_theme = $results['count']['matches'];
      }
      elseif (isset($results['count']['entities'])) {
        // Handle other results.
        $count_for_theme = $results['count']['entities'];
      }
      else {
        // Handle other results.
        $count_for_theme = $results['count'];
      }
      // $count expected to be a numerical value.
      unset($results['count']);
      $renderable = [
        '#theme' => 'scanner_results',
        '#data' => ['values' => $results, 'count' => $count_for_theme],
      ];
      $scannerStore = \Drupal::service('tempstore.private')->get('scanner');
      // Persist the results to the tempstore.
      $scannerStore->set('results', $renderable);
    }
    else {
      \Drupal::messenger()->addMessage($this->t('There were some errors.'));
    }
    if (!isset($count['matches'])) {
      $count['matches'] = 0;
      $count['entities'] = 0;
    }
    \Drupal::messenger()->addMessage($this->t('Found @matches matches in @entities entities.', ['@matches' => $count['matches'], '@entities' => $count['entities']]));
  }

  /**
   * Helper function to fetch languaged enabled on the site.
   *
   * @return array
   *   The languages keyed by langcode, with an option "all" for all languages.
   */
  public function getLanguages() {
    $languages = \Drupal::languageManager()->getLanguages();
    foreach ($languages as $language) {
      $langs[$language->getId()] = $language->getName();
    }
    $langs['all'] = $this->t('All languages');
    return $langs;
  }

}
