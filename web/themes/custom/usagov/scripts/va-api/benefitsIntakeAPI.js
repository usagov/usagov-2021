// POST /upload
// This request needs to be done before uploading the doc. 
// It's going to return the URL that will be used to upload the doc
async function uploadRequest() {
    const myHeaders = new Headers();
    myHeaders.append("apikey", "");
    myHeaders.append("accept", "application/json");
    myHeaders.append("Content-Type", "application/x-www-form-urlencoded");

    const urlencoded = new URLSearchParams();
    urlencoded.append("{}", "");

    const requestOptions = {
        method: "POST",
        headers: myHeaders,
        body: urlencoded,
        redirect: "follow"
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

    // Get the form data
    const veteranFirstNameField = document.getElementById("input-veterans-first-name");
    const veteranLastNameField = document.getElementById("input-veterans-last-name");
    const veteranZipCodeField = document.getElementById("input-veterans-zip");
    const veteranSSNField = document.getElementById("input-veterans-ssn");

    const formData = new FormData();

    var metadataJSON = { 
        veteranFirstName: veteranFirstNameField.value,
        veteranLastName: veteranLastNameField.value,
        fileNumber: veteranSSNField.value,
        zipCode: veteranZipCodeField.value,
        source: "USA.gov",
        docType: "21P-530EZ"
    }

    const blob = new Blob([JSON.stringify(metadataJSON)], { type: 'application/json' });
    formData.append('metadata', blob);

     // A file <input> element
    const fileInput = document.querySelector("#va-pdf-form");
    const formPDF = fileInput.files[0];
    formData.append("content", formPDF, formPDF.name);

    // const myHeaders = new Headers();
    
    // myHeaders.append('Access-Control-Allow-Origin', 'http://localhost');
    // myHeaders.append('Access-Control-Request-Method', 'PUT');

    const requestOptions = {
        method: "PUT",
        body: formData,
        // headers: myHeaders,
        redirect: "follow"
    };

    try {
        const response = await fetch(fileLocationURL, requestOptions);
        
        if (!response.ok) {
          throw new Error(`Response status: ${response.status}`);
        }
    
        const json = await response.json();
        console.log(json);
    } 
    catch (error) {
        console.error(error.message);
    }
}

const downloadButton = document.querySelector("#download-button");
downloadButton.setAttribute('download', '');

const submitButton = document.querySelector("#submitButton");

submitButton.addEventListener("click", async () => {
    const fileLocationURL = await uploadRequest();
    documentUploadRequest(fileLocationURL);
});
