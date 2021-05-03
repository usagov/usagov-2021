/**
 * @file
 * Attaches behaviors for the Clientside Validation jQuery module.
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Disable clientside validation for webforms.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformClientSideValidationNoValidation = {
    attach: function (context) {
      $(context)
        .find('form[data-webform-clientside-validation-novalidate]')
        .once('webformClientSideValidationNoValidate')
        .each(function () {
          $(this).validate().destroy();
        });
    }
  };

})(jQuery, Drupal);
