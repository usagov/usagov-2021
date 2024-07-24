// Simple function to add/remove checked class in 2024 wizard.
(function($, Drupal) {
  "use strict";

  Drupal.behaviors.wizardChecked = {
    "attach": function(context, settings) {
      $(context).find(".usa-radio__label").on("click", function() {
        $(".usa-radio__label").removeClass("checked");
        $(this).addClass("checked");
      });
    }
  };
})(jQuery, Drupal);
