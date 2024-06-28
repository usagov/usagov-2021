(function($, Drupal) {
  Drupal.behaviors.wizardChecked = {
    attach: function(context, settings) {
      // Code to be run on page load, and
      // on ajax load. It's equivalent to jQuery.ready()
      $(context).find(".usa-radio__label").on( "click", function() {
        $(".usa-radio__label").removeClass('checked');
          $(this).addClass('checked');
        });
    }
  };
})(jQuery, Drupal);
