let priorButton = document.getElementById("prior");

if (priorButton != null) {
  priorButton.addEventListener("click", priorStepFunction);
}

function priorStepFunction() {
  "use strict";
  dataLayer.push({"event": "Wizard_Prior"});
}

let nextButton = document.getElementById("next");

if (nextButton != null) {
  nextButton.addEventListener("click", wizardStepError);
}

function wizardStepError() {
  "use strict";
  let choices = document.getElementsByName("options");
  if (choices) {
    for (let choice = 0; choice < choices.length; choice++) {
      let selected = choices[choice].checked;
      if (selected === true) {
        document.getElementById("msg").innerHTML = "";
        document.getElementById("msg").removeAttribute("tabindex", "-1");
        document.getElementById("wizard-border").classList.remove("wizard_error");
        dataLayer.push({"event": "Wizard_Next"});
        return true;
      }
 else if (
        document.getElementsByTagName("html")[0].getAttribute("lang") === "en"
      ) {
        document.getElementById("msg").innerHTML =
          "Error: Please choose one of the following options";
        document.getElementById("msg").focus();
        document.getElementById("wizard-border").classList.add("wizard_error");
      }
 else {
        document.getElementById("msg").innerHTML =
          "Error: Por favor elija una opción";
        document.getElementById("msg").focus();
        document.getElementById("wizard-border").classList.add("wizard_error");
      }
    }
  }
  dataLayer.push({"event": "Wizard_Error", "button": "Next"});
  return false;
}
