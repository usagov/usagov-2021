window.addEventListener("resize", checkForMobile);
window.addEventListener("load", checkForMobile);

var EXPANDED = "aria-expanded";
var CONTROLS = "aria-controls";
var HIDDEN = "hidden";

function showLinks(event) {
  "use strict";
  if (window.innerWidth < 480) {
    // get current selected button settings for later
    var selected = event.target;
    var selectedWasOpen = selected.getAttribute(EXPANDED) === "true";

    // change(close) other selected settings
    document
      .querySelectorAll(".usa-gov-footer__primary-link")
      .forEach(function (button) {
        button.setAttribute(EXPANDED, "false");

        var buttonId = button.getAttribute(CONTROLS);
        var buttonControls = document.getElementById(buttonId);

        buttonControls.setAttribute(HIDDEN, "");
      });

    // change current selected settings
    selected.setAttribute(EXPANDED, !selectedWasOpen);
    var selectedIsOpen = selected.getAttribute(EXPANDED) === "true";

    var selectedId = selected.getAttribute(CONTROLS);
    var selectedControls = document.getElementById(selectedId);
    if (selectedIsOpen) {
      selectedControls.removeAttribute(HIDDEN);
    }
 else {
      selectedControls.setAttribute(HIDDEN, "");
    }
  }
}

function checkForMobile() {
  "use strict";
  // isMobile uses same check that telephone numbers use to set to links
  var isMobile = window.innerWidth < 480 ? true : false;
  var newElementType = isMobile ? "button" : "h3";

  document
    .querySelectorAll(".usa-gov-footer__primary-link")
    .forEach(function (primaryLink) {
      var newElement = document.createElement(newElementType);
      newElement.textContent = primaryLink.textContent;

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
        primaryLink.nextElementSibling.setAttribute("id", menuId);
        newElement.setAttribute("type", "button");
        var primaryLinkId = newElement.getAttribute(CONTROLS);
        var primaryLinkControls = document.getElementById(primaryLinkId);
        primaryLinkControls.setAttribute(HIDDEN, "");
        newElement.addEventListener("click", showLinks);
      }
 else {
        primaryLink.nextElementSibling.removeAttribute(HIDDEN);
      }
      primaryLink.after(newElement);
      primaryLink.remove();
    });
}
