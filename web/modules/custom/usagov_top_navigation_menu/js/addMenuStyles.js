(function ($, Drupal, drupalSettings) {
  /**
   * @namespace
   */
  Drupal.behaviors.usagovtopnavigationmenuAddMenuStyles = {
    attach: function (context) {
      var top_nav = drupalSettings.top_nav_menu;
      // console.log(top_nav);
      let dicts = {};
      for (menu_item in top_nav) {
        var menu_path = Object.keys(top_nav[menu_item])[0].toString();
        var menu_id = Object.values(top_nav[menu_item])[0];
        dicts[menu_path] = menu_id;
      }

      let current_path = window.location.pathname;

      if (current_path in dicts) {
        let listItem;
        listItem = document.getElementById(dicts[current_path]);
        //check to make sure the description was not left null
        if (listItem) {
          let aElem = listItem.getElementsByTagName("a")[0];
          // console.log(`the list item is: ${aElem}`);
          aElem.setAttribute("href", "#skip-to-h1");
          if (document.documentElement.lang == "es") {
            var p = document.createElement('p');
            p.innerHTML = 'esta página';
            p.classList.add('usa-sr-only');
            document.getElementById(listItem.getAttribute("id")).firstElementChild.prepend(p);
          }
          else {
            aElem.setAttribute("aria-current", "page");
          }
          aElem.classList.add("currentMenuItem");

        } else {
          // console.error("Top nav description was left empty in cms");
        }
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
