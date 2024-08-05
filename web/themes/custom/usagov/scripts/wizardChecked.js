// Simple function to add/remove checked class in 2024 wizard.
(function($, Drupal) {
  "use strict";

  Drupal.behaviors.wizardChecked = {
    "attach": function(context, settings) {
      $(context).find(".usa-radio__label, .usa-radio__input").on("click, focus", function(e) {
        $(".usa-radio__label").removeClass("checked");
        $(this).next(".usa-radio__label").addClass("checked");
      });
    }
  };
})(jQuery, Drupal);
