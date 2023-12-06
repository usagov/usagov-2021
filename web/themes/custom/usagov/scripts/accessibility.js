/**
 * Improve the accessibility of pages with input fields.
 * Creating error messages so users know which fields need attention. Error messages are managed on CMS in the body element and element ID's are used to get the respective text.
 * Used on pages: find and contact elected officials; email your elected official
 * a11y_translations is used as fallback for pages where error message is not included in the CMS.
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
};
let a11y_content = a11y_translations[document.documentElement.lang];

// Changes state name to official postal abbreviations.
// Note: The USPS API accepts the state name, but while testing, "Pennsylvania" returned an error in the API response.
// To avoid this, we are going to use the official postal abbreviations.
const state_codes = {
    "Alabama": "AL",
    "Alaska": "AK",
    "Arizona": "AZ",
    "Arkansas": "AR",
    "California": "CA",
    "Colorado": "CO",
    "Connecticut": "CT",
    "Delaware": "DE",
    "District Of Columbia": "DC",
    "Florida": "FL",
    "Georgia": "GA",
    "Hawaii": "HI",
    "Idaho": "ID",
    "Illinois": "IL",
    "Indiana": "IN",
    "Iowa": "IA",
    "Kansas": "KS",
    "Kentucky": "KY",
    "Louisiana": "LA",
    "Maine": "ME",
    "Maryland": "MD",
    "Massachusetts": "MA",
    "Michigan": "MI",
    "Minnesota": "MN",
    "Mississippi": "MS",
    "Missouri": "MO",
    "Montana": "MT",
    "Nebraska": "NE",
    "Nevada": "NV",
    "New Hampshire": "NH",
    "New Jersey": "NJ",
    "New Mexico": "NM",
    "New York": "NY",
    "North Carolina": "NC",
    "North Dakota": "ND",
    "Ohio": "OH",
    "Oklahoma": "OK",
    "Oregon": "OR",
    "Pennsylvania": "PA",
    "Rhode Island": "RI",
    "South Carolina": "SC",
    "South Dakota": "SD",
    "Tennessee": "TN",
    "Texas": "TX",
    "Utah": "UT",
    "Vermont": "VT",
    "Virginia": "VA",
    "Washington": "WA",
    "West Virginia": "WV",
    "Wisconsin": "WI",
    "Wyoming": "WY"
};

// This function makes the call to the USPS API and returns the response.
function addressUSPSValidation(streetAddress, city, state, zipCode) {

    const USERID = "";
    const PASSWORD = "";
    const url = `https://secure.shippingapis.com/ShippingAPI.dll?API=Verify \
    &XML=<AddressValidateRequest USERID="${USERID}" PASSWORD="${PASSWORD}"><Address><Address1>\
    </Address1><Address2>${streetAddress}</Address2><City>${city}</City><State>${state}\
    </State><Zip5>${zipCode}</Zip5><Zip4></Zip4></Address></AddressValidateRequest>`;

    const Http = new XMLHttpRequest();
    Http.open("GET", url);
    Http.send();

    Http.onreadystatechange = (e) => {
        // Testing. REMOVE
        console.log(Http.responseText);
        return Http.responseText;
    }
}

// This function is executed every time the "Find my elected officials" button is clicked.
function myforms(event) {
    "use strict";
    // stop form submission
    let elementVal = ["input", "textarea"];
    let test = [];
    let errorFound = false;
    for (let n = 0; n < elementVal.length; n++) {
        let elmnts = document.forms["myform"].getElementsByTagName(elementVal[n]);
        for (let k = 0; k < elmnts.length; k++) {
            if (elmnts[k].value === "") {
                let error = elmnts[k].previousElementSibling.id;
                test.push(error + " missing");
                elmnts[k].classList.add("usa-user-error");
                elmnts[k].previousElementSibling.classList.add("usa-error");

                // Changing to use the error method specified in the CMS if available
                var errorID = "error-" + error;
                var cmsError = document.getElementById(errorID);
                var message;
                if (cmsError) {
                    message = cmsError.getElementsByTagName("span")[0].innerHTML;

                }
                else {
                    message = a11y_content[error];
                }

                elmnts[k].previousElementSibling.innerHTML = message;
                event.preventDefault();
                errorFound = true;
            }
            // Check if the street address, zip code or city field is empty and if it is, add the vertical line on the left side.
            if (elmnts[k].value === "" && (elmnts[k].previousElementSibling.id === "street" ||
                                           elmnts[k].previousElementSibling.id === "zip" ||
                                           elmnts[k].previousElementSibling.id === "city")) {
                elmnts[k].parentElement.classList.add("usa-border-error");
            }
            // Check if the state field is empty and if it is, add the vertical line on the left side.
            else if (elmnts[k].value === "" && elmnts[k].previousElementSibling.id === "state") {
                elmnts[k].parentElement.parentElement.classList.add("usa-border-error");
            }
            // If the current field is not empty, the error style is removed.
            else if (elmnts[k].value !== "") {
                elmnts[k].classList.remove("usa-user-error");
                elmnts[k].parentElement.classList.remove("usa-border-error");
                elmnts[k].previousElementSibling.classList.remove("usa-error");
                elmnts[k].parentElement.parentElement.classList.remove("usa-border-error");
                elmnts[k].previousElementSibling.innerHTML = "";
            }
        }
    }

    // If all fields have an error, join the error lines on the left into one.
    if (test.length === 4) {
        document.getElementById("error-border").classList.add("usa-main-border-error");
        document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "30px";
        document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "31px";
        document.getElementsByClassName("usa-combo-box__clear-input")[0].style["top"] = "30px";
    }
    // If 3 or fewer fields have an error, separate the lines on the left.
    else if (test.length < 4) {
            document.getElementById("error-border").classList.remove("usa-main-border-error");
            document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "1px";
            document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "1px";
            document.getElementsByClassName("usa-combo-box__clear-input")[0].style["top"] = "1px";
    }

    // If there is an error, the alert box becomes visible.
    if (errorFound) {
        document.getElementById("error-box").classList.remove("usa-error--alert");
    }

    // If the "Street address" field has an error, the message "Fill out the street field/Escriba la dirección" in the alert box becomes visible.
    if (errorFound && document.getElementById("input-street").value !== "") {
        document.getElementById("error-street").classList.add("usa-error--alert");
    }
    else {
        document.getElementById("error-street").classList.remove("usa-error--alert");
    }

    // If the "City" field has an error, the message "Fill out the city field/Escriba el nombre de la ciudad" in the alert box becomes visible.
    if (errorFound && document.getElementById("input-city").value !== "") {
        document.getElementById("error-city").classList.add("usa-error--alert");
    }
    else {
        document.getElementById("error-city").classList.remove("usa-error--alert");
    }

    // If the "State" field has an error, the message "Fill out the state field/Escriba el nombre del estado" in the alert box becomes visible.
    if (errorFound && document.getElementById("input-state").value !== "") {
        document.getElementById("error-state").classList.add("usa-error--alert");
    }
    else {
        document.getElementById("error-state").classList.remove("usa-error--alert");
        document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "30px";
        document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "31px";
        document.getElementsByClassName("usa-combo-box__clear-input")[0].style["top"] = "30px";
    }

    // If the "ZIP code" field has an error, the message "Fill out the ZIP code field/Escriba el código postal" in the alert box becomes visible.
    if (errorFound && document.getElementById("input-zip").value !== "") {
        document.getElementById("error-zip").classList.add("usa-error--alert");
    }
    else {
        document.getElementById("error-zip").classList.remove("usa-error--alert");
    }

   // If there is an error, modify the alert box header text based on the number of fields with errors.
    if (errorFound) {
        document.getElementById("error-box").focus();

        if (test.length === 1) {
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
                document.getElementById("error-box").getElementsByTagName("h3")[0].innerHTML = "Your information contains " + test.length + " errors";
            }
            // Spanish Header text when there is more than one error
            else {
                document.getElementById("error-box").getElementsByTagName("h3")[0].innerHTML = "Su información contiene " + test.length + " errores";
            }
        }

        dataLayer.push({
            'event': 'CEO form error',
            'error type': test.join(";")
        });
        return false;
    }

    const streetAddress = document.getElementById("input-street").value;
    const city = document.getElementById("input-city").value;
    const state = state_codes[document.getElementById("input-state").value];
    const zipCode = document.getElementById("input-zip").value;

    const response = addressUSPSValidation(streetAddress, city, state, zipCode);
    // TO-DO: Analyze the response and decide if the address is valid or not.
    // Testing. REMOVE
    return false;

    // document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "1px";
    // document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "1px";
    // document.getElementsByClassName("usa-combo-box__clear-input")[0].style["top"] = "1px";
    // dataLayer.push({
    //     'event': 'CEO_form_submit',
    //     'form_result': 'success'
    // });
};


window.addEventListener("load", function () {
    "use strict";
    // Customize input validation error messages by
    // specifying the name of each input field. Only
    // applies to elements specified in the list below.
    let elementTypes = ["input", "textarea"];
    for (let i = 0; i < elementTypes.length; i++) {
        let elements = document.getElementsByTagName(elementTypes[i]);
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
                if (clearButtons[i].parentElement.previousElementSibling.id === "input-state") {
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

