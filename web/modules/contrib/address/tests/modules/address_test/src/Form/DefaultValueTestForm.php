<?php

namespace Drupal\address_test\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Used to test the address default value handling inside complex ajax forms.
 */
class DefaultValueTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'address_default_value_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = Html::getUniqueId('address-test-form-ajax-wrapper');
    $selected_payment_method = 'credit_card';
    $user_input = $form_state->getUserInput();
    if (isset($user_input['payment_method'])) {
      $selected_payment_method = $user_input['payment_method'];
    }

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $form['payment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment method'),
      '#options' => [
        'credit_card' => $this->t('Credit card'),
        'cash_on_delivery' => $this->t('Cash on delivery'),
      ],
      '#default_value' => $selected_payment_method,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $wrapper_id,
      ],
      '#required' => TRUE,
    ];
    $form[$selected_payment_method] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Billing information'),
    ];
    // The address being nested under $selected_payment_method means that
    // on #ajax there will be no matching address form input, cause the payment
    // method has changed. This should result in the default value being shown.
    $form[$selected_payment_method]['address'] = [
      '#type' => 'address',
      '#default_value' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'Mountain View',
        'postal_code' => '94043',
        'address_line1' => '1098 Alta Ave',
        'organization' => 'Google Inc.',
        'given_name' => 'John',
        'family_name' => 'Smith',
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $payment_method = $values['payment_method'];
    $address = $values[$payment_method]['address'];

    $this->messenger()->addMessage($this->t('The street is "@street" and the country code is @country_code.', [
      '@street' => $address['address_line1'],
      '@country_code' => $address['country_code'],
    ]));
  }

}
