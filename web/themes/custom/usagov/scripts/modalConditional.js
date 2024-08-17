/**
 * Proof-of-concept: do an action if a specific query parameter was supplied on the URL,
 * then update the page history so it won't happen again if the user browsers around
 * and returns.
 *
 * NOTE: I'm using a mix of jQuery helpers and vanilla js like "window.addEventListener"
 * because my javascript is very rusty, not because it's the right thing to do. Adjust
 * as desired. We will also change the query string we're looking for (currently "showit=yes").
 */
(function ($) {
    "use strict";
    function maybeDisplayModal() {
        let params = new URLSearchParams(document.location.search);
        if (params.get("showit") == "yes") {
            displayModal();
            params.delete("showit");
            updateParamsInHistory(params);
        }
    }
    function displayModal() {
        $("#modal-conditional-poc").show();
    }
    function dismissModal() {
        $("#modal-conditional-poc").hide();
    }
    function updateParamsInHistory(newParams) {
        let newURL = window.location.pathname;
        if (newParams.size) {
            newURL = newURL + '?' + newParams.toString();
        }
        history.replaceState(history.state, null, newURL);
    }
    $(document).ready(maybeDisplayModal());
    window.addEventListener("beforeunload", dismissModal);
})(jQuery);
