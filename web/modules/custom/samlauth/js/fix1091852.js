(function ($, Drupal, window) {
  Drupal.behaviors.samlauthfix = {
    attach: function (context, settings) {
      // Copy states.js but reprocess only the AJAX additions. It can make all
      // of them invisible.
      var $states = $(context).find('[id^=edit-idp-certs-]');
      var il = $states.length;

      var _loop = function _loop(i) {
        var config = JSON.parse($states[i].getAttribute('data-drupal-states'));
        Object.keys(config || {}).forEach(function (state) {
          var d = new Drupal.states.Dependent({
            element: $($states[i]),
            state: Drupal.states.State.sanitize(state),
            constraints: config[state]
          });
          // This is basically the 1091852-131 patch. I hope.
          d.reevaluate();
        });
      };

      for (var i = 0; i < il; i++) {
        _loop(i);
      }
    }
  };
})(jQuery, Drupal, window);
