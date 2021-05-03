/**
 * @file
 * JavaScript behaviors for Webform Export/Import Test module.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Set import URL and submit the form.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformSubmissionExportImportTest = {
    attach: function (context) {
      $('#edit-import-url--description a', context)
        .once('webform-export-import-test')
        .on('click', function () {
          $('#edit-import-url').val(this.href);
          $('#webform-submission-export-import-upload-form').trigger('submit');
          return false;
        });
    }
  };

})(jQuery, Drupal);
