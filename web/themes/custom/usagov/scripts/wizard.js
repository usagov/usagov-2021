

let submitButton = document.getElementById("submit");

if (submitButton != null) {
  submitButton.addEventListener("click", wizardError);
}

function wizardError() {
  "use strict";
  let choices = document.getElementsByName("options");
  if (choices) {
    for (let choice = 0; choice < choices.length; choice++) {
      let selected = choices[choice].checked;
      if (selected === true) {
        document.getElementById("msg").innerHTML = "";
        document.getElementById("msg").removeAttribute("tabindex", "-1");
        dataLayer.push({"event": "Wizard_Begin"});
        return true;
      }
 else if (
        document.getElementsByTagName("html")[0].getAttribute("lang") === "en"
      ) {
        document.getElementById("msg").innerHTML =
          "Error:Please choose one of the following options";
        document.getElementById("msg").focus();
      }
 else {
        document.getElementById("msg").innerHTML =
          "Error:Por favor elija una opción";
        document.getElementById("msg").focus();
      }
    }
  }
  dataLayer.push({"event": "Wizard_Error", "button": "Begin"});
  return false;
}
