(function ($, Drupal, drupalSettings) {
  /**
   * @namespace
   */
  Drupal.behaviors.mymoduleAccessData = {
    attach: function (context) {
      var data = drupalSettings.mymoduleComputedData.title;
      console.log("HELLO FROM JS");
      console.log(data);
    },
  };
})(jQuery, Drupal, drupalSettings);
