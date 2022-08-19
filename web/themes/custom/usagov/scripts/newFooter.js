window.addEventListener("resize", checkForMobile);
window.addEventListener("load", checkForMobile);

var EXPANDED = "aria-expanded";
var CONTROLS = "aria-controls";
var HIDDEN = "hidden";

function showLinks(event) {
  (function ($) {
    if (window.innerWidth <= 500) {
      // get current selected button settings for later
      var selected = event.target;
      var selectedWasOpen = selected.getAttribute(EXPANDED) === "true";

      // change(close) other selected settings
      $(".usa-gov-footer__primary-link").each(function () {
        var button = $(this);
        button.attr(EXPANDED, "false");

        var isExpanded = button.attr(EXPANDED) === "true";

        var buttonId = button.attr(CONTROLS);
        var buttonControls = document.getElementById(buttonId);

        if (isExpanded) {
          buttonControls.removeAttribute(HIDDEN);
        } else {
          buttonControls.setAttribute(HIDDEN, "");
        }
      });

      // change current selected settings
      selected.setAttribute(EXPANDED, !selectedWasOpen);
      var selectedIsOpen = selected.getAttribute(EXPANDED) === "true";

      var selectedId = selected.getAttribute(CONTROLS);
      var selectedControls = document.getElementById(selectedId);
      if (selectedIsOpen) {
        selectedControls.removeAttribute(HIDDEN);
      } else {
        selectedControls.setAttribute(HIDDEN, "");
      }
    }
  })(jQuery);
}

function checkForMobile() {
  (function ($) {
    // isMobile uses same check that telephone numbers use to set to links
    var isMobile = window.innerWidth <= 500 ? true : false;
    var newElementType = isMobile ? "button" : "h3";

    $(".usa-gov-footer__primary-link").each(function () {
      var primaryLink = $(this);
      var newElement = document.createElement(newElementType);

      newElement.innerText = primaryLink.text();

      // hardcoded because we know what it is and this should only be used until update from uswds
      newElement.setAttribute("class", "usa-gov-footer__primary-link");
      newElement.classList.toggle(
        "usa-gov-footer__primary-link--button",
        isMobile
      );

      if (isMobile) {
        var menuId = "usa-footer-menu-list-".concat(
          Math.floor(Math.random() * 100000)
        );
        newElement.setAttribute(CONTROLS, menuId);
        newElement.setAttribute(EXPANDED, "false");
        primaryLink.next().attr("id", menuId);
        newElement.setAttribute("type", "button");

        var primaryLinkId = newElement.getAttribute(CONTROLS);
        var primaryLinkControls = document.getElementById(primaryLinkId);

        primaryLinkControls.setAttribute(HIDDEN, "");
        newElement.addEventListener("click", showLinks);
      } else {
        primaryLink.next().removeAttr(HIDDEN);
      }

      primaryLink.after(newElement);
      primaryLink.remove();
    });
  })(jQuery);
}
