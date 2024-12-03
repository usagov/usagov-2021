// POST /upload
// This request needs to be done before uploading the doc.
// It's going to return the URL that will be used to upload the doc
async function uploadRequest() {
    "use strict";

    const myHeaders = new Headers();
    myHeaders.append("apikey", "DsJFWbW81yAs30qVbluxnTwd3zb0an1H");
    myHeaders.append("accept", "application/json");
    myHeaders.append("Content-Type", "application/x-www-form-urlencoded");

    const urlencoded = new URLSearchParams();
    urlencoded.append("{}", "");

    const requestOptions = {
        "method": "POST",
        "headers": myHeaders,
        "body": urlencoded,
        "redirect": "follow"
    };

    try {
        const response = await fetch("https://sandbox-api.va.gov/services/vba_documents/v1/uploads", requestOptions);

        if (!response.ok) {
          throw new Error(`Response status: ${response.status}`);
        }

        const json = await response.json();
        const fileLocation = json.data.attributes.location;
        console.log(fileLocation);
        return fileLocation;
    }
    catch (error) {
        console.error(error.message);
    }
}

async function documentUploadRequest(fileLocationURL) {
    "use strict";
    // Get the form data
    const veteranFirstNameField = document.getElementById("input-veterans-first-name");
    const veteranLastNameField = document.getElementById("input-veterans-last-name");
    const veteranZipCodeField = document.getElementById("input-veterans-zip");
    const veteranSSNField = document.getElementById("input-veterans-ssn");

    const formData = new FormData();

    var metadataJSON = {
        "veteranFirstName": veteranFirstNameField.value,
        "veteranLastName": veteranLastNameField.value,
        "fileNumber": veteranSSNField.value,
        "zipCode": veteranZipCodeField.value,
        "source": "USA.gov",
        "docType": "21P-530EZ"
    };
    
    const blob = new Blob([JSON.stringify(metadataJSON)], {"type": 'application/json'});
    formData.append('metadata', blob);

     // A file <input> element
    const fileInput = document.querySelector("#va-pdf-form");
    const formPDF = fileInput.files[0];
    formData.append("content", formPDF, formPDF.name);

    // const myHeaders = new Headers();
    // myHeaders.append('Access-Control-Allow-Origin', 'http://localhost');
    // myHeaders.append('Access-Control-Request-Method', 'PUT');

    const requestOptions = {
        "method": "PUT",
        "body": formData,
        // headers: myHeaders,
        "redirect": "follow"
    };

    try {
        const response = await fetch(fileLocationURL, requestOptions);
        
        if (!response.ok) {
          throw new Error(`Response status: ${response.status}`);
        }

        return response.status === 200;
    
        // const json = await response.json();
        // console.log(json);
    } 
    catch (error) {
        console.error(error.message);
    }
}

function vaFormHandler() {
    "use strict";
    // stop form submission
    let test = [];
    let errorFound = false;

    const firstNameField = document.getElementById("input-veterans-first-name");
    const lastNameField = document.getElementById("input-veterans-last-name");
    const ssnField = document.getElementById("input-veterans-ssn");
    const zipCodeField = document.getElementById("input-veterans-zip");
    const formFields = [firstNameField, lastNameField, ssnField, zipCodeField];

    formFields.forEach(field => {
        let fieldID = field.previousElementSibling.id;
        var errorID = "error-" + fieldID;

        // If the current field is empty, the error style is added.
        if (!field.value) {
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
            field.parentElement.classList.add("usa-border-error");
            field.parentElement.classList.add("usa-form-spacing");
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

        return errorFound;
    }

    document.getElementById("error-box").classList.add("usa-error--alert");
    return errorFound;
}

const submitButton = document.querySelector("#submitButton");

submitButton.addEventListener("click", async () => {
    "use strict";
    const errorFound = vaFormHandler();
    if (!errorFound){
        const fileLocationURL = await uploadRequest();
        const response = await documentUploadRequest(fileLocationURL);
        if (response) {
            document.getElementById("VABenefitsIntakeForm").submit();
        }
    }
});

