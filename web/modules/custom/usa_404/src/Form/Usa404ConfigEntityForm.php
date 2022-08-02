<?php

namespace Drupal\usa_404\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Custom4xxConfigEntityForm.
 *
 * @package Drupal\custom_4xx_pages\Form
 */
class Custom4xxConfigEntityForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $custom4xx_config_entity = $this->entity;

    $form['notes'] = [
      '#type' => 'markup',
      '#markup' => '<h4>Map Designated 404 Page to Specific Languare Route</h4>',
    ];
    

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      // '#default_value' => $custom4xx_config_entity->label(),
      '#description' => $this->t("The label is purely up to the site owner, to keep track of redirects from a human readable perspective."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      // '#default_value' => $custom4xx_config_entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\custom_4xx_pages\Entity\Custom4xxConfigEntity::load',
      ],
      // '#disabled' => !$custom4xx_config_entity->isNew(),
    ];

    // $form['custom_4xx_type'] = [
    //   '#type' => 'select',
    //   '#title' => '4xx Type',
    //   '#options' => [
    //     '403' => '403',
    //     '404' => '404',
    //     '401' => '401',
    //   ],
    //   '#description' => $this->t("
    //     <ul>
    //       <li>403 - Access Denied</li>
    //       <li>404 - Not Found</li>
    //       <li>401 - Unauthorized</li>
    //     </ul>"),
    //   '#default_value' => $custom4xx_config_entity->get('custom_4xx_type'),
    //   '#required' => TRUE,
    // ];

    // $form['custom_403_path_to_apply'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Path To Apply To'),
    //   '#maxlength' => 255,
    //   '#default_value' => $custom4xx_config_entity->get('custom_403_path_to_apply'),
    //   '#description' => $this->t("What path should this apply to? Use wildcard * to support a nested path, e.g. /foo/bar/*"),
    //   '#required' => TRUE,
    // ];

    // $form['custom_403_page_path'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Path To Custom 403 Page'),
    //   '#maxlength' => 255,
    //   '#default_value' => $custom4xx_config_entity->get('custom_403_page_path'),
    //   '#description' => $this->t("Enter the path of the custom 403 page. The path can be any entity. It will render this entities contents in place of the standard 403 contents."),
    //   '#required' => TRUE,
    // ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  // public function save(array $form, FormStateInterface $form_state) {
  //   $custom4xx_config_entity = $this->entity;
  //   $custom4xx_config_entity->custom_403_path_to_apply = $form_state->getValue('custom_403_path_to_apply');
  //   $custom4xx_config_entity->custom_403_page_path = $form_state->getValue('custom_403_page_path');
  //   $custom4xx_config_entity->custom_4xx_type = $form_state->getValue('custom_4xx_type');
  //   $status = $custom4xx_config_entity->save();

  //   switch ($status) {
  //     case SAVED_NEW:
  //       drupal_set_message($this->t('Created the %label Custom 4xx Configuration Item.', [
  //         '%label' => $custom4xx_config_entity->label(),
  //       ]));
  //       break;

  //     default:
  //       drupal_set_message($this->t('Saved the %label Custom 4xx Configuration Item.', [
  //         '%label' => $custom4xx_config_entity->label(),
  //       ]));
  //   }
  //   $form_state->setRedirectUrl($custom4xx_config_entity->toUrl('collection'));
  // }

}
