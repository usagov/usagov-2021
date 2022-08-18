window.addEventListener("resize", checkForMobile);
window.addEventListener("load", checkForMobile);

function showLinks(event) {
  (function ($) {
    var button = event.target;
    console.log("going to show links of " + button);

    var isOpen = button.getAttribute("aria-expanded") === "true";
    console.log("isOpen " + isOpen);
    $(".usa-gov-footer__primary-link").each(function (currentElement) {
      $(this).attr("aria-expanded", "false");
    });
    button.setAttribute("aria-expanded", !isOpen);
    console.log("aria-expanded " + button.getAttribute("aria-expanded"));
    var isOpen = button.getAttribute("aria-expanded") === "true";
    console.log("isOpen " + isOpen);
  })(jQuery);
}

function checkForMobile() {
  (function ($) {
    console.log("Hello world from js!");

    console.log(window.innerWidth);
    // setting isMobile with same chack that telephone numbers use to set to links
    // beta uses 500
    var isMobile = window.innerWidth <= 950 ? true : false;
    console.log(isMobile);

    var newElementType = isMobile ? "button" : "h3";

    $(".usa-gov-footer__primary-link").each(function (currentElement) {
      console.log($(this));
      console.log($(this).next());

      var newElement = document.createElement(newElementType);

      // hardcoded because we know what it is
      newElement.setAttribute("class", "usa-gov-footer__primary-link");

      newElement.classList.toggle(
        "usa-gov-footer__primary-link--button",
        isMobile
      );

      newElement.innerText = $(this).text();

      console.log(newElement);
      console.log("created new element");
      if (isMobile) {
        var menuId = "usa-footer-menu-list-".concat(
          Math.floor(Math.random() * 100000)
        );
        newElement.setAttribute("aria-controls", menuId);
        newElement.setAttribute("aria-expanded", "false");
        $(this).next().attr("id", menuId);
        newElement.setAttribute("type", "button");
        newElement.addEventListener("click", showLinks);
      }

      $(this).after(newElement);
      $(this).remove();
    });
  })(jQuery);
}
