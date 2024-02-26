(() => {
  console.log("in state-drop-down");
  const stateInput = document.getElementById("state-info");
  let statesAndTerritoriesOptions;
  let statesAndTerritories = [];
  let stateSelect;
  let search_term = "";

  // create the state and territories array
  function getStatesAndTerritories() {
    stateSelect = document.getElementById("stateselect");
    statesAndTerritoriesOptions = stateSelect.getElementsByTagName("option");
    statesAndTerritories = [];
    for (let i = 0; i < statesAndTerritoriesOptions.length; i++) {
      let key = statesAndTerritoriesOptions[i].attributes.key.value;
      let name = statesAndTerritoriesOptions[i].innerText;
      statesAndTerritories.push({"name": name, "key": key});
    }
  }

  // filter the state and territories array
  function filterStatesAndTerritories(statesAndTerritories) {
    return statesAndTerritories.filter(
      (stateOrTerritory) =>
        stateOrTerritory.name
          .toLowerCase()
          .includes(search_term.toLowerCase()) ||
        stateOrTerritory.key.toLowerCase().includes(search_term.toLowerCase())
    );
  }

  // show the state list
  function displayFilteredStatesAndTerritories() {

  }

  // get the input of the combo box
  if (stateInput) {
    stateInput.addEventListener("input", (e) => {
      search_term = e.target.value;
      if (search_term != "") {
        getStatesAndTerritories();
        let filteredStatesAndTerritories = filterStatesAndTerritories(
          statesAndTerritories
        );
      }
    });
  }
})();
