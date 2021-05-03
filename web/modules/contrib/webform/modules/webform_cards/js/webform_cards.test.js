/**
 * @file
 * JavaScript behaviors for webform cards.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Initialize webform cards test.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformCardsTest = {
    attach: function (context) {
      $('.js-webform-card-test-submit-form', context).once('webform-card-test-submit-form').on('click', function () {
        var selector = $(this).attr('href').replace('#', '.') + ' .webform-button--submit';
        $(selector).trigger('click');
        return false;
      });
    }
  };

})(jQuery, Drupal);
