/**
 * Improve the accessibility of pages that use recaptcha.
 * Adding a tabindex attribute to an iframe in the recaptcha for the correct tabbing order
 * Used on pages: report a website issue in English and Spanish
 */


// checks to see if the page has loaded
function winLoad(callback) {
    "use strict";
  if (document.readyState === 'complete') {
    callback();
  }
    else {
    window.addEventListener("load", callback);
  }
}

// adds the attrubute to the iframe once the page is loaded
winLoad(function() {
    "use strict";
    const iframe = document.querySelector("iframe");
    iframe.setAttribute("tabindex", "1");
});
