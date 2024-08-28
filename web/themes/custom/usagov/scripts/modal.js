
function processModalUrlParameter() {
    "use strict";

    const urlParams = new URLSearchParams(window.location.search);
    const modalName = urlParams.get('modal');
    if (modalName === null) return;

    toggleModal(modalName);

    urlParams.delete("modal");
    let newURL = window.location.pathname;
    if (urlParams.size) {
        newURL = newURL + '?' + urlParams.toString();
    }
    history.replaceState(history.state, null, newURL);
}

/* open or close the modal programatically */
function toggleModal(modalName) {
    "use strict";

    let map = document.querySelector('[data-modal-name="'+ modalName +'"]');
    if (map === null) return;
    let modalID = map.getAttribute('data-modal-id');
    if (modalID === null) return;
    document.querySelector('[href="#paragraph--id--'+ modalID +'"]').click();
}

document.addEventListener('DOMContentLoaded', function() {
    "use strict";

    processModalUrlParameter();
});

