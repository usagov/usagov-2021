/**
 * Display name and office for recipient of message, and also
 * add their email to backup message at the bottom of the page.
 */


 const contact_translations = {
    "en": {
        "topic": "Please fill out the topic field.",
        "about": "Please fill out the about field.",
        "action": "Please fill out the action field.",
        "new_window": "Your message has been written, and a new window on the screen has opened with your email. Make sure to click send!",
        "subject": "A Message From a Constituent",
        "issue": "The issue that I am inquiring about is:",
        "concern": "My concerns regarding this issue are:",
        "idea": "And my ideas to address this issue are:"
    },
    "es": {
        "topic": "Por favor, escriba el tema. ",
        "about": "Por favor, escriba qué quiere decir acerca del tema.",
        "action": "Por favor, escriba su petición para el funcionario electo.",
        "new_window": 'Su mensaje ha sido escrito y se ha abierto una nueva ventana en la pantalla con su correo electrónico. Asegúrate de hacer clic en enviar ("send").',
        "subject": "Un mensaje de un ciudadano",
        "issue": "El tema sobre el que estoy preguntando es: ",
        "concern": "Mis inquietudes con respecto a este tema son:",
        "idea": "Y mis ideas para abordar este cuestión son:"
    }
}
let contact_content=contact_translations[ document.documentElement.lang ];

 function load() {
    let hrefWithoutHash = window.location.href.replace(window.location.hash, "");
    let email = hrefWithoutHash.split("email=")[1].split("?")[0].replace("_", "@");
    let name = hrefWithoutHash.split("name=")[1].split("?")[0].split("%20").join(" ");
    let office = hrefWithoutHash.split("office=")[1].split("?")[0].split("%20").join(" ");

    let displayOfficial = document.getElementById("display-official");
    displayOfficial.innerHTML = DOMPurify.sanitize(name + "<br>" + office);

    // In case the mailto button doesn't work,
    // display email for user to manually input
    let buttonAlt = document.getElementById("button-alt");
    buttonAlt.innerHTML += DOMPurify.sanitize(email);
}

/**
 * Execute mailto link based on user-submitted content.
 */
function writeMessage() {
    let email = window.location.href.split("email=")[1].split("?")[0].replace("_", "@");

    let topicField = document.getElementById("input-topic");
    let aboutField = document.getElementById("input-about");
    let actionField = document.getElementById("input-action");
    
    // Note: %0D%0A = newline character
    let address = email;
    let subject = "?subject=" + contact_content.subject;
    let body = [];
    if (topicField.value != "" && aboutField.value != "" && actionField.value != "") {
        body.push( "&body=" + contact_content.issue + "%0D%0A" + topicField.value + "%0D%0A%0D%0A" +
        contact_content.concern + "%0D%0A" + aboutField.value + "%0D%0A%0D%0A" +
        contact_content.idea + "%0D%0A" + actionField.value);
    }
    else
    if (topicField.value != "" && aboutField.value == "" && actionField.value != "") {
        body.push( "&body=" + contact_content.issue + "%0D%0A" + topicField.value + "%0D%0A%0D%0A" +
        contact_content.idea + "%0D%0A" + actionField.value + "%0D%0A%0D%0A");
    }
    else
    if (topicField.value != "" && aboutField.value != "" && actionField.value == "") {
        body.push( "&body=" + contact_content.issue + "%0D%0A" + topicField.value + "%0D%0A%0D%0A" +
        contact_content.concern + "%0D%0A" + aboutField.value + "%0D%0A%0D%0A");
    }
    else
    if (topicField.value == "" && aboutField.value != "" && actionField.value != "") {
        body.push( "&body=" + contact_content.concern + "%0D%0A" + aboutField.value + "%0D%0A%0D%0A" + 
        contact_content.idea + "%0D%0A" + actionField.value + "%0D%0A%0D%0A");
    }
    else
    if (topicField.value != "" && aboutField.value == "" && actionField.value == "") {
        body.push( "&body=" + contact_content.issue + "%0D%0A" + topicField.value + "%0D%0A%0D%0A");
    }
    else
    if (topicField.value == "" && aboutField.value != "" && actionField.value == "") {
        body.push( "&body=" + contact_content.concern + "%0D%0A" + aboutField.value + "%0D%0A%0D%0A");
    }
    else
    if (topicField.value == "" && aboutField.value == "" && actionField.value != "") {
        body.push( "&body=" + contact_content.idea + "%0D%0A" + actionField.value + "%0D%0A%0D%0A");
    }

    
    // Must replace spaces with %20
    let mailtoLink = 'mailto:' + (address + subject + body).replace(" ", "%20");
    window.location.href = DOMPurify.sanitize(mailtoLink);
}

load();
