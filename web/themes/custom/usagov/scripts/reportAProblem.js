function timestamp() {
  "use strict";
  var response = document.getElementById("g-recaptcha-response");
  if (response === null || response.value.trim() === "") {
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
      document.getElementById(input.attr("id")).classList.add("usa-user-error");
      // Adds the error line to the side of the field.
      document.getElementById(input.attr("id")).parentElement.classList.add("usa-border-error");
      // Makes the error text visible in the alert box.
      document.getElementById(alertErrorId).classList.remove("usa-error--alert");
    }
    else if ($(this).find("span.err-label").length) {
      $(this).find("span.err-label").remove();
      // Removes the error outline in the field.
      document.getElementById(input.attr("id")).classList.remove("usa-user-error");
      // Removes the error line to the side of the field.
      document.getElementById(input.attr("id")).parentElement.classList.remove("usa-border-error");
      // Makes the error text invisible in the alert box.
      document.getElementById(alertErrorId).classList.add("usa-error--alert");
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
 * This function has the objective
 * @returns {undefined} This function does not return any value
 */
function modifyErrorElements() {
  'use strict';

  // If there is an error, modify the alert box header text based on the number of fields with errors.
  document.getElementById("error-box").classList.remove("usa-error--alert");
  document.getElementById("error-box").focus();

  // Gets all error text elements from the alert box to check how many errors we have (this includes reCaptcha and all fields)
  var errors = document.querySelectorAll('[id*="alert_error_"]:not(.usa-error--alert)');

  if (errors.length === 1) {
      // English Header text when there is only one error
      if (document.documentElement.lang === "en") {
          document.getElementById("error-box").getElementsByTagName("h3")[0].innerHTML = "Your information contains an error";
      }
      // Spanish Header text when there is only one error
      else {
          document.getElementById("error-box").getElementsByTagName("h3")[0].innerHTML = "Su información contiene 1 error";
      }
  }
  else {
      // English Header text when there is more than one error
      if (document.documentElement.lang === "en") {
          document.getElementById("error-box").getElementsByTagName("h3")[0].innerHTML = "Your information contains " + errors.length + " errors";
      }
      // Spanish Header text when there is more than one error
      else {
          document.getElementById("error-box").getElementsByTagName("h3")[0].innerHTML = "Su información contiene " + errors.length + " errores";
      }
  }

  // Gets all error text elements from the alert box to check how many errors we have (this only includes all fields, not the reCaptcha)
  errors = document.querySelectorAll('[id*="alert_error_"]:not(.usa-error--alert):not(#alert_error_recaptcha)');
  if (errors.length >= 3) {
    // Adds the side line without spaces when all 3 fields are incorrect.
    document.getElementById("error-border").classList.add("usa-main-border-error");
  }
  else {
    // Removes the side line without spaces.
    document.getElementById("error-border").classList.remove("usa-main-border-error");
  }
}

/**
 * This function runs every time the "Submit" button is pressed on the "Report an issue" page.
 * The function checks if the user's input is valid. If it's valid, the form is submitted, otherwise it modifies the page to make errors visible.
 * @returns {boolean} indicates whether the form can be submitted or not.
 */
var submitPressed = function () {
  "use strict";

  // reCaptcha Validation
  var captchaValidationResult = true;

  if (grecaptcha.getResponse().length === 0) {
    // Check if reCaptcha is checked.
    if ($(".err-label-captcha").length < 1) {
      if ($("html").attr("lang") === "en") {
        // Adds an english error message before the captcha box.
        $(".recaptcha-alignment").before(
          '<span class="err-label err-label-captcha" tabindex="0">Fill out the reCaptcha</span>'
        );
      }
      else {
        // Adds a spanish error message before the captcha box.
        $(".recaptcha-alignment").before(
          '<span class="err-label err-label-captcha" tabindex="0">Complete el reCaptcha</span>'
        );
      }
      // Adds the error outline to the reCaptcha box.
      document.getElementsByClassName("recaptcha-outline-padding")[0].classList.add("usa-user-error");
      // Makes the reCaptcha error text visible in the alert box.
      document.getElementById("alert_error_recaptcha").classList.remove("usa-error--alert");
      captchaValidationResult = false;
    }
  }
  else {
    // Removes the error style from the reCaptcha.
    document.getElementsByClassName("recaptcha-outline-padding")[0].classList.remove("usa-user-error");
    $(".err-label-captcha").remove();
    // Makes the reCaptcha error text invisible in the alert box.
    document.getElementById("alert_error_recaptcha").classList.add("usa-error--alert");
  }

  // Field Validation
  var fieldValidationResult = fieldValidation();
  // Hides or shows error elements.
  modifyErrorElements();
  return captchaValidationResult && fieldValidationResult;
};

jQuery(document).ready(function () {
  "use strict";
  $("#cntctbx").hide();
});

jQuery(document).ready(function () {
  "use strict";
  $("#pagesurvey-hdr").hide();
  $("#pagesurvey-trgt").hide();
  $("#pagesurvey-ombnum").hide();
});