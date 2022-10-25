function stateSelect() {
    let stateForm = document.getElementById("stateForm");
    let stateData = new FormData(stateForm);
    let stateValue = stateData.get('state-info');
    let enEspanol = window.location.pathname.substr(0,4) == '/es/';
    let statePath = enEspanol ? '/es/estados/' : '/states/';
    window.location.assign(window.location.origin + statePath + stateValue);
}

