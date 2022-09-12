/**
 * Improve the accessibility of pages with input fields.
 */

const a11y_translations = {
    "en": {
        "street": "Please fill out the street field.",
        "city": "Please fill out the city field.",
        "state": "Please fill out the state field.",
        "zip": "Please fill out the zip code field.",
        "topic": "Please fill out the topic field.",
        "about": "Please fill out the about field.",
        "action": "Please fill out the action field.",
        "clear_state": "Clear the contents of the state field."
    },
    "es": {
        "street": "Por favor, escriba la dirección. ",
        "city": "Por favor, escriba el nombre de la ciudad.",
        "state": "Por favor, escriba el nombre del estado.",
        "zip": "Por favor, escriba código postal.",
        "topic": "Por favor, escriba el tema.",
        "about": "Por favor, escriba qué quiere decir acerca del tema.",
        "action": "Por favor, escriba su petición para el funcionario electo.",
        "clear_state": "Borrar el contenido del campo del estado."
    }
}
let a11y_content = a11y_translations[ document.documentElement.lang ];

function myforms(event) {
    // stop form submission
    let elementVal = ["input", "textarea"];
    let test = [];
    let errorFound = false;
    for (let n = 0; n < elementVal.length; n++) {
        let elmnts = document.forms["myform"].getElementsByTagName(elementVal[n]);
        for (let k = 0; k < elmnts.length; k++) {
            if (elmnts[k].value == "") {
                let error = elmnts[k].previousElementSibling.id;
                test.push(error + " missing");
                elmnts[k].classList.add("usa-user-error");
                elmnts[k].previousElementSibling.classList.add("usa-error");
                let message = a11y_content[error];
                elmnts[k].previousElementSibling.innerHTML = message;
                event.preventDefault();
                errorFound = true;  
            }
            if (elmnts[k].value == "" && elmnts[k].previousElementSibling.id == "street") {
                elmnts[k].parentElement.classList.add("usa-border-error");
            }
            if (elmnts[k].value == "" && elmnts[k].previousElementSibling.id == "city") {
                elmnts[k].parentElement.classList.add("usa-border-error");
            }
            if (elmnts[k].value == "" && elmnts[k].previousElementSibling.id == "state") {
                elmnts[k].parentElement.parentElement.classList.add("usa-border-error");
            }
            if (elmnts[k].value == "" && elmnts[k].previousElementSibling.id == "zip") {
                elmnts[k].parentElement.classList.add("usa-border-error");
            }
            else
            if (elmnts[k].value != "") {
                elmnts[k].classList.remove("usa-user-error");
                elmnts[k].parentElement.classList.remove("usa-border-error");
                elmnts[k].previousElementSibling.classList.remove("usa-error");
                elmnts[k].parentElement.parentElement.classList.remove("usa-border-error");
                elmnts[k].previousElementSibling.innerHTML = "";   
            } 
        }     
    }
    if (test.length == 4) {
        document.getElementById("error-box").classList.remove("usa-error--alert")
        document.getElementById("error-border").classList.add("usa-main-border-error");
    }
    else
    if (test.length < 4){
        document.getElementById("error-border").classList.remove("usa-main-border-error");
    }
   
    if (errorFound) {
        dataLayer.push({'event':'myform','error type':test.join(";")});
        return false
    }
};

window.addEventListener("load", function() {
    // Customize input validation error messages by
    // specifying the name of each input field. Only
    // applies to elements specified in the list below.
    let elementTypes = ["input", "textarea"];
    for (let i = 0; i < elementTypes.length; i++) {
        elements = document.getElementsByTagName(elementTypes[i]);
        for (let j = 0; j < elements.length; j++) {
            // Note: all input fields should have an ID starting with "input-"
            let message = a11y_content[elements[j].id.replace("input-", "")];
            elements[j].setAttribute("oninvalid", "this.setCustomValidity('" + message + "')");
            elements[j].setAttribute("oninput", "this.setCustomValidity('')");
        }
    }

    // Clarify the purpose of the dropdown menu's clear button
    let clearButtons = document.getElementsByClassName("usa-combo-box__clear-input");
    for (let i = 0; i < clearButtons.length; i++) {
        // Multiple if statements to prevent a runaway error
        if (clearButtons[i].parentElement) {
            if (clearButtons[i].parentElement.previousElementSibling) {
                if (clearButtons[i].parentElement.previousElementSibling.id == "input-state") {
                    clearButtons[i].setAttribute("aria-label", a11y_content.clear_state);
                }
            }
        }
    }

    // Include the dropdown menu's toggle button in the tab order
    let toggleButtons = document.getElementsByClassName("usa-combo-box__toggle-list");
    for (let i = 0; i < toggleButtons.length; i++) {
        toggleButtons[i].removeAttribute("tabindex");
    }
});

