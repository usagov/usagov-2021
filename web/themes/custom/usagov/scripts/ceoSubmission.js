function getSearchParams() {
    "use strict";
    const paramsString = window.location.search;
    const searchParams = new URLSearchParams(paramsString);
    return searchParams;
}

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

/**
 * Creating error messages so users know which fields need attention based on the USPS WebTools API results.
 * Error messages are managed on CMS in the body element and element ID's are used to get the respective text.
 * Used on page: find and contact elected officials
 * usps_translations is used as fallback for pages where error message is not included in the CMS.
 */
const usps_translations = {
    "en": {
        "invalid-street": "Please enter a valid street address.",
        "no-street": "Address not found. Please enter a valid address.",
        "invalid-city": "City not found. Please enter a valid city.",
        "invalid-zip": "Please enter a valid 5-digit ZIP code."
    },
    "es": {
        "invalid-street": "Por favor, escriba una dirección válida.",
        "no-street": "Dirección no encontrada. Por favor, escriba una dirección válida.",
        "invalid-city": "Ciudad no encontrada. Por favor, escriba una ciudad válida.",
        "invalid-zip": "Por favor, escriba un código postal válido de 5 dígitos."
    }
};
let usps_content = usps_translations[document.documentElement.lang];

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
async function addressUSPSValidation(streetAddress, city, state, zipCode) {
    "use strict";

    // If the zip code contains any letters or is less or more than 5 characters, it returns an error.
    if (zipCode.length !== 5 || !(/^\d+$/.test(zipCode))) {
        return "Invalid Zip Code.";
    }

    try {
        // USPS API Call
        const url = `https://secure.shippingapis.com/ShippingAPI.dll?API=Verify \
        &XML=<AddressValidateRequest USERID="${USPS_USERID}" PASSWORD="${USPS_PASSWORD}"><Address><Address1>\
        </Address1><Address2>${streetAddress}</Address2><City>${city}</City><State>${state}\
        </State><Zip5>${zipCode}</Zip5><Zip4></Zip4></Address></AddressValidateRequest>`;

        const response = await fetch(url);
        var responseText = response.text();

        if (!response.ok || (await responseText).includes("<Error>")) {
            return (responseText);
        }

        return await responseText;
    }
    catch (error) {
        return "USPS API not working.";
    }
}

// This function analyzes the response received by the USPS API and returns the message that the user will see.
function uspsResponseParser(responseText, userStreetAddress, userCity, userZipCode) {
    "use strict";
    let response = {
        "fieldID": "",
        "errorMessage": "",
        "streetAddress": "",
        "zipCode": "",
        "city": ""
    };

    if (responseText === "USPS API not working.") {
        response.errorMessage = "USPS API not working.";
        return response;
    }

    // Set error message and field.
    if (responseText.includes("Invalid Address.")) {
        response.fieldID = "street";
        response.errorMessage = usps_content["invalid-street"];
    }
    else if (responseText.includes("Address Not Found. ")) {
        response.fieldID = "street";
        response.errorMessage = usps_content["no-street"];
    }
    else if (responseText.includes("Invalid City.")) {
        response.fieldID = "city";
        response.errorMessage = usps_content["invalid-city"];
    }
    else if (responseText.includes("Invalid Zip Code.")) {
        response.fieldID = "zip";
        response.errorMessage = usps_content["invalid-zip"];
    }
    else if (responseText.includes("Multiple addresses were found")) {
        // No errors received from USPS API
        response.fieldID = "no errors";
    }
    else {
        // No errors received from USPS API
        response.fieldID = "no errors";

        // Gets the address suggested by the USPS API
        let uspsStreetAddress = responseText.slice(responseText.indexOf('<Address2>') + 10, responseText.indexOf('</Address2>'));
        let uspsZipCode = responseText.slice(responseText.indexOf('<Zip5>') + 6, responseText.indexOf('</Zip5>'));
        let uspsCity = responseText.slice(responseText.indexOf('<City>') + 6, responseText.indexOf('</City>'));
        let uspsState = responseText.slice(responseText.indexOf('<State>') + 7, responseText.indexOf('</State>'));

        // Checks if the address suggested by the USPS API is different from the user's address.
        // If it's different, it returns the USPS address in the response
        if (uspsStreetAddress.toLowerCase() !== userStreetAddress.toLowerCase() ||
            uspsCity.toLowerCase() !== userCity.toLowerCase() ||
            uspsZipCode.toLowerCase() !== userZipCode.toLowerCase()) {

            response.streetAddress = uspsStreetAddress;
            response.city = uspsCity;
            response.state = uspsState;
            response.zipCode = uspsZipCode;
        }
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
    // Analyze the response and decide if the address is valid or not.
    const uspsApiResponse = await addressUSPSValidation(streetAddressField.value, cityField.value, stateField.value, zipCodeField.value);
    const response = uspsResponseParser(uspsApiResponse, streetAddressField.value, cityField.value, zipCodeField.value);

    formFields.forEach(field => {
        let fieldID = field.previousElementSibling.id;
        var errorID = "error-" + fieldID;

        // If the current field is empty, the error style is added.
        if (!field.value || response.fieldID === fieldID) {
            errorFound = true;
            test.push(fieldID + " missing");

            // Add field border error style.
            field.classList.add("usa-user-error");
            // Adds the error style to the error message above the field.
            field.previousElementSibling.classList.add("usa-error");

            // Makes the error message in the alert box visible.
            document.getElementById(errorID).classList.remove("usa-error--alert");

            var message;
            if (!field.value) {
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
                var sanitizeResponse = DOMPurify.sanitize(response.errorMessage);
                // Change the error message above the input field.
                message = sanitizeResponse;
                // Change the error message inside the alert box.
                document.getElementById(errorID).getElementsByTagName("span")[0].innerHTML =  sanitizeResponse;
            }

            field.previousElementSibling.innerHTML = "Error: " + message;

            // Check if the street address, zip code or city field is empty and if it is, add the vertical line on the left side.
            if (field.previousElementSibling.id === "street" ||
                field.previousElementSibling.id === "zip" ||
                field.previousElementSibling.id === "city") {
                field.parentElement.classList.add("usa-border-error");
                field.parentElement.classList.add("usa-form-spacing");
            }
            // Check if the state field is empty and if it is, add the vertical line on the left side.
            else if (field.previousElementSibling.id === "state") {
                field.parentElement.parentElement.classList.add("usa-border-error");
                field.parentElement.parentElement.classList.add("usa-form-spacing");
                // Arranges the drop-down arrow within the input field.
                document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "30px";
                document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "31px";
            }
        }

        // If the current field is not empty, the error style is removed.
        else if (field.value !== "" || field.value) {
            // Remove field border error style.
            field.classList.remove("usa-user-error");

            // Remove the vertical line on the left side.
            field.parentElement.classList.remove("usa-border-error");
            field.parentElement.parentElement.classList.remove("usa-border-error");
            field.parentElement.classList.remove("usa-form-spacing");
            field.parentElement.parentElement.classList.remove("usa-form-spacing");


            // Remove the error message above the field.
            field.previousElementSibling.innerHTML = "";
            // Remove the error style to the error message above the field.
            field.previousElementSibling.classList.remove("usa-error");
            // Hide the error message from the alert box.
            document.getElementById(errorID).classList.add("usa-error--alert");

            if (field.previousElementSibling.id === "state") {
                // Arranges the drop-down arrow within the input field.
                document.getElementsByClassName("usa-combo-box__toggle-list")[0].style["top"] = "1px";
                document.getElementsByClassName("usa-combo-box__input-button-separator")[0].style["top"] = "1px";
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

    if (response.errorMessage !== "USPS API not working." &&
        response.streetAddress !== "" &&
        response.city !== "" &&
        response.zipCode !== "") {
        // Stores the suggested address for the address suggestion alert box.
        localStorage.setItem("uspsStreetAddress", response.streetAddress);
        localStorage.setItem("uspsCity", response.city);
        localStorage.setItem("uspsState", response.state);
        localStorage.setItem("uspsZipCode", response.zipCode);
        localStorage.setItem("formResubmitted", false);
    }
    else {
        localStorage.removeItem("uspsStreetAddress");
        localStorage.removeItem("uspsCity");
        localStorage.removeItem("uspsState");
        localStorage.removeItem("uspsZipCode");
        localStorage.removeItem("formResubmitted");
    }

    document.getElementById("error-box").classList.add("usa-error--alert");
    document.getElementById("myform").submit();
};

window.addEventListener("load", function () {
    "use strict";

    let clearButtonWrappers = document.getElementsByClassName("usa-combo-box__clear-input__wrapper");
    for (const clearButtonWrapper of clearButtonWrappers) {
        clearButtonWrapper.remove();
    }

    // Include the dropdown menu's toggle button in the tab order
    let toggleButtons = document.getElementsByClassName("usa-combo-box__toggle-list");
    for (const toggleButton of toggleButtons) {
        toggleButton.removeAttribute("tabindex");
    }


    // Code for autocomplete state fields
    let isChromeOrEdge = navigator.userAgent.includes("Chrome");
    // Change attributes so that autofill works in state input
    if (isChromeOrEdge) {
        let stateSelectBox = document.getElementsByName("input-state")[0];
        stateSelectBox.setAttribute("autocomplete","country");

        let stateInputBox = document.getElementById("input-state");
        stateInputBox.setAttribute("autocomplete","address-level1");
    }
});

(function() {
    "use strict";
    // Customize input validation error messages by
    // specifying the name of each input field. Only
    // applies to elements specified in the list below.
    // It also prepropulates the form if the URL has the parameters.
    let elementTypes = ["input", "textarea", "select"];

    // Stores the URL parameters
    let searchParams = getSearchParams();

    for (let i = 0; i < elementTypes.length; i++) {
        let elements = document.getElementsByTagName(elementTypes[i]);

        for (let j = 0; j < elements.length; j++) {
            // Note: all input fields should have an ID starting with "input-"
            let message = a11y_content[elements[j].id.replace("input-", "")];
            elements[j].setAttribute("oninvalid", "this.setCustomValidity('" + message + "')");
            elements[j].setAttribute("oninput", "this.setCustomValidity('')");

            let inputParam = searchParams.get(elements[j].id);
            if (elements[j].id.includes('input-') && inputParam) {

                // Prepopulates the dropdown of the states.
                if (elements[j].id === "input-state") {
                    var div = document.querySelector(`div.usa-combo-box`);
                    div.setAttribute("data-default-value", inputParam);
                }
                // Prepopulates all the input fields (street, city, zip).
                else {
                    elements[j].setAttribute('value', inputParam);
                }
            }
        }
    }
})();
