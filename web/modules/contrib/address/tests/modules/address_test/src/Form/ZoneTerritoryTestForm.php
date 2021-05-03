<?php

namespace Drupal\address_test\Form;

use CommerceGuys\Addressing\Zone\ZoneTerritory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Used to test the address_zone_territory form element.
 */
class ZoneTerritoryTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'address_zone_territory_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['territory'] = [
      '#type' => 'address_zone_territory',
      '#default_value' => 'AG',
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $territory = new ZoneTerritory($values['territory']);

    $this->messenger()->addMessage($this->t('Submitted: @country_code / @administrative_area / @included_postal_codes / @excluded_postal_codes.', [
      '@country_code' => $territory->getCountryCode(),
      '@administrative_area' => $territory->getAdministrativeArea(),
      '@included_postal_codes' => $territory->getIncludedPostalCodes(),
      '@excluded_postal_codes' => $territory->getExcludedPostalCodes(),
    ]));
  }

}
