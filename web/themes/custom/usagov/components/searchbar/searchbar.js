/* For federal agencies search bar.

This allows a drop down to show of suggested agencies as users type. It checks against agency names, synonyms and acronyms from the Federal Directory Record Content Type.

Relies on the view federal_directory_export specifically the json export displays: SAYT Es and SAYT En.

Examples:
~ If a user enters "fda" the drop down should show "food and drug administration".
~ If a user enters "commerce" the drop down should show "commerce department, department of commerce and U.S Department of commerce" (all of which go to the same url).
~ If a user enters "homeland security" the drop down should show "Department of Homeland Security, Homeland Security Department, U.S. Department of Homeland Security" (all of which go to the same url).
*/


var search_input = document.getElementById("search-field-en-small");
const dir_search_results = document.getElementById("fed-dir-search-results");
let lang = document.documentElement.lang;
let search_term = "";
var allAgencies;

function fetchAgencies() {
  if (lang == "es") {
    return fetch("/sites/default/files/directory_report_federal_es").then(
      (response) => response.json()
    );
  }
 else {
    return fetch("/sites/default/files/directory_report_federal").then(
      (response) => response.json()
    );
  }
}


function searchAgencies(allAgencies) {
  var returnAgencies=[];
  var lowerCaseSearchTerm = search_term.toLowerCase();
  const firstWordExp = new RegExp("^"+lowerCaseSearchTerm+".*");
  const begWordExp = new RegExp(".* "+lowerCaseSearchTerm);
  const abbrExp = new RegExp(lowerCaseSearchTerm);

  allAgencies.forEach((agency) => {
    var lowerCaseAgency = agency.agency_title.toLowerCase();

    if (lowerCaseAgency.match(firstWordExp) || lowerCaseAgency.match(begWordExp)) {
      returnAgencies.push(agency);
    }
 else if (agency.agency_acronym.toLowerCase().match(abbrExp)) {
      returnAgencies.push(agency);
    };
  });

  return returnAgencies;
}

function showAgencies(filteredAgencies) {
  dir_search_results.innerText = "";
  const usasearch_sayt = document.createElement("div");
  usasearch_sayt.classList.add("usasearch_sayt");
  usasearch_sayt.setAttribute("id", "usasearch_sayt");
  const ul = document.createElement("ul");

  filteredAgencies.forEach((agency, index) => {
    const resultBox = document.createElement("li");
    resultBox.classList.add("resultBox");
    resultBox.setAttribute("id", `resultBox${index}`);
    resultBox.setAttribute("role", "option");

    const anchor = document.createElement("a");
    anchor.onclick = function () {
      search_input.value = agency.agency_title;
    };
    const type = agency.agency_type;
    // need to get either the agency or synonym url. They will have the same value but are stored in 2 seperate fields
    if (type == "Federal Directory Record") {
      anchor.href = agency.agency_url;
    }
 else {
      anchor.href = agency.synonym_url;
    }

    if (agency.agency_acronym) {
      anchor.innerText = `${agency.agency_title} (${agency.agency_acronym})`;
      anchor.acronym = agency.agency_acronym;
    }
else {
      anchor.innerText = agency.agency_title;
    }


    resultBox.appendChild(anchor);
    ul.appendChild(resultBox);
  });

  usasearch_sayt.appendChild(ul);
  dir_search_results.appendChild(usasearch_sayt);
}

async function getAgencies() {
  try {
    if (!allAgencies) {
      allAgencies = await fetchAgencies();
    }
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
