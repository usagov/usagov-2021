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

      if (current_path in dicts) {
        let listItem;
        listItem = document.getElementById(dicts[current_path]);
        let aElem = listItem.getElementsByTagName("a")[0];
        aElem.setAttribute("href", "#skip-to-h1");
        aElem.setAttribute("aria-current", "page");
        aElem.classList.add("currentMenuItem");
      } else {
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
