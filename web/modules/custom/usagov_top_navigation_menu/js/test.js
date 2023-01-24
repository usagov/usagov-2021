(function ($, Drupal, drupalSettings) {
  /**
   * @namespace
   */
  Drupal.behaviors.mymoduleAccessData = {
    attach: function (context) {
      var top_nav = drupalSettings.top_nav_menu;
      console.log(top_nav);
      let dicts = {};
      for (menu_item in top_nav) {
        var menu_path = (Object.keys(top_nav[menu_item])[0]).toString();
        var menu_id = Object.values(top_nav[menu_item])[0];
        dicts[menu_path] = menu_id;
      }

      let current_path = window.location.pathname;
      console.log(`HELLO FROM JS ${current_path}`);

      if (current_path in dicts) {
        console.log("YUP");
      } else {
        console.log("NOPE");
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
