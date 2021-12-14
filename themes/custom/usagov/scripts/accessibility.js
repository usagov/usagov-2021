/**
 * Improve the accessibility of pages with input fields.
 */
window.addEventListener("load", function() {
    // Customize input validation error messages by
    // specifying the name of each input field. Only
    // applies to elements specified in the list below.
    let elementTypes = ["input", "textarea"];
    for (let i = 0; i < elementTypes.length; i++) {
        elements = document.getElementsByTagName(elementTypes[i]);
        for (let j = 0; j < elements.length; j++) {
            // Note: all input fields should have an ID starting with "input-"
            let message = "Please fill out the " + elements[j].id.replace("input-", "") + " field.";
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
                if (clearButtons[i].parentElement.previousElementSibling.id == "input-state") {
                    clearButtons[i].setAttribute("aria-label", "Clear the contents of the state field.");
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
