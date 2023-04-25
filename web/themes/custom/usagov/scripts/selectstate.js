function stateSelect() {
    "use strict";
    let stateForm = document.getElementById("stateForm");
    let stateData = new FormData(stateForm);
    let stateValue = stateData.get('state-info');
    let stateName = stateValue.split("/")[2];
    dataLayer.push({
        'event': '50_state_submit',
        '50_state_url': stateValue,
        '50_state_name': stateName
    });
    window.location.assign(window.location.origin + stateValue);
}

