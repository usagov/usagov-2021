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
 * This function validates if the reCaptcha response and adds the error style if needed.
 * @returns {boolean} indicates whether the reCaptcha is checked or not.
 */
function reCaptchaValidation() {
  "use strict";

  // reCaptcha Validation
  var captchaValidationResult = true;
  // Screen width to validate the reCaptcha
  var screenWidth = window.innerWidth;

  if (screenWidth >= 500 && grecaptcha.getResponse(0).length === 0 ||
       screenWidth < 500 && grecaptcha.getResponse(1).length === 0) {
    // Check if reCaptcha is checked.
    if ($(".err-label-captcha").length < 1 && !document.querySelector("span.err-label")) {

      if ($("html").attr("lang") === "en") {
        // Adds an english error message before the captcha box.
        $(".recaptcha-alignment").before(
          '<span class="err-label usa-error recaptcha-error-message" tabindex="0">Fill out the reCaptcha</span>'
        );
      }
      else {
        // Adds a spanish error message before the captcha box.
        $(".recaptcha-alignment").before(
          '<span class="err-label usa-error recaptcha-error-message" tabindex="0">Complete el reCaptcha</span>'
        );
      }
      // Adds the error outline to the reCaptcha box.
      $(".recaptcha-outline-padding")[0].classList.add("usa-user-error");
      // Makes the reCaptcha error text visible in the alert box.
      var alertBoxText = $("#alert_error_recaptcha")[0];
      alertBoxText ? alertBoxText.classList.remove("usa-error--alert") : "";
      // Adds left padding from recaptcha.
      $(".recaptcha-container")[0].classList.add("usa-form-spacing", "usa-border-error");
      // Aligns the reCaptcha to the left when it has an error.
      $(".recaptcha-alignment")[0].style.justifyContent = "left";
      captchaValidationResult = false;
    }
  }
  else {
    // Removes error messages from the reCaptcha
    var reCaptchaErrorMessage = $(".recaptcha-error-message")[0];
    reCaptchaErrorMessage ? reCaptchaErrorMessage.remove() : "";

    // Removes the error style from the reCaptcha.
    var reCaptchaErrorStyle = $(".recaptcha-outline-padding")[0];
    reCaptchaErrorStyle ? reCaptchaErrorStyle.classList.remove("usa-user-error") : "";
    var reCaptchaErrorLabel = $(".err-label-captcha")[0];
    reCaptchaErrorLabel ? reCaptchaErrorLabel.remove() : "";

    // Makes the reCaptcha error text invisible in the alert box.
    $("#alert_error_recaptcha")[0].classList.add("usa-error--alert");
    // Removes left padding from recaptcha.
    var reCaptchaContainer = $(".recaptcha-container")[0];
    reCaptchaContainer ? reCaptchaContainer.classList.remove("usa-form-spacing", "usa-border-error") : "";
    // Aligns the reCaptcha to the center when it doesn't have an error.
    $(".recaptcha-alignment")[0].style.justifyContent = "center";
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

  // Gets all error text elements from the alert box to check how many errors we have (this only includes all fields, not the reCaptcha)
  errors = document.querySelectorAll('[id*="alert_error_"]:not(.usa-error--alert)');
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

jQuery(document).ready(function () {
  "use strict";

  $("#cntctbx").hide();

  var reCaptchaLargeNode = document.getElementById('recaptcha-large');
  var oldDisplayLarge = getComputedStyle(reCaptchaLargeNode).display;

  var reCaptchaSmallNode = document.getElementById('recaptcha-small');
  var oldDisplaySmall = getComputedStyle(reCaptchaSmallNode).display;

  // Callback function to execute when mutations are observed
  const reCaptchaCallback = (entries) => {
    var entry = entries[0];
    var newDisplay = getComputedStyle(entry.target).display;

    if ($(".usa-error--alert").length <= 0 && (newDisplay !== oldDisplayLarge || newDisplay !== oldDisplaySmall)) {
      reCaptchaValidation();
      console.log('new:', newDisplay, 'old:', oldDisplaySmall ? oldDisplaySmall : oldDisplayLarge);
    }
  };

  const reCaptchaObserver = new IntersectionObserver(reCaptchaCallback, {"threshold": 0});
  reCaptchaObserver.observe(reCaptchaLargeNode);
  reCaptchaObserver.observe(reCaptchaSmallNode);
});

jQuery(document).ready(function () {
  "use strict";

  $("#pagesurvey-hdr").hide();
  $("#pagesurvey-trgt").hide();
  $("#pagesurvey-ombnum").hide();
});