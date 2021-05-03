/**
 * @file
 * Paragraphs actions JS code for paragraphs actions button.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Handle event when "Add above" button is clicked.
   *
   * @param event
   *   Click event.
   */
  var clickHandler = function(event) {
    event.preventDefault();
    var $button = $(this);
    var $add_more_wrapper = $button.closest('table').siblings('.clearfix').find('.paragraphs-add-dialog');

    // Find delta for row without interference of unrelated table rows.
    var $anchorRow = $button.closest('tr');
    var delta = $anchorRow.parent().find('> .draggable').index($anchorRow);

    // Set delta before opening of dialog.
    $add_more_wrapper.parent().find('.paragraph-type-add-modal-delta').val(delta);

    Drupal.paragraphsAddModal.openDialog($add_more_wrapper, Drupal.t('Add above'));
  };

  /**
   * Process paragraph_AddAboveButton elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphsAddAboveButton = {
    attach: function (context, settings) {
      $('.paragraphs-dropdown-actions', context).once('paragraphs-add-above-button').each(function () {
        var $actions = $(this);
        if ($actions.closest('.paragraph-top').hasClass('add-above-on')) {
          var $button = $('<input class="paragraphs-dropdown-action paragraphs-dropdown-action--add-above button js-form-submit form-submit" type="submit" value="' + Drupal.t('Add above') + '">');
          // "Mousedown" is used since the original actions created by paragraphs
          // use the event "focusout" to hide the actions dropdown.
          $button.on('mousedown', clickHandler);

          $actions.prepend($button);
        }
      });
    }
  };

})(jQuery, Drupal);
