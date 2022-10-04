function stateSelect() {
  let e = document.getElementById("state-info");
  let value = e.value;
  let enEspanol = window.location.pathname.substr(0,4) == '/es/';
  let statePath = enEspanol ? '/es/estados/' : '/states/';
  window.location.assign(
    window.location.origin + statePath + value.replace(/\s/g, "-").toLowerCase()
  );
}

