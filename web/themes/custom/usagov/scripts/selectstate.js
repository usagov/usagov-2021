function stateSelect() {
    let stateForm = document.getElementById("stateForm");
    let stateData = new FormData(stateForm);
    let stateValue = stateData.get('state-info');
    let enEspanol = window.location.pathname.substr(0,4) == '/es/';
    let statePath = enEspanol ? '/es/estados/' : '/states/';
    dataLayer.push({
        'event': '50_state_submit',
        '50_state_url': statePath + stateValue,
        '50_state_name': stateValue
    });
    window.location.assign(window.location.origin + statePath + stateValue);
}

