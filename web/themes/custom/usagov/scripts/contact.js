/**
 * Display name and office for recipient of message, and also
 * add their email to backup message at the bottom of the page.
 */
 function load() {
    let email = window.location.href.split("email=")[1].split("?")[0].replace("_", "@");
    let name = window.location.href.split("name=")[1].split("?")[0].split("%20").join(" ");
    let office = window.location.href.split("office=")[1].split("?")[0].split("%20").join(" ");

    let displayOfficial = document.getElementById("display-official");
    displayOfficial.innerHTML = name + "<br>" + office;

    // In case the mailto button doesn't work,
    // display email for user to manually input
    let buttonAlt = document.getElementById("button-alt");
    buttonAlt.innerHTML += email;
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
    let address = "mailto:" + email;
    let subject = "?subject=" + "A Message From a Constituent";
    let body = "&body=" + "The issue that I am inquiring about is:%0D%0A" + topicField.value + "%0D%0A%0D%0A" +
        "My concerns regarding this issue are:%0D%0A" + aboutField.value + "%0D%0A%0D%0A" +
        "And my ideas to address this issue are:%0D%0A" + actionField.value;

    // Must replace spaces with %20
    let mailtoLink = (address + subject + body).replace(" ", "%20");
    window.location.href = mailtoLink;

    alert("Your message has been written, and a new window on the screen has opened with your email. Make sure to click send!");
}

load();