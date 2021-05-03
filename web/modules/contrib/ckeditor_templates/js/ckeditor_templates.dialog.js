/**
 * @file
 * Override ckeditor template dialog style.
 */

(function (CKEDITOR) {

  'use strict';

  CKEDITOR.on('dialogDefinition', function (ev) {
    var dialogName = ev.data.name;
    var dialog = ev.data.definition.dialog;

    if (dialogName === 'templates') {
      dialog.on('show', function () {
        var dialogElement = dialog.getElement().getFirst();
        dialogElement.addClass('cke_templates_dialog');
      });
    }
  });

})(CKEDITOR);
