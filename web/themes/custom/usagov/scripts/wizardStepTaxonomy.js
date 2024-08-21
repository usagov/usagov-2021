(function ($, Drupal, once) {
"use strict";
Drupal.behaviors.wizardStepTaxonomy = {
  "attach": function(context, settings) {

    let priorButton = document.getElementById("prior");

    let priorButtonUrl;

    if (priorButton != null) {
      priorButtonUrl = priorButton.getAttribute("onclick").split("=")[1].trim().slice(1, -1);
      priorButton.addEventListener("click", priorStepFunction);
    }

    function priorStepFunction() {
      if (priorButtonUrl) {
        dataLayer.push({"event": "Wizard_Prior", "Wizard_Nav_Button_URL": priorButtonUrl});
      }
    }

    let nextButton = document.getElementById("next");

    if (nextButton != null) {
      nextButton.addEventListener("click", wizardStepError);
    }

    function wizardStepError() {
      let choices = document.getElementsByName("options");

      const htmlLangAttr = document.documentElement.lang;

      var errorMessage = '';

      if (choices) {
        for (let choice = 0; choice < choices.length; choice++) {
          let selected = choices[choice].checked;


          if (htmlLangAttr === "en") {
            errorMessage = 'Error: Please choose one of the following options';
          }
          else {
            errorMessage = 'Error: elija una de las siguientes opciones';
          }

          // If any choice is selected, return true.
          if (selected) {
            var choiceUrl = choices[choice].getAttribute("value");
            dataLayer.push({"event": "Wizard_Next", "Wizard_Nav_Button_URL": choiceUrl});
            return true;
          }
        }

        /*
         If we exit the loop successfully, there must have been no choice selected.
         Show the error message.
         */
        $("#wizard-border").addClass("usagov-wizard--error").show();
        $("#msg").html(errorMessage).focus();
        dataLayer.push({"event": "usagov-wizard--error", "button": "Next"});
        return false;
      }
    }
  }
};
})(jQuery, Drupal, once);
