<?php

/**
 * @file
 * Contains \Drupal\usa_orphaned_entities\Plugin\views\style
 */

 namespace Drupal\usa_orphaned_entities\Plugin\views\style;

 use Drupal\core\form\FormStateInterface;
 use Drupal\views\Plugin\views\style\StylePluginBase;
//  use Drupal\node\Entity\Node;

 /**
 * Style plugin to render a list of nodes with orphaned entity references.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "usa_orphaned_entities",
 *   title = @Translation("Orphaned Entities"),
 *   help = @Translation("Render a list of entities with entity references that have been orphaned."),
 *   theme = "views_view_orphaned_entities",
 *   display_types = { "normal" }
 * )
 */

 class OrphanedEntitiesReport extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['path'] = array('default' => 'usa_orphaned_entities');
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Path prefix for TARDIS links.
    $form['path'] = array(
      '#type' => 'textfield',
      '#title' => t('Link path'),
      '#default_value' => (isset($this->options['path'])) ? $this->options['path'] : 'tardis',
      '#description' => t('Path prefix for each TARDIS link, eg. example.com<strong>/tardis/</strong>2015/10.'),
    );

    // Month date format.
    $form['month_date_format'] = array(
      '#type' => 'textfield',
      '#title' => t('Month date format'),
      '#default_value' => (isset($this->options['month_date_format'])) ? $this->options['month_date_format'] : 'm',
      '#description' => t('Valid PHP <a href="@url" target="_blank">Date function</a> parameter to display months.', array('@url' => 'http://php.net/manual/en/function.date.php')),
    );

    // Whether month links should be nested inside year links.
    $options = array(
      1 => 'yes',
      0 => 'no',
    );
    $form['nesting'] = array(
      '#type' => 'radios',
      '#title' => t('Nesting'),
      '#options' => $options,
      '#default_value' => (isset($this->options['nesting'])) ? $this->options['nesting'] : 1,
      '#description' => t('Should months be nested inside years? <br />
        Example:
        <table style="width:100px">
          <thead>
              <th>Nesting</th>
              <th>No nesting</th>
          </thead>
          <tbody>
            <td>
              <ul>
                <li>2016
                  <ul>
                    <li>03</li>
                    <li>02</li>
                    <li>01</li>
                  </ul>
                </li>
              </ul>
            </td>
            <td>
              <ul>
                <li>2016/03</li>
                <li>2016/02</li>
                <li>2016/01</li>
              </ul>
            </td>
          </tbody>
        </table>
      '),
    );

    // Extra CSS classes.
    $form['classes'] = array(
      '#type' => 'textfield',
      '#title' => t('CSS classes'),
      '#default_value' => (isset($this->options['classes'])) ? $this->options['classes'] : 'view-tardis',
      '#description' => t('CSS classes for further customization of this TARDIS page.'),
    );
  }
 }
