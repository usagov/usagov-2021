// Allow users to deselect a radio by pressing "spacebar".
(function($, Drupal) {
  "use strict";

  Drupal.behaviors.wizardChecked = {
    "attach": function(context, settings) {
      $(context).find(".usa-radio__label, .usa-radio__input").on('keydown', function(e) {
        // 32 is spacebar.
        if (e.keyCode === 32) {
          // Don't do any weird scrolling.
          e.preventDefault();
          // Do the thing.
          let wasChecked = $(this).prop('checked');
          $(this).prop('checked', !wasChecked);
        }
      });
    }
  };
})(jQuery, Drupal);
