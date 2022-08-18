window.addEventListener("resize", checkForMobile);
window.addEventListener("load", checkForMobile);

var CONTROLS = "aria-controls";
var HIDDEN = "hidden";

function showLinks(event) {
  (function ($) {
    if (window.innerWidth <= 500) {
      var button = event.target;

      var isOpen = button.getAttribute("aria-expanded") === "true";
      $(".usa-gov-footer__primary-link").each(function (currentElement) {
        $(this).attr("aria-expanded", "false");
      });

      button.setAttribute("aria-expanded", !isOpen);
      var isOpen = button.getAttribute("aria-expanded") === "true";

      var id = button.getAttribute(CONTROLS);
      var controls = document.getElementById(id);
      if (isOpen) {
        controls.removeAttribute(HIDDEN);
      } else {
        controls.setAttribute(HIDDEN, "");
      }
    }
  })(jQuery);
}

function checkForMobile() {
  (function ($) {
    // console.log("Hello world from js!");

    // console.log(window.innerWidth);
    // setting isMobile with same chack that telephone numbers use to set to links beta uses 500
    var isMobile = window.innerWidth <= 500 ? true : false;

    var newElementType = isMobile ? "button" : "h3";

    $(".usa-gov-footer__primary-link").each(function (currentElement) {
      // console.log($(this));

      var newElement = document.createElement(newElementType);

      // hardcoded because we know what it is
      newElement.setAttribute("class", "usa-gov-footer__primary-link");

      newElement.classList.toggle(
        "usa-gov-footer__primary-link--button",
        isMobile
      );

      newElement.innerText = $(this).text();

      // console.log(newElement);
      // console.log("created new element");
      if (isMobile) {
        var menuId = "usa-footer-menu-list-".concat(
          Math.floor(Math.random() * 100000)
        );
        newElement.setAttribute("aria-controls", menuId);
        newElement.setAttribute("aria-expanded", "false");
        $(this).next().attr("id", menuId);
        newElement.setAttribute("type", "button");

        var id = newElement.getAttribute(CONTROLS);
        var controls = document.getElementById(id);

        controls.setAttribute(HIDDEN, "");
        newElement.addEventListener("click", showLinks);
      } else {
        $(this).next().removeAttr(HIDDEN);
      }

      $(this).after(newElement);
      $(this).remove();
    });
  })(jQuery);
}
