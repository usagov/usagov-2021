<?php

namespace Drupal\uswds_ckeditor_integration\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\SetDialogTitleCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a grid dialog form for use in CKEditor.
 *
 * @package Drupal\uswds_ckeditor_integration\Form
 */
class GridDialog extends FormBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * GridDialog constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GridDialog {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'uswds_ckeditor_integration';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'uswds_ckeditor_integration/dialog';

    // Opening the dialog passes the value as editor object, but is only once.
    $values = $form_state->getValues();
    $input = $form_state->getUserInput();

    // Initialize entity element with form attributes, if present.
    $settings = empty($values['uswds_grid_settings']) ? [] : Json::decode($values['uswds_grid_settings']);
    $settings += empty($input['uswds_grid_settings']) ? [] : Json::decode($input['uswds_grid_settings']);
    // The default values are set directly from \Drupal::request()->request,
    // provided by the editor plugin opening the dialog.
    if (!$form_state->get('uswds_grid_settings')) {
      $form_state->set('uswds_grid_settings', isset($input['editor_object']) ? $input['editor_object'] : []);
    }
    $settings += $form_state->get('uswds_grid_settings');
    $form_state->set('uswds_grid_settings', $settings);

    if (!$form_state->get('step')) {
      $form_state->set('step', 'select');
    }

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="uswds_grid-dialog-form">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'uswds_grid-dialog-step--' . $form_state->get('step');

    if ($form_state->get('step') == 'select') {
      $form = $this->buildSelectStep($form, $form_state);
    }
    elseif ($form_state->get('step') == 'layout') {
      $form = $this->buildLayoutStep($form, $form_state);
    }
    elseif ($form_state->get('step') == 'advanced') {
      $form = $this->buildAdvancedStep($form, $form_state);
    }

    return $form;
  }

  /**
   * Builds the column selection step.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form part.
   */
  public function buildSelectStep(array $form, FormStateInterface $form_state): array {
    $settings = $form_state->get('uswds_grid_settings');
    $form['#title'] = $this->t("Select columns");

    $columns = [];
    for ($i = 1; $i <= 12; $i++) {
      $title = $this->t('Column @num', ['@num' => $i]);
      $img = drupal_get_path('module', 'uswds_ckeditor_integration') . '/images/ui/col_' . $i . '.png';
      $img_src = '<img src="/' . $img . '" title="' . $title . '" />';
      $columns[$i] = $img_src . '<p>' . $this->t('Column @num', ['@num' => $i]) . '</p>';
    }

    // @TODO: Need to find a better way around this.
    if (!empty($settings['saved'])) {
      $form['uswds_grid_settings'] = [
        '#type' => 'hidden',
        '#value' => Json::encode($settings),
      ];
    }

    $form['num_columns'] = [
      '#title' => $this->t('Select Number of Columns'),
      '#type' => 'radios',
      '#options' => $columns,
      '#default_value' => $settings['num_columns'] ?? 1,
      '#attributes' => [
        'disabled' => $settings['saved'] ?? FALSE,
      ],
      '#prefix' => $this->t('Read-only on existing elements.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitStep',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-next',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Builds the layout selection step.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form part.
   */
  public function buildLayoutStep(array $form, FormStateInterface $form_state): array {
    $settings = $form_state->get('uswds_grid_settings');
    $form['#title'] = $this->t("Choose a layout");

    // $form['add_container'] = [
    // '#title' => $this->t('Add Container'),
    // '#type' => 'checkbox',
    // '#default_value' => $settings['add_container'] ?? FALSE,
    // '#attributes' => [
    // 'class' => ['uswds_grid-add-container'],
    // ],
    // ];
    //
    // $form['container_type'] = [
    // '#title' => $this->t('Container Type'),
    // '#type' => 'radios',
    // '#options' => [
    // 'default' => $this->t('Default'),
    // 'fluid' => $this->t('Fluid'),
    // 'wrapper' => $this->t('Wrapper'),
    // ],
    // '#default_value' => $settings['container_type'] ?? 'default',
    // '#states' => [
    // 'visible' => [
    // '.uswds_grid-add-container' => ['checked' => TRUE],
    // ],
    // ],
    // ];
    // @TODO: detect row class override.
    $form['no_gutter'] = [
      '#title' => $this->t('No Gutters'),
      '#type' => 'checkbox',
      '#default_value' => $settings['no_gutter'] ?? FALSE,
    ];

    // Output the options for each breakpoint.
    $breakpoints = [
      'none' => $this->t('Default'),
      'sm' => $this->t('Mobile Large'),
      'md' => $this->t('Tablet'),
      'lg' => $this->t('Desktop'),
    ];

    $form['tab_container'] = [
      '#type' => 'vertical_tabs',
    ];

    // Default.
    $num_cols = (int) $settings['num_columns'];
    $options = [
      'none' => $this->t('None (advanced)'),
      'auto' => $this->t('Auto'),
      'equal' => $this->t('Equal Width'),
    ];

    // Add specifics per columns.
    $this->calculateColumns($num_cols, $options);

    foreach ($breakpoints as $class => $breakpoint) {

      $form['breakpoints'][$class] = [
        '#title' => $breakpoint,
        '#type' => 'details',
        '#open' => $class === 'none',
        '#group' => 'tab_container',
      ];

      unset($options['full']);
      if (in_array($class, ['none', 'sm', 'md'])) {
        $options['full'] = $this->t('Full Width (100%)');
      }

      $form['breakpoints'][$class]['layout'] = [
        '#type' => 'radios',
        '#options' => $options,
        '#default_value' => $settings['breakpoints'][$class]['layout'] ?? 'none',
      ];

    }

    // @TODO: Refactor for repetition here.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitBackStep',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-back',
        ],
      ],
    ];

    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitDialog',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-next',
        ],
      ],
    ];

    $form['actions']['advanced'] = [
      '#type' => 'submit',
      '#value' => $this->t('Advanced Settings'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitStep',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-next',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Builds the advanced settings step.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form part.
   */
  public function buildAdvancedStep(array $form, FormStateInterface $form_state): array {
    $settings = $form_state->get('uswds_grid_settings');
    $form['#title'] = $this->t("Advanced Settings");

    // Only show if container is selected.
    if (!empty($settings['add_container'])) {
      $form['container_wrapper_class'] = [
        '#title' => $this->t('Container Wrapper Classes'),
        '#type' => 'textfield',
        '#description' => $this->t('Add classes separated by space. Ex: bg-warning py-5'),
        '#default_value' => $settings['container_wrapper_class'] ?? '',
      ];

      $form['container_class'] = [
        '#title' => $this->t('Container Classes'),
        '#type' => 'textfield',
        '#description' => $this->t('Add classes separated by space. Ex: bg-warning py-5'),
        '#default_value' => $settings['container_class'] ?? '',
      ];
    }

    $form['row_class'] = [
      '#title' => $this->t('Row Classes'),
      '#type' => 'textfield',
      '#description' => $this->t('Add classes separated by space. Ex: bg-warning py-5'),
      '#default_value' => $settings['row_class'] ?? '',
    ];

    for ($i = 1; $i <= $settings['num_columns']; $i++) {
      $form['col_' . $i . '_classes'] = [
        '#title' => $this->t('Col @num classes', ['@num' => $i]),
        '#type' => 'textfield',
        '#default_value' => $settings['col_' . $i . '_classes'] ?? '',
        '#description' => $this->t('Add classes separated by space. Ex: bg-warning py-5'),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitBackStep',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-back',
        ],
      ],
    ];

    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitDialog',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-next',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Load current settings.
    $settings = $form_state->get('uswds_grid_settings');

    if ($form_state->get('step') == 'select') {
      $values_to_save = ['num_columns'];
    }
    elseif ($form_state->get('step') == 'layout') {
      $values_to_save = [
        'add_container',
        'container_type',
        'no_gutter',
        'breakpoints',
      ];
    }
    elseif ($form_state->get('step') == 'advanced') {
      $values_to_save = [
        'container_wrapper_class',
        'container_class',
        'row_class',
      ];

      // Detect changes and set parent to "none".
      for ($i = 1; $i <= $settings['num_columns']; $i++) {
        $key = 'col_' . $i . '_classes';
        $settings[$key] = $form_state->getValue($key, '');
      }
    }

    if (isset($values_to_save)) {
      foreach ($values_to_save as $save) {
        $settings[$save] = $form_state->getValue($save);
      }
    }
    $form_state->set('uswds_grid_settings', $settings);
  }

  /**
   * Submits a step to move on.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public function submitStep(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    // Display errors in form, if any.
    if ($form_state->hasAnyErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#uswds_grid-dialog-form', $form));
    }
    else {
      $form_state->set('step', $form_state->get('step') === 'select' ? 'layout' : 'advanced');
      $form_state->setRebuild(TRUE);
      $rebuild_form = $this->formBuilder->rebuildForm('uswds_ckeditor_integration', $form_state, $form);
      unset($rebuild_form['#prefix'], $rebuild_form['#suffix']);
      $response->addCommand(new HtmlCommand('#uswds_grid-dialog-form', $rebuild_form));
      $response->addCommand(new SetDialogTitleCommand('', $rebuild_form['#title']));
    }
    return $response;
  }

  /**
   * Submits a backstep. @TODO: this should probably just go above.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public function submitBackStep(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $form_state->set('step', $form_state->get('step') === 'advanced' ? 'layout' : 'select');
    $form_state->setRebuild(TRUE);
    $rebuild_form = $this->formBuilder->rebuildForm('uswds_ckeditor_integration', $form_state, $form);
    unset($rebuild_form['#prefix'], $rebuild_form['#suffix']);
    $response->addCommand(new HtmlCommand('#uswds_grid-dialog-form', $rebuild_form));
    $response->addCommand(new SetDialogTitleCommand('', $rebuild_form['#title']));

    return $response;
  }

  /**
   * Commit the changes and close the dialog window.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public function submitDialog(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $settings = $form_state->get('uswds_grid_settings');

    // // Set container classes.
    // if (isset($settings['add_container'])) {
    // $settings['container_class'] = $settings['container_class'] ?? '';
    // if ($settings['container_type'] === 'default') {
    // $settings['container_class'] = 'container ' .
    // $settings['container_class'];
    // }
    // else {
    // $settings['container_class'] = 'container-fluid ' .
    // $settings['container_class'];
    // }
    // }
    // else {
    // $settings['container_class'] = '';
    // }
    // Set row classes.
    if (isset($settings['row_class'])) {
      if (strpos($settings['row_class'], 'row') === FALSE) {
        $settings['row_class'] = 'grid-row ' . $settings['row_class'];
      }
      if ($settings['no_gutter'] && strpos($settings['row_class'], 'no-gutters') === FALSE) {
        $settings['row_class'] = 'no-gutters ' . $settings['row_class'];
      }
    }
    elseif ($settings['no_gutter']) {
      $settings['row_class'] = 'grid-row';
    }
    else {
      $settings['row_class'] = 'grid-row grid-gap';
    }

    // Parse out the column classes.
    for ($i = 1; $i <= $settings['num_columns']; $i++) {
      $keys = [];
      $col = 'col_' . $i . '_classes';
      foreach ($settings['breakpoints'] as $class => $selection) {

        switch ($class) {
          case 'sm':
            $new_class = 'mobile-lg:';
            break;

          case 'md':
            $new_class = 'tablet:';
            break;

          case 'lg':
            $new_class = 'desktop:';
            break;

          default:
            $new_class = '';
            break;
        }

        // Advanced.
        if ($selection['layout'] === 'none') {
          continue;
        }
        // Equal columns.
        elseif (in_array($selection['layout'], ['equal', 'auto', 'full'])) {
          $suffix = '';
          if ($selection['layout'] === 'auto') {
            $suffix = '-auto';
          }
          elseif ($selection['layout'] === 'full') {
            $suffix = '-12';
          }
          if ($class === 'none') {
            $keys['grid-col-fill' . $suffix] = TRUE;
          }
          else {
            $keys[$new_class . 'grid-col' . $suffix] = TRUE;
          }
        }
        else {
          $vals = explode('_', $selection['layout']);
          if (!empty($vals[$i - 1])) {
            if ($class === 'none') {
              $keys['grid-col-' . $vals[$i - 1]] = TRUE;
            }
            else {
              $keys[$new_class . 'grid-col-' . $vals[$i - 1]] = TRUE;
            }
          }
        }
      }

      $current = implode(' ', array_keys($keys));
      if (!empty($settings[$col])) {
        $settings[$col] = $current . ' ' . $settings[$col];
      }
      else {
        $settings[$col] = $current;
      }
    }

    // Track that we're committed.
    $settings['saved'] = TRUE;

    $response->addCommand(new EditorDialogSave($settings));
    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing needed here.
  }

  /**
   * Column selections. @TODO: Config or maybe calculated?
   *
   * @param mixed $num_cols
   *   The number of cols selected.
   * @param array $options
   *   The options array.
   */
  protected function calculateColumns($num_cols, array &$options) {

    // For now, just defined.
    switch ($num_cols) {
      case 2:
        $options['3_9'] = $this->t('25% / 75%');
        $options['4_8'] = $this->t('33% / 66%');
        $options['8_4'] = $this->t('66% / 33%');
        $options['9_3'] = $this->t('75% / 25%');
        break;

      case 3:
        $options['6_3_3'] = $this->t('50% / 25% / 25%');
        $options['3_6_3'] = $this->t('25% / 50% / 25%');
        $options['3_3_6'] = $this->t('25% / 25% / 50%');
        break;

      case 4:
        $options['4_4_2_2'] = $this->t('33% / 33% / 17% / 17%');
        $options['2_4_2_4'] = $this->t('17% / 33% / 17% / 33%');
        $options['2_4_4_2'] = $this->t('17% / 33% / 33% / 17%');
        $options['2_2_4_4'] = $this->t('17% / 17% / 33% / 33%');
        break;

      case 5:
        $options['4_2_2_2_2'] = $this->t('33% / 17% / 17% / 17% / 17%');
        $options['2_4_2_2_2'] = $this->t('17% / 33% / 17% / 17% / 17%');
        $options['2_2_4_2_2'] = $this->t('17% / 17% / 33% / 17% / 17%');
        $options['2_2_2_4_2'] = $this->t('17% / 17% / 17% / 33% / 17%');
        $options['2_2_2_2_4'] = $this->t('17% / 17% / 17% / 17% / 33%');
        break;
    }
  }

}
