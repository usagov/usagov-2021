/**
 * @file
 * JavaScript behaviors for signature pad integration.
 */

(function ($, Drupal, debounce) {

  'use strict';

  // @see https://github.com/szimek/signature_pad#options
  Drupal.webform = Drupal.webform || {};
  Drupal.webform.signaturePad = Drupal.webform.signaturePad || {};
  Drupal.webform.signaturePad.options = Drupal.webform.signaturePad.options || {};

  /**
   * Initialize signature element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformSignature = {
    attach: function (context) {
      if (!window.SignaturePad) {
        return;
      }

      $(context).find('input.js-webform-signature').once('webform-signature').each(function () {
        var $input = $(this);
        var value = $input.val();
        var $wrapper = $input.parent();
        var $canvas = $wrapper.find('canvas');
        var $button = $wrapper.find(':button, :submit');
        var canvas = $canvas[0];

        var refresh = function () {
          // Set dimensions.
          $canvas.attr('width', $wrapper.width());
          $canvas.attr('height', $wrapper.width() / 3);
          // Set signature.
          signaturePad.clear();
          var value = $input.val();
          if (value) {
            signaturePad.fromDataURL(value);
          }
        };

        // Initialize signature canvas.
        var options = $.extend({
          onEnd: function () {
            $input.val(signaturePad.toDataURL());
          }
        }, Drupal.webform.signaturePad.options);
        var signaturePad = new SignaturePad(canvas, options);

        // Disable the signature pad when input is disabled or readonly.
        if ($input.is(':disabled') || $input.is('[readonly]')) {
          signaturePad.off();
          $button.hide();
        }

        // Set resize handler.
        $(window).on('resize', debounce(refresh, 10));

        // Set reset handler.
        $button.on('click', function () {
          signaturePad.clear();
          $input.val('');
          this.trigger('blur');
          return false;
        });

        // Input onchange clears signature pad if value is empty.
        // Onchange events handlers are triggered when a webform is
        // hidden or shown.
        // @see webform.states.js
        // @see triggerEventHandlers()
        $input.on('change', function () {
          setTimeout(refresh, 1);
        });

        // Turn signature pad off/on when the input
        // is disabled/readonly/enabled.
        // @see webform.states.js
        $input.on('webform:disabled webform:readonly', function () {
          if ($input.is(':disabled') || $input.is('[readonly]')) {
            signaturePad.off();
            $button.hide();
          }
          else {
            signaturePad.on();
            $button.show();
          }
        });

        // Make sure that the signature pad is initialized.
        setTimeout(refresh, 1);
      });
    }
  };

})(jQuery, Drupal, Drupal.debounce);
