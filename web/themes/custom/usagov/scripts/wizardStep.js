


document.getElementById("prior").addEventListener("click", priorStepFunction);
function priorStepFunction() {
    dataLayer.push({'Wizard_Prior':'Prior'});
}
document.getElementById("next").addEventListener("click", wizardStepError);
function wizardStepError() {
    let choices = document.getElementsByName("options");
    for ( let choice = 0; choice < choices.length; choice++ ) {
        let selected = choices[choice].checked;
        if ( selected == true ) {
            document.getElementById("msg").innerHTML = "";
            document.getElementById("msg").removeAttribute("tabindex", "-1");
            dataLayer.push({'Wizard_Success':'Next'});
        return true;
        }
        else if (document.getElementsByTagName('html')[0].getAttribute('lang') == "en" ) {
            document.getElementById("msg").innerHTML = "Please choose one option";
            document.getElementById("msg").focus();
        } 
        else {
            document.getElementById("msg").innerHTML = "Por favor elija una opción";
            document.getElementById("msg").focus();
        }
    }
    dataLayer.push({'Wizard_Error':'Next'});
}