/**
 * @file
 * Attaches behaviors for the Clientside Validation jQuery module.
 */

(function ($, drupalSettings) {

  'use strict';

  // Disable clientside validation for webforms submitted using Ajax.
  // This prevents Computed elements with Ajax from breaking.
  // @see \Drupal\clientside_validation_jquery\Form\ClientsideValidationjQuerySettingsForm
  drupalSettings.clientside_validation_jquery.validate_all_ajax_forms = 0;

  /**
   * Add .cv-validate-before-ajax to all webform submit buttons.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformClientSideValidationAjax = {
    attach: function (context) {
      $('form.webform-submission-form .form-actions :submit:not([formnovalidate])')
        .once('webform-clientside-validation-ajax')
        .addClass('cv-validate-before-ajax');
    }
  };

  $(document).once('webform_cvjquery').on('cv-jquery-validate-options-update', function (event, options) {
    options.errorElement = 'strong';
    options.showErrors = function (errorMap, errorList) {
      // Show errors using defaultShowErrors().
      this.defaultShowErrors();

      // Add '.form-item--error-message' class to all errors.
      $(this.currentForm).find('strong.error').addClass('form-item--error-message');

      // Move all radios, checkbox, and datelist errors to parent container.
      $(this.currentForm).find('.form-checkboxes, .form-radios, .form-type-datelist .container-inline, .form-type-tel, .webform-type-webform-height .form--inline').each(function () {
        var $container = $(this);
        var $errorMessages = $container.find('strong.error.form-item--error-message');
        $errorMessages.insertAfter($container);
      });

      // Move error after field suffix.
      $(this.currentForm).find('strong.error.form-item--error-message ~ .field-suffix').each(function () {
        var $fieldSuffix = $(this);
        var $errorMessages = $fieldSuffix.prev('strong.error.form-item--error-message');
        $errorMessages.insertAfter($fieldSuffix);
      });
    };
  });

})(jQuery, drupalSettings);
