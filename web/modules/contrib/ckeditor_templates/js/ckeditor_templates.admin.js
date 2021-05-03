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
      $('[data-ckeditor-plugin-id="templates"]').drupalSetSummary(function (context) {
        var templatePathValue = $('input[name="editor[settings][plugins][templates][template_path]').val();
        var replaceContentValue = $('input[name="editor[settings][plugins][templates][replace_content]').is(':checked');

        var templatePathOutput = templatePathValue ? 'Template file overridden.' : 'Default or theme template file.';
        var replaceContentOutput = replaceContentValue ? '"Replace content" checked' : '"Replace content" unchecked';

        return templatePathOutput + '<br />' + replaceContentOutput;
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
