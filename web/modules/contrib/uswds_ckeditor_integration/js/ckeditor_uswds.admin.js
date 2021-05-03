/**
 * @file
 * CKEditor 'templates' plugin admin behavior.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Provides the summary for the "templates" plugin settings vertical tab.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behaviour to the "templates" settings vertical tab.
   */
  Drupal.behaviors.ckeditorTemplatesSettingsSummary = {
    attach: function () {
      $('[data-ckeditor-plugin-id="uswds_table"]').drupalSetSummary(function (context) {
        var replaceContentValue = $('input[name="editor[settings][plugins][uswds_table][override_table]').is(':checked');

        return replaceContentValue ? '"Use USWDS Table:" checked' : '"Use UWDS Table:" unchecked';
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
