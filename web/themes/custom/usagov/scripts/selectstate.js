function stateSelect() {
  let e = document.getElementById("state-info");
  let value = e.value;
  window.location.assign(
    window.location.href + "/" + value.replace(/\s/g, "-").toLowerCase()
  );
}
