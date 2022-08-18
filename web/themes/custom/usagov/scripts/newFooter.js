window.addEventListener("resize", checkForMobile);
window.addEventListener("load", checkForMobile);

var EXPANDED = "aria-expanded";
var CONTROLS = "aria-controls";
var HIDDEN = "hidden";

function showLinks(event) {
  (function ($) {
    if (window.innerWidth <= 500) {
      var button = event.target;

      var isOpen = button.getAttribute(EXPANDED) === "true";
      $(".usa-gov-footer__primary-link").each(function () {
        $(this).attr(EXPANDED, "false");
      });

      button.setAttribute(EXPANDED, !isOpen);
      var isOpen = button.getAttribute(EXPANDED) === "true";

      var id = button.getAttribute(CONTROLS);
      var acontrols = document.getElementById(id);
      if (isOpen) {
        acontrols.removeAttribute(HIDDEN);
      } else {
        acontrols.setAttribute(HIDDEN, "");
      }
    }
  })(jQuery);
}

function checkForMobile() {
  (function ($) {
    // setting isMobile with same chack that telephone numbers use to set to links
    var isMobile = window.innerWidth <= 500 ? true : false;

    var newElementType = isMobile ? "button" : "h3";

    $(".usa-gov-footer__primary-link").each(function () {
      var newElement = document.createElement(newElementType);

      // hardcoded because we know what it is and this should only be used until update from uswds
      newElement.setAttribute("class", "usa-gov-footer__primary-link");

      newElement.classList.toggle(
        "usa-gov-footer__primary-link--button",
        isMobile
      );

      newElement.innerText = $(this).text();

      if (isMobile) {
        var menuId = "usa-footer-menu-list-".concat(
          Math.floor(Math.random() * 100000)
        );
        newElement.setAttribute(CONTROLS, menuId);
        newElement.setAttribute(EXPANDED, "false");
        $(this).next().attr("id", menuId);
        newElement.setAttribute("type", "button");

        var id = newElement.getAttribute(CONTROLS);
        var acontrols = document.getElementById(id);

        acontrols.setAttribute(HIDDEN, "");
        newElement.addEventListener("click", showLinks);
      } else {
        $(this).next().removeAttr(HIDDEN);
      }

      $(this).after(newElement);
      $(this).remove();
    });
  })(jQuery);
}
