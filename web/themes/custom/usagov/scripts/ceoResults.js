/**
 * Build and execute request to look up elected officials for provided address.
 * @param {string} address Address for which to fetch elected officials info.
 * @param {function(Object)} callback Function which takes the response object as a parameter.
 */
function lookup(address, callback) {
    "use strict";
    /**
     * Request object for given parameters.
     * @type {gapi.client.HttpRequest}
     */

    let count=0;
    var timer = window.setInterval(function() {
        count++;
        if (typeof gapi.client !== "undefined") {
            window.clearInterval(timer);
            let req = gapi.client.request({
                "path": "/civicinfo/v2/representatives",
                "params": {"address": address}
            });
            req.execute(callback);
        }
    else if (count > 100) {
            // Stop trying after 100 attempts (10 seconds)
            window.clearInterval(timer);
        }
    }, 100);

}

/**
 * Render results in the DOM.
 * @param {Object} response Response object returned by the API.
 * @param {Object} rawResponse Raw response from the API.
 */
function renderResults(response, rawResponse) {
    "use strict";

    // Allow the interface to show now that we are about to have content on the page
    jQuery('.usa-prose-container').show();
    jQuery('.usa-prose-loader').hide();

    // Text strings for the page's language should be assigned to "usagovCEOtext" in
    // an inline script in the page's Header HTML. The translations here are retained for backward compatibility.
    const backupTranslations = {
        "en": {
            "error-fetch": "We're sorry. The Google Civic Information API that provides data for this tool is not working right now. Please try again later.",
            "error-fetch-heading": "Data temporarily unavailable",
            "error-address": "There was a problem getting results for this address. Please check to be sure you entered a valid U.S. address.",
            "error-address-heading": "Invalid address",
            "levels": [
                {
                    "heading": "Federal officials",
                    "description": "represent you and your state in Washington, DC."
                },{
                    "heading": "State officials",
                    "description": "represent you in your state capital."
                },{
                    "heading": "Local officials",
                    "description": "represent you in your county or city."
                }
            ],
            "local_levels": ["City officials",
                             "County officials"],
            "party-affiliation": "Party affiliation",
            "address": "Address",
            "phone-number": "Phone number",
            "website": "Website",
            "contact-via-email": "Contact via email",
            "path-contact": "/elected-officials-email",
        },
        "es": {
            "error-fetch": "Lo sentimos. Pero la API de información cívica de Google que provee los datos al sistema de búsqueda no está funcionando. Por favor, intente de nuevo más tarde.",
            "error-fetch-heading": "Datos no disponibles temporalmente",
            "error-address": "Tuvimos problemas para obtener resultados con esta dirección. Por favor, verifique si ingresó una dirección válida en EE. UU.",
            "error-address-heading": "Dirección incorrecta",
            "levels": [
                {
                    "heading": "Funcionarios federales",
                    "description": "que le representan a usted y a su estado en Washington, DC."
                },{
                    "heading": "Funcionarios estatales",
                    "description": "que le representan en la capital de su estado."
                },{
                    "heading": "Funcionarios locales",
                    "description": "que le representan en su condado o ciudad."
                }
            ],
            "local_levels": ["Funcionarios de ciudades",
                             "Funcionarios de condados"],
            "party-affiliation": "Afiliación de partido",
            "address": "Dirección",
            "phone-number": "Teléfono",
            "website": "Sitio web",
            "contact-via-email": "Contactar por correo electrónico",
            "path-contact": "/es/funcionarios-electos-correo-electronico",
        }
    };

    // const content = (typeof usagovCEOtext !== "undefined") ? usagovCEOtext : backupTranslations[ document.documentElement.lang ];
    const content = backupTranslations[ document.documentElement.lang ];

    // Get location for where to attach the rendered results
    let resultsDiv = document.getElementById("results");

    // No response received - return error
    if (!response) {
        resultsDiv.appendChild(document.createTextNode(
            content["error-fetch"]
        ));
        dataLayer.push({
            'event': 'CEO API Error',
            'error type': "no-response-from-api"
        });
        return;
    }
    if (response.error) {
        let errorType;
        switch (response.error.code) {
            case 400: // Failed to parse address or No address provided
            case 404: // No information for this address
            case 409: // Conflicting information for this address
                errorType = "error-address";
                break;
            case 401: // The request was not appropriately authorized
            case 403: // Too many OCD IDs retrieved
            case 503: // backendError
            default:
                errorType = "error-fetch";
                break;
        }
        let h1 = document.getElementById("skip-to-h1");
        let resultsSection = document.getElementById("resultsSection");
        let intro = document.getElementsByClassName("usa-intro")[0];

        h1.innerHTML = content[""+errorType+"-heading"];
        resultsSection.innerHTML = "";
        intro.innerHTML = content[errorType];
        intro.style.paddingBottom = '20px';
        dataLayer.push({
            'event': 'CEO API Error',
            'error type': errorType,
            'error code': ''+response.error.code,
            'error detail': response.error.message
        });
        return;
    }

    // Assign office and level to each elected official
    for (let i = 0; i < response.offices.length; i++) {
        for (let j = 0; j < response.offices[i].officialIndices.length; j++) {
            let officialIndex = response.offices[i].officialIndices[j];
            response.officials[officialIndex].office = response.offices[i].name;
            if (response.offices[i].levels) {
                response.officials[officialIndex].level = response.offices[i].levels[0];
            }
        }
    }

    // If elected officials were actually found:
    if (response.officials.length > 0) {
        // Indicates if the accordion of city officials has results/officials. By default this variable indicates that it has no results.
        let cityHasResults = false;
        // Indicates if the accordion of county officials has results/officials. By default this variable indicates that it has no results.
        let countyHasResults = false;
        // Indicates if the accordion of state officials has results/officials. By default this variable indicates that it has no results.
        let stateHasResults = false;

        // Create container for rendering results
        let container = document.createElement("div");
        container.setAttribute("class", "usa-accordion");

        // Create an accordion for each level of elected officials
        const levels = content["levels"];
        for (let i = 0; i < levels.length; i++) {
            let levelName = levels[i];
            let levelNameID = replaceSpaces(levelName.heading);

            let accordionHeader = document.createElement("h2");
            accordionHeader.setAttribute("class", "usa-accordion__heading");
            accordionHeader.setAttribute("id", "heading_" + levelNameID);

            let accordionHeaderButton = document.createElement("button");
            accordionHeaderButton.setAttribute("class", "usa-accordion__button");
            accordionHeaderButton.setAttribute("aria-expanded", "false");

            accordionHeaderButton.setAttribute("aria-controls", levelNameID);
            accordionHeaderButton.innerHTML = `${levelName.heading} <span class='usa-normal'>${levelName.description}</span>`;

            accordionHeader.appendChild(accordionHeaderButton);

            let accordionContent = document.createElement("div");
            accordionContent.setAttribute("id", levelNameID);
            accordionContent.setAttribute("class", "usa-accordion usa-accordion__content usa-prose");
            accordionContent.setAttribute("hidden", "until-found");

            container.appendChild(accordionHeader);
            container.appendChild(accordionContent);
        }

        // Append container to the location for rendered results
        resultsDiv.appendChild(container);

        // Create an accordion for each level of elected officials
        const local_levels = content["local_levels"];
        for (let i = 0; i < local_levels.length; i++) {
            let levelName = local_levels[i];
            let levelNameID = replaceSpaces(levelName);

            let accordionHeader = document.createElement("h3");
            accordionHeader.setAttribute("class", "usa-accordion__heading");
            accordionHeader.setAttribute("id", "heading_" + levelNameID);

            let accordionHeaderButton = document.createElement("button");
            accordionHeaderButton.setAttribute("class", "usa-accordion__button");
            accordionHeaderButton.setAttribute("aria-expanded", "false");

            accordionHeaderButton.setAttribute("aria-controls", levelNameID);
            accordionHeaderButton.innerHTML = levelName;

            accordionHeader.appendChild(accordionHeaderButton);

            let accordionContent = document.createElement("div");
            accordionContent.setAttribute("id", levelNameID);
            accordionContent.setAttribute("class", "usa-accordion usa-accordion__content usa-prose");
            accordionContent.setAttribute("hidden", "until-found");

            // Adds the sub-accordion to the Local officials accordion.
            // Note: If the Local officials accordion is not the last accordion in the container, you will need to change it.
            container.lastElementChild.appendChild(accordionHeader);
            container.lastElementChild.appendChild(accordionContent);
        }

        // Create an accordion section for each elected official
        for (let i = 0; i < response.officials.length; i++) {

            let accordionHeader = document.createElement("h4");
            accordionHeader.setAttribute("class", "usa-accordion__heading");

            let accordionHeaderButton = document.createElement("button");
            accordionHeaderButton.setAttribute("class", "usa-accordion__button");
            accordionHeaderButton.setAttribute("aria-expanded", "false");

            var officialNumber = "Official_" + i;
            accordionHeaderButton.setAttribute("aria-controls", officialNumber);
            accordionHeaderButton.innerHTML =  response.officials[i].office + ", " + response.officials[i].name;

            accordionHeader.appendChild(accordionHeaderButton);

            let accordionContent = document.createElement("div");
            accordionContent.setAttribute("id", officialNumber);
            accordionContent.setAttribute("class", "usa-accordion__content usa-prose");
            accordionContent.setAttribute("hidden", "until-found");

            // Create bullet list of details for the elected official
            let bulletList = document.createElement("ul");
            bulletList.classList.add("add-list-reset");

            // Display party affiliation
            // NOTE: unlike other details, this field will display
            // "none provided" if no party is specified. This is
            // the only mandatory detail for each elected official
            // (so the accordion isn't blank if there are no details.
            let party = response.officials[i].party || "none provided";
            let nextElem = document.createElement("li");
            nextElem.classList.add("padding-bottom-2");
            nextElem.innerHTML = `<div class="text-bold">${content["party-affiliation"]}:</div><div>${party}<div>`;
            bulletList.appendChild(nextElem);

            // Display address, if provided
            let address = response.officials[i].address || "none provided";
            nextElem = document.createElement("li");
            nextElem.classList.add("padding-bottom-2");
            if (address !== "none provided") {
                // Normalize address
                address = address[0].line1 + "<br>" + address[0].city + ", " + address[0].state + " " + address[0].zip;

                nextElem = document.createElement("li");
            nextElem.classList.add("padding-bottom-2");
                nextElem.innerHTML = `<div class="text-bold">${content["address"]}:</div><div>${address}</div>`;

                bulletList.appendChild(nextElem);
            }

            // Display phone number, if provided
            let phoneNumber = response.officials[i].phones || "none provided";
            if (phoneNumber !== "none provided") {
                // Select first phone number and create clickable link
                let linkToPhone = `<a href="tel:${phoneNumber[0]}">${phoneNumber[0]}</a>`;

                nextElem = document.createElement("li");
                nextElem.classList.add("padding-bottom-2");
                nextElem.innerHTML = `<div class="text-bold">${content["phone-number"]}:</div><div>${linkToPhone}</div>`;
                // nextElem.appendChild(linkToPhone);

                bulletList.appendChild(nextElem);
            }

            // Display website, if provided
            let website = response.officials[i].urls || "none provided";
            if (website !== "none provided") {

                // Shorten the link and remove unnecessary characters
                let cleanLink = response.officials[i].urls[0]
                    .replace("https://", "").replace("http://", "").replace("www.", "");
                if (cleanLink[cleanLink.length - 1] === "/") {
                    cleanLink = cleanLink.slice(0, -1);
                }
                let link=`<a class="ceoLink" href="${response.officials[i].urls[0]}">${cleanLink}</a>`;
                // link.innerHTML = cleanLink;

                nextElem = document.createElement("li");
                nextElem.classList.add("padding-bottom-2");
                // nextElem.innerHTML = "<div class="text-bold">"+content["website"]+":</div><div>";
                nextElem.innerHTML = `<div class="text-bold">${content["website"]}:</div><div>${link}</div>`;
                // nextElem.appendChild(link);

                bulletList.appendChild(nextElem);
            }

            // Display social media accounts, if provided
            let socials = response.officials[i].channels || "none provided";
            if (socials !== "none provided") {
                for (let j = 0; j < socials.length; j++) {
                    // Create appropriate type of link
                    // for each social media account
                    nextElem = document.createElement("li");
                    nextElem.classList.add("padding-bottom-2");
                    let socialOptions = {
                        "twitter": "https://x.com/",
                        "facebook": "https://facebook.com/",
                        "youtube": "https://youtube.com/",
                        "linkedin": "https://linkedin.com/in/"
                    };
                    let social = socials[j].type.toLowerCase();
                    if (social in socialOptions) {
                        if (socials[j].type === "Twitter") {
                            nextElem.innerHTML = `<div class="text-bold">X:</div><div><a href="${socialOptions[social]}${socials[j].id}">@${socials[j].id}</div>`;
                        }
                        else {
                            nextElem.innerHTML = `<div class="text-bold">${socials[j].type}:</div><div><a href="${socialOptions[social]}${socials[j].id}">@${socials[j].id}</div>`;
                        }
                    }
                    bulletList.appendChild(nextElem);
                }
            }

            // Display email via contact button, if provided
            let email = response.officials[i].emails || "none provided";
            if (email !== "none provided") {
                // let primaryEmail = document.createElement("button");
                let linkToContact = document.createElement("button");
                let firstEmail = email[0];

                linkToContact.setAttribute("class", "usa-button usagov-button state-email");
                linkToContact.style.marginTop = "15px";
                linkToContact.style.marginBottom = "8px";
                linkToContact.innerHTML = content["contact-via-email"];

                // Build search params for email page.
                let searchParams = getSearchParams();
                searchParams.set('email', firstEmail);
                searchParams.set('name', response.officials[i].name);
                searchParams.set('office', response.officials[i].office);
                linkToContact.setAttribute("role","button");
                linkToContact.setAttribute("onclick", "window.location.href = '" + content["path-contact"] + "?"
                                           + searchParams.toString() + "#skip-to-h1'");
                // Append bullet list of details to accordion
                accordionContent.appendChild(bulletList);
                accordionContent.appendChild(linkToContact);
            }
            else {
                // Append bullet list of details to accordion
                accordionContent.appendChild(bulletList);
            }

            // Determine under which level accordion the elected official section should be appended
            let appendLocation;
            let level = response.officials[i].level;

            // Add the Mayor to the City officials accordion
            // There are some Mayors, such as the Mayor of Anchorage, that do not appear at the city level.
            if (response.officials[i].office.toLowerCase().includes("mayor") &&
                response.officials[i].office.toLowerCase().includes(response.normalizedInput.city.toLowerCase())) {
                appendLocation = document.getElementById(replaceSpaces(content["local_levels"][0]));
                cityHasResults = true;
            }
            // Add to Federal officials accordion
            else if (level === "country") {
                appendLocation = document.getElementById(replaceSpaces(content["levels"][0].heading));
            }
            // Add to State officials accordion
            else if (level === "administrativeArea1") {
                appendLocation = document.getElementById(replaceSpaces(content["levels"][1].heading));
                // Change the variable to indicate that it does have results.
                stateHasResults = true;
            }
            // Add to County officials accordion
            else if (level === "administrativeArea2") {
                appendLocation = document.getElementById(replaceSpaces(content["local_levels"][1]));
                // Change the variable to indicate that it does have results.
                countyHasResults = true;
            }
            // Add to City officials accordion
            else {
                appendLocation = document.getElementById(replaceSpaces(content["local_levels"][0]));
                // Change the variable to indicate that it does have results.
                cityHasResults = true;
            }

            // Append elected official section to the appropriate level accordion
            appendLocation.appendChild(accordionHeader);
            appendLocation.appendChild(accordionContent);
        }

        // Hides the City officials accordion if no results
        let cityHeaderID = "heading_" + replaceSpaces(content["local_levels"][0]);
        if (!cityHasResults) {
            document.getElementById(cityHeaderID).classList.add("usa-accordion__heading-hidden");
        }
        else {
            document.getElementById(cityHeaderID).classList.remove("usa-accordion__heading-hidden");
        }

        // Hides the County officials accordion if no results
        let countyHeaderID = "heading_" + replaceSpaces(content["local_levels"][1]);
        if (!countyHasResults) {
            document.getElementById(countyHeaderID).classList.add("usa-accordion__heading-hidden");
        }
        else {
            document.getElementById(countyHeaderID).classList.remove("usa-accordion__heading-hidden");
        }

        // Hides the State officials accordion if no results
        let stateHeaderID = "heading_" + replaceSpaces(content["levels"][1].heading);
        if (!stateHasResults) {
            document.getElementById(stateHeaderID).classList.add("usa-accordion__heading-hidden");
        }
        else {
            document.getElementById(stateHeaderID).classList.remove("usa-accordion__heading-hidden");
        }
    }
    else {
        // No elected officials found - return error
        resultsDiv.appendChild(document.createTextNode(
            content["error-address"]
        ));
        dataLayer.push({
            'event': 'CEO API Error',
            'error type': "no-officials-from-api"
        });
    }
}

/**
 * Initialize API client by setting the API key.
 */
 function setApiKey() {
    "use strict";
    gapi.client.setApiKey("AIzaSyDgYFMaq0e-u3EZPPhTrBN0jL1uoc8Lm0A");
}

// This function is called when the user clicks the link inside the address suggestion alert box.
// When the function is called, it will change the search values with the suggested address so that the user gets the new results.
function resubmitForm() {
    'use strict';
    let searchParams = getSearchParams();
    localStorage.setItem("formResubmitted", true);

    var inputStreet = localStorage.getItem("uspsStreetAddress");
    var inputCity = localStorage.getItem("uspsCity");
    var inputState = localStorage.getItem("uspsState");
    var inputZip = localStorage.getItem("uspsZipCode");

    searchParams.set('input-street', inputStreet);
    searchParams.set('input-city', inputCity);
    searchParams.set('input-state', inputState);
    searchParams.set('input-zip', inputZip);

    window.location.search = searchParams.toString();
}

/**
 * Process form data, display the address, and search for elected officials.
 */
function load() {
    "use strict";
    let searchParams = getSearchParams();

    let inputStreet = searchParams.get('input-street');
    let inputCity = searchParams.get('input-city');
    let inputState = searchParams.get('input-state');

    let inputZip = searchParams.get('input-zip');
    let normalizedAddress = inputStreet + ", " + inputCity + ", " + inputState + " " + inputZip;
    let displayAddress = document.getElementById("display-address");
    displayAddress.innerHTML = DOMPurify.sanitize(normalizedAddress.replace(", ", "<br>"));

    // Displays USPS address suggestions, if any.
    if (localStorage.getItem("formResubmitted") === "false") {
        // USPS Address Suggestions Translations
        const usps_suggestion_content = document.documentElement.lang === "en" ?
        {
            "suggestion-heading": "Address suggestion",
            "suggestion-message": "We optimized the address you provided for accuracy:",
            "suggestion-link-text": "Use this address for your search"
        }
        :
        {
            "suggestion-heading": "Dirección sugerida",
            "suggestion-message": "Optimizamos la dirección que usted proporcionó para mayor precisión:",
            "suggestion-link-text": "Utilizar esta dirección para la búsqueda"
        };

        // USPS Address Suggestion Alert Box
        let suggestedAddress = localStorage.getItem("uspsStreetAddress") + ", " + localStorage.getItem("uspsCity") + ", " + inputState + " " + localStorage.getItem("uspsZipCode");
        let addressSuggestionAlert = document.createElement('div');
        addressSuggestionAlert.setAttribute('class', 'usa-alert usa-alert--info');
        addressSuggestionAlert.innerHTML = `<div class="usa-alert__body">
                        <h2 class="usa-alert__heading">${usps_suggestion_content["suggestion-heading"]}</h2>
                        <p class="usa-alert__text">
                            ${usps_suggestion_content["suggestion-message"]}
                            <p>
                                ${DOMPurify.sanitize(suggestedAddress.replace(", ", "<br>"))}
                            </p>
                            <a class="usa-link" href='#skip-to-h1' onclick="resubmitForm()">
                                ${usps_suggestion_content["suggestion-link-text"]}
                            </a>
                        </p>
                        </div>`;

        // Adds the USPS address suggestion alert box above the "Your address:" section.
        // Note: Make sure the "Your address:" header in the cms has the ID "address-heading".
        displayAddress.parentNode.parentNode.insertBefore(addressSuggestionAlert, document.getElementById("address-heading"));

    }

    // Checks if the element exists in the CMS content.
    var editAddressLink = document.getElementById("edit-address-link");
    if (editAddressLink) {
        let link = document.createElement('a');

        // Add the href and link text depending on the language.
        if (document.documentElement.lang === "en") {
            link.setAttribute('href', `/elected-officials${window.location.search}`);
            link.innerHTML = "Edit my address";
        }
        else {
            link.setAttribute('href', `/es/funcionarios-electos${window.location.search}`);
            link.innerHTML = "Editar mi dirección";
        }

        // Add the link to the <p> element in the cms.
        editAddressLink.appendChild(link);
    }

    lookup(normalizedAddress, renderResults);
}

function getSearchParams() {
    "use strict";
    const paramsString = window.location.search;
    const searchParams = new URLSearchParams(paramsString);
    return searchParams;
}

function replaceSpaces(string) {
    "use strict";
    return string.toLowerCase().replaceAll(" ", "_");
}

// Load the GAPI Client Library
gapi.load("client", setApiKey);
document.addEventListener('DOMContentLoaded', function() {
    "use strict";
    load ();
});
