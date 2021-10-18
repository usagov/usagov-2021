/**
 * Build and execute request to look up elected officials for provided address.
 * @param {string} address Address for which to fetch elected officials info.
 * @param {function(Object)} callback Function which takes the response object as a parameter.
 */
function lookup(address, callback) {
    /**
     * Request object for given parameters.
     * @type {gapi.client.HttpRequest}
     */
     setTimeout(function(){
        //TODO: Find a better way to call load function when the page is ready
        let req = gapi.client.request({
            "path" : "/civicinfo/v2/representatives",
            "params" : {"address" : address}
        });
        req.execute(callback);
    }, 200);
    
}

/**
 * Render results in the DOM.
 * @param {Object} response Response object returned by the API.
 * @param {Object} rawResponse Raw response from the API.
 */
function renderResults(response, rawResponse) {
    let language="english";
    if( document.documentElement.lang == 'es' ){
        language="spanish";
    }
    const translations = {
        "english": {
            "error-fetch": "ERROR: Failed trying to fetch elected officials!",
            "error-address": "ERROR: Could not find elected officials for given address!",
            "levels": ["Federal Officials", "State Officials", "Local Officials"],
            "party-affiliation": "Party Affiliation",
            "address": "Address",
            "phone-number": "Phone Number",
            "website": "Website",
            "contact-via-email": "Contact via Email",
            "path-contact": "/contact-elected-officials/email",
        },
        "spanish": {
            "error-fetch": "ERROR: Failed trying to fetch elected officials!",
            "error-address": "ERROR: Could not find elected officials for given address!",
            "levels": ["Funcionarios federales", "Funcionarios estatales", "Funcionarios locales"],
            "party-affiliation": "Afiliación de partido",
            "address": "Dirección",
            "phone-number": "Teléfono",
            "website": "Sitio web",
            "contact-via-email": "Contactar por correo electrónico",
            "path-contact": "/es/funcionarios-electos/email",
        }
    }
    let content=translations[language];

    // Get location for where to attach the rendered results
    let resultsDiv = document.getElementById("results");

    // No response received - return error
    if (!response || response.error) {
        resultsDiv.appendChild(document.createTextNode(
            content["error-fetch"]
        ));
        return;
    }

    // Assign office and level to each elected official
    for (let i = 0; i < response.offices.length; i++) {
        for (let j = 0; j < response.offices[i].officialIndices.length; j++) {
            let officialIndex = response.offices[i].officialIndices[j];
            response.officials[officialIndex].office = response.offices[i].name;
            response.officials[officialIndex].level = response.offices[i].levels[0];
        }
    }

    // If elected officials were actually found:
    if (response.officials.length > 0) {
        // Create container for rendering results
        let container = document.createElement("div");
        container.setAttribute("class", "usa-accordion");
        container.setAttribute("aria-multiselectable", "true")

        // Create an accordion for each level of elected officials
        const levels = content["levels"];
        for (let i = 0; i < levels.length; i++) {
            let accordionHeader = document.createElement("h2");
            accordionHeader.setAttribute("class", "usa-accordion__heading");

            let accordionHeaderButton = document.createElement("button");
            accordionHeaderButton.setAttribute("class", "usa-accordion__button");
            accordionHeaderButton.setAttribute("aria-expanded", "true");

            let levelName = levels[i];
            accordionHeaderButton.setAttribute("aria-controls", levelName);
            accordionHeaderButton.innerHTML = levelName;

            accordionHeader.appendChild(accordionHeaderButton);

            let accordionContent = document.createElement("div");
            accordionContent.setAttribute("id", levelName);
            accordionContent.setAttribute("class", "usa-accordion__content usa-prose")

            container.appendChild(accordionHeader);
            container.appendChild(accordionContent);
        }

        // Append container to the location for rendered results
        resultsDiv.appendChild(container);

        // Create an accordion section for each elected official
        for (let i = 0; i < response.officials.length; i++) {
            // let titleHeader = document.createElement("h3");
            // titleHeader.setAttribute("class", "font-serif-md");
            // titleHeader.style.color = "rgb(26, 54, 85)";
            // titleHeader.innerHTML = response.officials[i].name + ", " + response.officials[i].office;

            let accordionHeader = document.createElement("h4");
            accordionHeader.setAttribute("class", "usa-accordion__heading");

            let accordionHeaderButton = document.createElement("button");
            accordionHeaderButton.setAttribute("class", "usa-accordion__button");
            accordionHeaderButton.setAttribute("aria-expanded", "true");

            var officialNumber = "Official #" + i;
            accordionHeaderButton.setAttribute("aria-controls", officialNumber);
            accordionHeaderButton.innerHTML = response.officials[i].name + ", " + response.officials[i].office;

            accordionHeader.appendChild(accordionHeaderButton);

            let accordionContent = document.createElement("div");
            accordionContent.setAttribute("id", officialNumber);
            accordionContent.setAttribute("class", "usa-accordion__content usa-prose")

            // Create bullet list of details for the elected official
            let bulletList = document.createElement("ul");

            // Display party affiliation
            // NOTE: unlike other details, this field will display
            // "none provided" if no party is specified. This is
            // the only mandatory detail for each elected official
            // (so the accordion isn't blank if there are no details.
            let party = response.officials[i].party || "none provided";
            let nextElem = document.createElement("li");
            nextElem.innerHTML = "<b>"+content["party-affiliation"]+":</b> " + party;
            bulletList.appendChild(nextElem);

            // Display address, if provided
            let address = response.officials[i].address || "none provided";
            nextElem = document.createElement("li");
            if (address != "none provided") {
                // Normalize address
                address = address[0].line1 + ", " + address[0].city + ", " + address[0].state + " " + address[0].zip;

                nextElem = document.createElement("li");
                nextElem.innerHTML = "<b>"+content["address"]+":</b> " + address;

                bulletList.appendChild(nextElem);
            }

            // Display phone number, if provided
            let phoneNumber = response.officials[i].phones || "none provided";
            if (phoneNumber != "none provided") {
                // Select first phone number and create clickable link
                let linkToPhone = document.createElement("a");
                linkToPhone.setAttribute("href", "tel:" + phoneNumber[0]);
                linkToPhone.innerHTML = phoneNumber[0];

                nextElem = document.createElement("li");
                nextElem.innerHTML = "<b>"+content["phone-number"]+":</b> ";
                nextElem.appendChild(linkToPhone);

                bulletList.appendChild(nextElem);
            }

            // Display website, if provided
            let website = response.officials[i].urls || "none provided";
            if (website != "none provided") {
                let link = document.createElement("a");
                link.setAttribute("href", response.officials[i].urls[0]);

                // Shorten the link and remove unnecessary characters
                let cleanLink = response.officials[i].urls[0]
                    .replace("https://", "").replace("http://", "").replace("www.", "");
                if (cleanLink[cleanLink.length - 1] == "/") {
                    cleanLink = cleanLink.slice(0, -1);
                }
                link.innerHTML = cleanLink;

                nextElem = document.createElement("li");
                nextElem.innerHTML = "<b>"+content["website"]+":</b> ";
                nextElem.appendChild(link);

                bulletList.appendChild(nextElem);
            }

            // Display social media accounts, if provided
            let socials = response.officials[i].channels || "none provided";
            if (socials != "none provided") {
                for (let j = 0; j < socials.length; j++) {
                    // Create appropriate type of link
                    // for each social media account
                    let linkToSocial = document.createElement("a");
                    if (socials[j].type.toLowerCase() == "twitter") {
                        linkToSocial.setAttribute("href", "https://twitter.com/" + socials[j].id);
                    } else if (socials[j].type.toLowerCase() == "facebook") {
                        linkToSocial.setAttribute("href", "https://facebook.com/" + socials[j].id);
                    } else if (socials[j].type.toLowerCase() == "youtube") {
                        linkToSocial.setAttribute("href", "https://youtube.com/" + socials[j].id);
                    } else if (socials[j].type.toLowerCase() == "linkedin") {
                        linkToSocial.setAttribute("href", "https://linkedin.com/in/" + socials[j].id);
                    }
                    linkToSocial.innerHTML = "@" + socials[j].id;

                    nextElem = document.createElement("li");
                    nextElem.innerHTML = "<b>" + socials[j].type + ":</b> ";
                    nextElem.appendChild(linkToSocial);

                    bulletList.appendChild(nextElem);
                }
            }

            // Display email via contact button, if provided
            let email = response.officials[i].emails || "none provided";
            if (email != "none provided") {
                let primaryEmail = document.createElement("button");
                let linkToContact = document.createElement("a");
                let emailLinkified = email[0].replace("@", "_");

                primaryEmail.setAttribute("class", "usa-button usa-button--accent-cool");
                primaryEmail.style.marginTop = "15px";
                primaryEmail.innerHTML = content["contact-via-email"];

                linkToContact.setAttribute("href", content["path-contact"] + "?email=" + emailLinkified +
                    "?name=" + response.officials[i].name + "?office=" + response.officials[i].office);
                linkToContact.appendChild(primaryEmail);

                bulletList.appendChild(linkToContact);
            }

            // Append bullet list of details to accordion
            accordionContent.appendChild(bulletList);

            // Determine under which level accordion the elected official section should be appended
            let appendLocation;
            let level = response.officials[i].level;
            if (level == "country") {
                appendLocation = document.getElementById(content["levels"][0]);
            } else if (level == "administrativeArea1") {
                appendLocation = document.getElementById(content["levels"][1]);
            }  else {
                appendLocation = document.getElementById(content["levels"][2]);
            }

            // Append elected official section to the appropriate level accordion
            // appendLocation.appendChild(titleHeader);
            appendLocation.appendChild(accordionHeader);
            appendLocation.appendChild(accordionContent);
        }
    } else {
        // No elected officials found - return error
        resultsDiv.appendChild(document.createTextNode(
            content["error-address"]
        ));
    }
}

/**
 * Initialize API client by setting the API key.
 */
 function setApiKey() {
    gapi.client.setApiKey("AIzaSyCYx2xTtqC-Ayn9oQMQ0OOT0zV_53p_zXI");
}

/**
 * Process form data, display the address, and search for elected officials.
 */
function load() {
    let inputStreet = window.location.href.split("input-street=")[1].split("&")[0].split("+").join(" ");
    let inputCity = window.location.href.split("input-city=")[1].split("&")[0].split("+").join(" ");
    let inputState = window.location.href.split("input-state=")[1].split("&")[0];
    let inputZip = window.location.href.split("input-zip=")[1].split("&")[0];

    let normalizedAddress = inputStreet + ", " + inputCity + ", " + inputState + " " + inputZip;

    let displayAddress = document.getElementById("display-address");
    displayAddress.innerHTML = normalizedAddress.replace(", ", "<br>");

    // Trigger offline testing based on specific input
    if (normalizedAddress == "123 Main Street, Somewhere, DC 12345") {
        console.log("[DEBUG] Offline testing enabled!");
        displayAddress.innerHTML += "<br>[DEBUG: Offline Testing Enabled]"

        renderResults(offlineResponse, null);
        return;
    }

    lookup(normalizedAddress, renderResults);
}

// Load the GAPI Client Library
gapi.load("client", setApiKey);

// Mock response for offline testing
var offlineResponse = {
    offices: [
        {
            name: "Website Creator",
            levels: ["country"],
            officialIndices: [0, 1],
        },
        {
            name: "Governor",
            levels: ["administrativeArea1"],
            officialIndices: [2],
        },
        {
            name: "Mayor",
            levels: ["locality"],
            officialIndices: [3],
        },
    ],
    officials: [
        {
            name: "Charlie Liu",
            party: "General Services Administration",
            address: [{line1: "123 Main Street", city: "Somewhere", state: "DC", zip: "12345"}],
            phones: ["(123) 456-7890"],
            urls: ["https://usa.gov/elected-officials"],
            channels: [{type: "LinkedIn", id: "cliu13"}],
            emails: ["charlie.liu@gsa.gov"],
        },
        {
            name: "Jacob Cuomo",
            party: "General Services Administration",
            address: [{line1: "123 Main Street", city: "Somewhere", state: "DC", zip: "12345"}],
            phones: ["(123) 456-7890"],
            urls: ["https://usa.gov/elected-officials"],
            channels: [{type: "LinkedIn", id: "jacob-cuomo-659937125"}],
            emails: ["jacob.cuomo@gsa.gov"],
        },
        {
            name: "John Smith",
            party: "Democratic Party",
        },
        {
            name: "Jane Doe",
            party: "Republican Party",
        },
    ],
};

document.body.onload = load();