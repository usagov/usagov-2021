function timestamp() {
  "use strict";
  var response = $("#g-recaptcha-response");
  if (response || response.value.trim() === "") {
    var elems = JSON.parse(
      document.getElementsByName("captcha_settings")[0].value
    );
    elems["ts"] = JSON.stringify(new Date().getTime());
    document.getElementsByName("captcha_settings")[0].value =
      JSON.stringify(elems);
  }
}

setInterval(timestamp, 500);

/**
 * This function displays all the error elements related to the given reCaptcha. Such as the error message
 * on top of the reCaptcha, the error style, the error message in the alert box, the left padding,
 * and side line.
 * @returns {Element} recaptcha that you want to display.
 */
function showReCaptchaError(reCaptchaContainer) {
  "use strict";

  if ($("html").attr("lang") === "en") {
    // Adds an english error message before the captcha box.
    $('<span class="err-label usa-error recaptcha-error-message" tabindex="0">Fill out the reCaptcha</span>')
    .insertBefore(reCaptchaContainer.querySelector(".recaptcha-alignment"));
  }
  else {
    // Adds a spanish error message before the captcha box.
    $('<span class="err-label usa-error recaptcha-error-message" tabindex="0">Complete el reCaptcha</span>')
    .insertBefore(reCaptchaContainer.querySelector(".recaptcha-alignment"));
  }
  // Adds the error outline to the reCaptcha box.
  reCaptchaContainer.querySelector(".recaptcha-outline-padding").classList.add("usa-user-error");

  var alertBoxText = document.querySelector("#alert_error_recaptcha_large");

  if (reCaptchaContainer.id === "recaptcha-small-container") {
    alertBoxText = document.querySelector("#alert_error_recaptcha_small");
  }
  // Makes the reCaptcha error text visible in the alert box.

  alertBoxText ? alertBoxText.classList.remove("usa-error--alert") : "";
  // Adds left padding from recaptcha.
  reCaptchaContainer.classList.add("usa-form-spacing", "usa-border-error");
  // Aligns the reCaptcha to the left when it has an error.
  reCaptchaContainer.querySelector(".recaptcha-alignment").style.justifyContent = "left";

}

/**
 * This function hides all the error elements related to the given reCaptcha. Such as the error message
 * on top of the reCaptcha, the error style, the error message in the alert box, the left padding,
 * and side line.
 * @returns {Element} recaptcha that you want to hide.
 */
function hideReCaptchaError(reCaptchaContainer) {
  "use strict";

  // Removes error messages from the reCaptcha
  var reCaptchaErrorMessage = reCaptchaContainer.querySelector(".recaptcha-error-message");
  reCaptchaErrorMessage ? reCaptchaErrorMessage.remove() : "";

  // Removes the error style from the reCaptcha.
  var reCaptchaErrorStyle = reCaptchaContainer.querySelector(".recaptcha-outline-padding");
  reCaptchaErrorStyle ? reCaptchaErrorStyle.classList.remove("usa-user-error") : "";
  var reCaptchaErrorLabel = reCaptchaContainer.querySelector(".recaptcha-error-message");
  reCaptchaErrorLabel ? reCaptchaErrorLabel.remove() : "";

  var alertBoxText = document.querySelector("#alert_error_recaptcha_large");

  if (reCaptchaContainer.id === "recaptcha-small-container") {
    alertBoxText = document.querySelector("#alert_error_recaptcha_small");
  }

  // Makes the reCaptcha error text invisible in the alert box.
  alertBoxText.classList.add("usa-error--alert");

  // Removes left padding from recaptcha.
  reCaptchaContainer ? reCaptchaContainer.classList.remove("usa-form-spacing", "usa-border-error") : "";
  // Aligns the reCaptcha to the center when it doesn't have an error.
  reCaptchaContainer.querySelector(".recaptcha-alignment").style.justifyContent = "center";
  // Removes the side line without spaces.
  var mainErrorBorder = reCaptchaContainer.querySelector("#error-border");
  mainErrorBorder ? mainErrorBorder.classList.remove("usa-main-border-error") : "";
}

/**
 * This function validates if the reCaptcha response and adds the error style if needed.
 * @returns {boolean} indicates whether the reCaptcha is checked or not.
 */
function reCaptchaValidation() {
  "use strict";

  // reCaptcha Validation
  var captchaValidationResult = true;
  // Screen width to validate the reCaptcha
  var screenWidth = window.innerWidth;

  var reCaptchaSmallContainer = document.querySelector("#recaptcha-small-container");
  var reCaptchaLargeContainer = document.querySelector("#recaptcha-large-container");

  if (screenWidth >= 500) {
    hideReCaptchaError(reCaptchaSmallContainer);
    // Check if reCaptcha is checked on large devices and if it already has the error style.
    if (grecaptcha.getResponse(0).length === 0) {
      if (!reCaptchaLargeContainer.querySelector("span.recaptcha-error-message")) {
        showReCaptchaError(reCaptchaLargeContainer);
        captchaValidationResult = false;
      }
    }
    else {
      hideReCaptchaError(reCaptchaLargeContainer);
    }
  }
  else {
    hideReCaptchaError(reCaptchaLargeContainer);
    // Check if reCaptcha is checked on small devices and if it already has the error style.
    if (grecaptcha.getResponse(1).length === 0) {
      if (!reCaptchaSmallContainer.querySelector("span.recaptcha-error-message")) {
        showReCaptchaError(reCaptchaSmallContainer);
        captchaValidationResult = false;
      }
    }
    else {
      hideReCaptchaError(reCaptchaSmallContainer);
    }
  }

  return captchaValidationResult;
}

/**
 * This function validates if the text entered is an email.
 * @param {string} email - the text to be validated
 * @returns {boolean} indicates whether the input text is an email or not.
 */
function validateEmail(email) {
  "use strict";
  var re = /\S+@\S+\.\S+/; // This is a very simple regex for email
  return re.test(email);
}

/**
 * This function has the objective of checking if the fields (Name, Email, Describe the issue)
 * are valid, and if invalid, makes error messages and error styling visible.
 * @returns {boolean} indicates if all the fields are valid or not.
 */
function fieldValidation() {
  "use strict";

  var noErrors = true;

  // Iterate through all fields.
  $(".required").each(function () {

    var input = $(this).find("input,textarea"); // Current field
    var errorId = "error_" + input.attr("id");
    var alertErrorId = "alert_" + errorId; // Id of field error text in alert box
    var fieldElement = $("#" + input.attr("id"))[0];

    // Check if the current field is valid
    if (
      input.val() === "" ||
      (input.attr("id") === "email" && !validateEmail(input.val()))
    ) {
      noErrors = false;

      // If the error is not yet visible, it adds it to the form.
      if (!$(this).find("span.err-label").length) {
        var error = input.attr("data-error");

        var label = $(this).find("label");
        label.after(
          '<span id="' +
            errorId +
            '" class="err-label usa-error" tabindex="0">' +
            error +
            "</span>"
        );
        input.attr("aria-labelledby", label.attr("id") + " " + errorId);
      }

      // Adds the error outline in the field.
      fieldElement.classList.add("usa-user-error");
      // Adds the error line to the side of the field.
      fieldElement.parentElement.classList.add("usa-border-error");
      // Makes the error text visible in the alert box.
      var alertBoxText = $("#" + alertErrorId)[0];
      alertBoxText ? alertBoxText.classList.remove("usa-error--alert") : "";
      // Adds the left padding from the fields.
      fieldElement.parentElement.classList.add("usa-form-spacing");
    }
    else if ($(this).find("span.err-label").length) {
      var errorLabel = $(this).find("span.err-label");
      errorLabel ? errorLabel.remove() : "";

      // Removes the error outline in the field.
      fieldElement.classList.remove("usa-user-error") ;
      // Removes the error line to the side of the field.
      fieldElement.parentElement.classList.remove("usa-border-error") ;
      // Makes the error text invisible in the alert box.
      $("#" + alertErrorId)[0].classList.add("usa-error--alert");
      // Removes the left padding from the fields.
      fieldElement.parentElement.classList.remove("usa-form-spacing") ;
    }
  });

  // If there is at least 1 error, focus the screen on the first error message.
  if (!noErrors) {
    var elem = document.querySelector(".err-label");
    elem.focus();
    var viewportOffset = elem.getBoundingClientRect();
    var top = viewportOffset.top;
    if (top < 108) {
      window.scrollTo(0, window.pageYOffset - (108 - top));
    }
  }

  return noErrors;
}

/**
 * This function has the objective of modifying the alert box.
 * @returns {undefined} This function does not return any value
 */
function modifyErrorElements() {
  'use strict';

  // If there is an error, modify the alert box header text based on the number of fields with errors.
  $("#error-box")[0].classList.remove("usa-error--alert") ;
  $("#error-box")[0].focus();

  // Gets all error text elements from the alert box to check how many errors we have (this includes reCaptcha and all fields)
  var errors = document.querySelectorAll('[id*="alert_error_"]:not(.usa-error--alert)');

  if (errors.length === 1) {
      // English Header text when there is only one error
      if (document.documentElement.lang === "en") {
          $("#error-box")[0].getElementsByTagName("h3")[0].innerHTML = "Your information contains an error";
      }
      // Spanish Header text when there is only one error
      else {
          $("#error-box")[0].getElementsByTagName("h3")[0].innerHTML = "Su información contiene 1 error";
      }
  }
  else {
      // English Header text when there is more than one error
      if (document.documentElement.lang === "en") {
          $("#error-box")[0].getElementsByTagName("h3")[0].innerHTML = "Your information contains " + errors.length + " errors";
      }
      // Spanish Header text when there is more than one error
      else {
          $("#error-box")[0].getElementsByTagName("h3")[0].innerHTML = "Su información contiene " + errors.length + " errores";
      }
  }

  if (errors.length >= 4) {
    // Adds the side line without spaces when all 3 fields are incorrect.
    $("#error-border")[0].classList.add("usa-main-border-error");
  }
  else {
    // Removes the side line without spaces.
    $("#error-border")[0].classList.remove("usa-main-border-error") ;
  }
}

/**
 * This function runs every time the "Submit" button is pressed on the "Report an issue" page.
 * The function checks if the user's input is valid. If it's valid, the form is submitted, otherwise it modifies the page to make errors visible.
 * @returns {boolean} indicates whether the form can be submitted or not.
 */
var submitPressed = function () {
  "use strict";

  // reCaptcha Validation and error styling.
  var reCaptchaValidationResult = reCaptchaValidation();

  // Field Validation
  var fieldValidationResult = fieldValidation();
  // Hides or shows error elements.
  modifyErrorElements();

  return reCaptchaValidationResult && fieldValidationResult;
};

function changeRecaptchaDisplay(mediaQueryList) {
  'use strict';
  // If the media query matches, it means the width is 499px or less
  if (mediaQueryList.matches) {
    // Hide the large recaptcha and show the small recaptcha.
    document.getElementById('recaptcha-large-container').style.display = "none";
    document.getElementById('recaptcha-small-container').style.display = "flex";
  }
  else {
    // Hide the small recaptcha and show the large recaptcha.
    document.getElementById('recaptcha-large-container').style.display = "flex";
    document.getElementById('recaptcha-small-container').style.display = "none";
  }
}

jQuery(document).ready(function () {
  "use strict";

  $("#cntctbx").hide();

  // Create a MediaQueryList object to check if the screen size
  var mediaQueryList = window.matchMedia("(max-width: 499px)");

  // Call listener function at run time
  changeRecaptchaDisplay(mediaQueryList);

  // Attach listener function on state changes
  mediaQueryList.addEventListener("change", function() {
    changeRecaptchaDisplay(mediaQueryList);
  });

  // Large recaptcha Element
  var reCaptchaLargeNode = document.getElementById('recaptcha-large-container');

  // Called every time an attribute changes in the large reCaptcha
  const reCaptchaObserver = new MutationObserver((mutationList, observer) => {
    if (document.querySelectorAll('#error-box:not(.usa-error--alert)').length > 0) {
      mutationList.forEach((mutation) => {
        // Check if display value changes from none to flex for recaptcha-large
        if (mutation.target.id === 'recaptcha-large-container') {
          reCaptchaValidation();
          modifyErrorElements();
        }
      });
    }
  });
  reCaptchaObserver.observe(reCaptchaLargeNode, {"attributes": true, "attributeFilter": ['style']});
});

jQuery(document).ready(function () {
  "use strict";

  $("#pagesurvey-hdr").hide();
  $("#pagesurvey-trgt").hide();
  $("#pagesurvey-ombnum").hide();
});