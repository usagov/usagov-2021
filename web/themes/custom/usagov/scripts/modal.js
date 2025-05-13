/**
 * This script displays a modal upon page load if the modal is present on the page and
 * the URL includes the modal's name as the 'modal' query parameter.
 *
 * The toggleModal, openModal, and closeModal functions can be used to control modals
 * from other scripts. These functions take a modal ID argument which is a numerical ID
 * rather than the modal's name, so we rely on the modalID function to get the ID for a
 * given modal name.
 */
let modalName;

document.addEventListener('DOMContentLoaded', function() {
    "use strict";

    document.querySelectorAll('.usa-modal').forEach((modal) => {
        // Modals start hidden to prevent a flash of content on page load.
        // This unhides the modals after USWDS script has moved them into a hidden container.
        modal.hidden = false;
    });

    modalName = removeUrlParameter('modal');
    if (modalName === null) return;
    modalName = modalName.replace(/[^a-zA-Z0-9_-]/g, '');
    openModal(modalID(modalName));
});

// Takes a URL query parameter name. Removes that parameter from the URL and browser history. Returns the parameter's value.
function removeUrlParameter(parameterName) {
    "use strict";

    const urlParams = new URLSearchParams(window.location.search);
    const parameterValue = urlParams.get(parameterName);
    if (parameterValue === null) return null;
    urlParams.delete(parameterName);
    let newURL = window.location.pathname;
    if (urlParams.size) {
        newURL = newURL + '?' + urlParams.toString();
    }
    history.replaceState(history.state, null, newURL);

    return parameterValue;
}

// Takes a modal name and returns its numerical ID
function modalID(modalName) {
    "use strict";

    if (modalName === null) return null;
    let map = document.querySelector('[data-modal-name="'+ modalName +'"]');
    if (map === null) return null;
    return map.getAttribute('data-modal-id');
}

function openModal(modalID) {
    "use strict";

    if (modalID === null) return;
    if (!modalIsOpen(modalID)) {
        dataLayer.push({'usa_modal_status': 'has_opened'});
        toggleModal(modalID);

        // Set focus to the first focusable element for accessibility
        document.querySelector('#paragraph--id--'+ modalID).querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').focus();
    }
}

function closeModal(modalID) {
    "use strict";

    if (modalID === null) return;
    if (modalIsOpen(modalID)) {
        toggleModal(modalID);
    }
}

function modalIsOpen(modalID) {
    "use strict";

    let modalElement = document.querySelector('#paragraph--id--'+ modalID);
    if (modalElement === null) return null;
    return modalElement.classList.contains('is-visible');
}

function toggleModal(modalID) {
    "use strict";

    // Click the modal button to trigger USWDS modal script
    document.querySelector('[href="#paragraph--id--'+ modalID +'"]').click();
}