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
    let email = decodeURIComponent(hrefWithoutHash.split("email=")[1].split("?")[0]);
    let name = decodeURIComponent(hrefWithoutHash.split("name=")[1].split("?")[0].split("%20").join(" "));
    let office = decodeURIComponent(hrefWithoutHash.split("office=")[1].split("?")[0].split("%20").join(" "));

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
    let hrefWithoutHash = window.location.href.replace(window.location.hash, "");    
    let email = decodeURIComponent(hrefWithoutHash.split("email=")[1].split("?")[0]);

    let topicField = document.getElementById("input-topic");
    let aboutField = document.getElementById("input-about");
    let actionField = document.getElementById("input-action");

    // Minimal check of email address; it must not start with a protocol://, etc.
    // This is solely to eliminate an "open redirect" vulnerability. It would be
    // better to look up the official's address, find the matching email address, and
    // use the email string returned from the Civic API.
    email = email.trim();
    let protocol_regex = /^\w+:/;
    let protocol_match = email.match(protocol_regex);
    let parts = email.split('@');
    let parts_bad = false;
    // There should be at least 2 parts:
    if (parts.length < 2) {
      parts_bad = true;
    }
    if (parts.length > 2) {
        // Multiple @s are bad unless the first part of the address is in quotation marks. 
        let lastpart = parts[parts.length - 1];
        let prevpart = parts[parts.length - 2];
        let firstpart = parts[0];
        if (firstpart.substring(0,1) != '"') {
          parts_bad = true;
        }
        else if (prevpart.substring(prevpart.length - 1) != '"') {
          parts_bad = true;
        }
        // The part after the @ must not contain quotation marks. 
        if (! parts_bad) {
          if (lastpart.indexOf('"') != -1) {
              parts_bad = true;
          }
        }
    }
    
    if (! parts_bad && (protocol_match == undefined)) {
      let address = parts.join('@');
      let subject = "?subject=" + encodeURIComponent(contact_content.subject);
      let body = [];
	
      if (topicField.value != "") {
	  body.push(encodeURIComponent(contact_content.issue + "\n"));
	  body.push(encodeURIComponent(topicField.value + "\n\n"));
      }
      if (aboutField.value != "") {
	  body.push(encodeURIComponent(contact_content.concern + "\n"));
	  body.push(encodeURIComponent(aboutField.value + "\n\n"));
      }
      if (actionField.value != "") {
	  body.push(encodeURIComponent(contact_content.idea + "\n"));
	  body.push(encodeURIComponent(actionField.value));
      }

      body_string = "&body=" + body.join('');

      let mailtoLink = 'mailto:' + (address + subject + body_string);
      //window.location.href = mailtoLink;
      window.location.assign(mailtoLink);
    }
}

load();
