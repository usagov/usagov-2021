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

async function getData(streetAddress, city, state, zipCode) {
    const USERID = "";
    const PASSWORD = "";
    const url = `https://secure.shippingapis.com/ShippingAPI.dll?API=Verify \
    &XML=<AddressValidateRequest USERID="${USERID}" PASSWORD="${PASSWORD}"><Address><Address1>\
    </Address1><Address2>${streetAddress}</Address2><City>${city}</City><State>${state}\
    </State><Zip5>${zipCode}</Zip5><Zip4></Zip4></Address></AddressValidateRequest>`;

    // If the zip code contains any letters or is less or more than 5 characters, it returns an error.
    if(zipCode.length !== 5 || !(/^\d+$/.test(zipCode))) {
        return "Invalid Zip Code.";
    }

    try {
        const response = await fetch(`https://secure.shippingapis.com/ShippingAPI.dll?API=Verify \
        &XML=<AddressValidateRequest USERID="${USERID}" PASSWORD="${PASSWORD}"><Address><Address1>\
        </Address1><Address2>${streetAddress}</Address2><City>${city}</City><State>${state}\
        </State><Zip5>${zipCode}</Zip5><Zip4></Zip4></Address></AddressValidateRequest>`);

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.text();
        return data;
    }
    catch (error) {
        console.error(error);
    }
}


// This function makes the call to the USPS API and returns the response.
function addressUSPSValidation(responseText) {

    let response = {
        fieldID: "",
        errorMessage: "",
    }

    if(responseText.includes("Invalid Address.")){
        response.fieldID = "street";
        response.errorMessage = document.documentElement.lang === "en" ? "Please enter a valid address." : "Por favor, escriba una dirección válida.";
    }
    else if(responseText.includes("Address Not Found.")){
        response.fieldID = "street";
        response.errorMessage = document.documentElement.lang === "en" ? "Address not found. Please enter a valid address." : "Dirección no encontrada. Por favor, escriba una dirección válida.";
    }
    else if(responseText.includes("Invalid City.")){
        response.fieldID = "city";
        response.errorMessage = document.documentElement.lang === "en" ? "Please enter a valid city." : "Por favor, escriba una ciudad válida.";
    }
    else if(responseText.includes("Invalid Zip Code.")){
        response.fieldID = "zip";
        response.errorMessage = document.documentElement.lang === "en" ? "Please enter a valid 5-digit ZIP code." : "Por favor, escriba un código postal válido de 5 dígitos.";
    }
    return response;

}

// This function is executed every time the "Find my elected officials" button is clicked.
async function handleFormSubmission() {
    "use strict";
    // stop form submission
    let test = [];
    let errorFound = false;

    const streetAddressField = document.getElementById("input-street");
    const cityField = document.getElementById("input-city");
    const stateField = document.getElementById("input-state");
    const zipCodeField = document.getElementById("input-zip");
    const formFields = [streetAddressField, cityField, stateField, zipCodeField];

    // TO-DO: Analyze the response and decide if the address is valid or not.
    // const response = addressUSPSValidation(streetAddressField.value, cityField.value, stateField.value, zipCodeField.value);
    const response = addressUSPSValidation(await getData(streetAddressField.value, cityField.value, stateField.value, zipCodeField.value));

    formFields.forEach(field => {
        let fieldID = field.previousElementSibling.id;
        var errorID = "error-" + fieldID;

        // If the current field is empty, the error style is added.
        if (!field.value || response.fieldID == fieldID) {
            errorFound = true;
            test.push(fieldID + " missing");

            // Add field border error style.
            field.classList.add("usa-user-error");
            // Adds the error style to the error message above the field.
            field.previousElementSibling.classList.add("usa-error");

            // Makes the error message in the alert box visible.
            document.getElementById(errorID).classList.remove("usa-error--alert");

            var message;
            if(!field.value) {
                // Changing to use the error method specified in the CMS if available
                var cmsError = document.getElementById(errorID);
                if (cmsError) {
                    message = cmsError.getElementsByTagName("span")[0].innerHTML;
                }
                else {
                    message = a11y_content[fieldID];
                }
            }
            else {
                // Change the error message above the input field.
                message = response.errorMessage;
                // Change the error message inside the alert box.
                document.getElementById(errorID).getElementsByTagName("span")[0].innerHTML =  response.errorMessage;
            }

            field.previousElementSibling.innerHTML = message;

            // Check if the street address, zip code or city field is empty and if it is, add the vertical line on the left side.
            if (field.previousElementSibling.id === "street" ||
                field.previousElementSibling.id === "zip" ||
                field.previousElementSibling.id === "city") {
                field.parentElement.classList.add("usa-border-error");
            }
            // Check if the state field is empty and if it is, add the vertical line on the left side.
            else if (field.previousElementSibling.id === "state") {
                field.parentElement.parentElement.classList.add("usa-border-error");
                // Arranges the drop-down arrow within the input field.
                document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "30px";
                document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "31px";
                document.getElementsByClassName("usa-combo-box__clear-input")[0].style["top"] = "30px";
            }
        }

        // If the current field is not empty, the error style is removed.
        else if (field.value !== "" || field.value) {
            // Remove field border error style.
            field.classList.remove("usa-user-error");

            // Remove the vertical line on the left side.
            field.parentElement.classList.remove("usa-border-error");
            field.parentElement.parentElement.classList.remove("usa-border-error");

            // Remove the error message above the field.
            field.previousElementSibling.innerHTML = "";
            // Remove the error style to the error message above the field.
            field.previousElementSibling.classList.remove("usa-error");
            // Hide the error message from the alert box.
            document.getElementById(errorID).classList.add("usa-error--alert");

            if(field.previousElementSibling.id === "state"){
                // Arranges the drop-down arrow within the input field.
                document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "1px";
                document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "1px";
                document.getElementsByClassName("usa-combo-box__clear-input")[0].style["top"] = "1px";
            }
        }
    });


    // If all fields have an error, join the error lines on the left into one.
    if (test.length === 4) {
        document.getElementById("error-border").classList.add("usa-main-border-error");
    }
    // If 3 or fewer fields have an error, separate the lines on the left.
    else if (test.length < 4) {
        document.getElementById("error-border").classList.remove("usa-main-border-error");
    }

   // If there is an error, modify the alert box header text based on the number of fields with errors.
    if (errorFound) {
        document.getElementById("error-box").classList.remove("usa-error--alert");
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
        return;
    }

    dataLayer.push({
        'event': 'CEO_form_submit',
        'form_result': 'success'
    });
    document.getElementById("error-box").classList.add("usa-error--alert");
    document.getElementById("myform").submit();
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

