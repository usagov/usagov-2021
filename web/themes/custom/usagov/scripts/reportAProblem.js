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

function validateEmail(email) {
  "use strict";
  var re = /\S+@\S+\.\S+/; // This is a very simple regex for email
  return re.test(email);
}

// This function is called when the form is submitted.
function fieldValidation(){
  "use strict";
  console.log("Field Validation start");
  // Removes the line without spaces.
  document.getElementById("error-border").classList.remove("usa-main-border-error");
  var noerrors = true;
  $(".required").each(function () {
    var input = $(this).find("input,textarea");
    var errorId = "error_" + input.attr("id");
    var alertErrorId = "alert_" + errorId;

    if (
      input.val() === "" ||
      (input.attr("id") === "email" && !validateEmail(input.val()))
    ) {
      noerrors = false;
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

  // If
  if (!noerrors) {
    var elem = document.querySelector(".err-label");
    elem.focus();
    var viewportOffset = elem.getBoundingClientRect();
    var top = viewportOffset.top;
    if (top < 108) {
      window.scrollTo(0, window.pageYOffset - (108 - top));
    }
  }
  console.log("Field Validation end");
  return noerrors;
}

function modifyErrorMessages() {
  'use strict';
  // If there is an error, modify the alert box header text based on the number of fields with errors.
  document.getElementById("error-box").classList.remove("usa-error--alert");
  document.getElementById("error-box").focus();

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

  errors = document.querySelectorAll('[id*="alert_error_"]:not(.usa-error--alert):not(#alert_error_00NU0000004z90C)');
  if (errors.length >= 3) {
    // Adds the line without spaces when all 3 fields are incorrect.
    document.getElementById("error-border").classList.add("usa-main-border-error");
  }
  else {
    // Removes the line without spaces.
    document.getElementById("error-border").classList.remove("usa-main-border-error");
  }
}

// This function runs every time the "Submit" button is pressed on the "Report an issue" page.
var submitPressed = function () {
  "use strict";
  var reCaptchaResult = true;
  if (grecaptcha.getResponse().length === 0) {
    // Check if reCaptcha is checked.
    if ($(".err-label-captcha").length < 1) {
      if ($("html").attr("lang") === "en") {
        // Adds an english error message before the captcha box.
        $(".recaptcha-alignment").before(
          '<span class="err-label err-label-captcha" tabindex="0">Please fill out the reCaptcha</span>'
        );
      }
      else {
        // Adds a spanish error message before the captcha box.
        $(".recaptcha-alignment").before(
          '<span class="err-label err-label-captcha" tabindex="0">Por favor, complete el reCaptcha</span>'
        );
      }
      // Adds the error outline to the reCaptcha box.
      document.getElementsByClassName("recaptcha-outline-padding")[0].classList.add("usa-user-error");
      // Makes the reCaptcha error text visible in the alert box.
      document.getElementById("alert_error_recaptcha").classList.remove("usa-error--alert");
    }
    reCaptchaResult = false;
  }
  else {
    // Removes the error style from the reCaptcha.
    document.getElementsByClassName("recaptcha-outline-padding")[0].classList.remove("usa-user-error");
    $(".err-label-captcha").remove();
    // Makes the reCaptcha error text invisible in the alert box.
    document.getElementById("alert_error_recaptcha").classList.add("usa-error--alert");
  }

  var validationResult = fieldValidation();
  modifyErrorMessages();
  return validationResult;
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