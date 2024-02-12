const search_input = document.getElementById("search-field-en-small");
const dir_search_results = document.getElementById("fed-dir-search-results");
let lang = document.documentElement.lang;
let search_term = "";

function fetchAgencies() {
  if (lang == "es") {
    return fetch("sites/default/files/directory_report_federal_es").then(
      (response) => response.json()
    );
  }
  else {
    return fetch("sites/default/files/directory_report_federal").then(
      (response) => response.json()
    );
  }
}

function searchAgencies(allAgencies) {
  return allAgencies.filter((agency) =>
    agency.agency_title.toLowerCase().includes(search_term.toLowerCase())
  );
}

function showAgencies(filteredAgencies) {
  dir_search_results.innerText = "";
  const usasearch_sayt = document.createElement("div");
  usasearch_sayt.classList.add("usasearch_sayt");
  usasearch_sayt.setAttribute("id", "usasearch_sayt");
  const ul = document.createElement("ul");

  filteredAgencies.forEach((agency) => {
    const resultBox = document.createElement("li");
    resultBox.classList.add("resultBox");
    resultBox.setAttribute("id", "resultBox");
    resultBox.setAttribute("role", "option");

    const anchor = document.createElement("a");

    anchor.href = agency.agency_url;
    anchor.innerText = agency.agency_title;

    resultBox.appendChild(anchor);
    ul.appendChild(resultBox);
  });

  usasearch_sayt.appendChild(ul);
  dir_search_results.appendChild(usasearch_sayt);
}

async function getAgencies() {
  try {
    let allAgencies = await fetchAgencies();
    let filteredAgencies = await searchAgencies(allAgencies);
    await showAgencies(filteredAgencies);
  }
 catch (status) {
    // no suggestions found
  }
}

function listen_for_clear_results() {
  const fed_dir_results = document.getElementById("usasearch_sayt");
  if (fed_dir_results) {
    window.addEventListener("click", function (e) {
      // Clicked outside box need to clear the reults box
      if (!fed_dir_results.contains(e.target)) {
        dir_search_results.innerText = "";
      }
    });
  }
}

if (search_input) {
  search_input.addEventListener("input", (e) => {
    search_term = e.target.value;
    if (search_term != "") {
      getAgencies();
    }
 else {
      dir_search_results.innerText = "";
    }
    listen_for_clear_results();
  });
}
