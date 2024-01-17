(function ($, jQuery) {
  "use strict";

  $(document).ready(function () {
    function validateEmail(email) {
      var re = /\S+@\S+\.\S+/; // This is a very simple regex for email
      return re.test(email);
    }

    $(".validate").submit(function () {
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

            // Makes the error text visible in the alert box.
            document.getElementById(alertErrorId).classList.remove("usa-error--alert");

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

          document.getElementById(input.attr("id")).classList.add("usa-user-error");
          modifyAlertErrorBox();
        }
        else if ($(this).find("span.err-label").length) {
          $(this).find("span.err-label").remove();
          document.getElementById(input.attr("id")).classList.remove("usa-user-error");
          // Makes the error text invisible in the alert box.
          document.getElementById(alertErrorId).classList.add("usa-error--alert");
        }
      });

      if (!noerrors) {
        var elem = document.querySelector(".err-label");
        elem.focus();
        var viewportOffset = elem.getBoundingClientRect();
        var top = viewportOffset.top;
        if (top < 108) {
          window.scrollTo(0, window.pageYOffset - (108 - top));
        }
      }
      return noerrors;
    });
  });
})(jQuery);

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

function modifyAlertErrorBox() {
  // If there is an error, modify the alert box header text based on the number of fields with errors.
  document.getElementById("error-box").classList.remove("usa-error--alert");
  document.getElementById("error-box").focus();

  var errors = document.querySelectorAll('[id*="alert_error_"]:not(.usa-error--alert)');
  console.log(errors.length);
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
}

// This function runs every time the "Submit" button is pressed on the "Report an issue" page.
var submitPressed = function () {
  "use strict";
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
      modifyAlertErrorBox();
    }
    return true;
  }
  else {
    // Removes the error style from the reCaptcha.
    document.getElementsByClassName("recaptcha-outline-padding")[0].classList.remove("usa-user-error");
    $(".err-label-captcha").remove();
    // Makes the reCaptcha error text invisible in the alert box.
    document.getElementById("alert_error_recaptcha").classList.add("usa-error--alert");
  }
  return true;
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