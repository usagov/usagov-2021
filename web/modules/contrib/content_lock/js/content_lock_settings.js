/**
 * @file
 * Defines Javascript behaviors for the content lock module.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Behaviors for the content lock settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the content lock settings form behavior.
   */
  Drupal.behaviors.contentLockSettings = {
    attach: function (context, settings) {
      $('.content-lock-entity-settings[value="*"]', context)
        .once('content-lock-settings')
        // Init
        .each(Drupal.behaviors.contentLockSettings.toggleBundles)
        // Change
        .change(Drupal.behaviors.contentLockSettings.toggleBundles);

      $('.content-lock-entity-types input', context)
        .once('content-lock-settings')
        .change(Drupal.behaviors.contentLockSettings.toggleEntityType);
    },

    /**
     * Toggle the bundle rows if all option is changed.
     */
    toggleBundles: function () {
      var all_bundles_selected = this.checked;
      $(this).closest('tbody').find('.bundle-settings').each(function () {
        // If the "All bundles" checkbox is checked then uncheck and disable
        // all other options.
        var $checkbox = $('[type="checkbox"]', this);
        if (all_bundles_selected) {
          $checkbox
            .prop('disabled', true)
            .prop('checked', false)
            .addClass('is-disabled');
          $(this).hide();
        }
        else {
          $checkbox
            .prop('disabled', false)
            .removeClass('is-disabled');
          $(this).show();
        }
      });
    },

    /**
     * Remove all selected bundles or auto select all when changing an entity type.
     */
    toggleEntityType: function () {
      var entity_type_id = $(this).val();
      if (this.checked) {
        $('.' + entity_type_id + ' .content-lock-entity-settings[value="*"]')
          .prop('checked', true)
          .trigger('change');
      }
      else {
        $('.' + entity_type_id + ' .content-lock-entity-settings')
          .prop('checked', false);
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
