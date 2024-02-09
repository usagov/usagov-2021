const search_input = document.getElementById('search-field-en-small');
const dir_search_results = document.getElementById('fed-dir-search-results');

let search_term = '';

function fetchAgencies() {
  return fetch('sites/default/files/directory_report_federal')
    .then((response) => response.json());
};

function searchAgencies(allAgencies) {
  return allAgencies
  .filter(agency =>
      agency.agency_title.toLowerCase().includes(search_term.toLowerCase())
  );
}

function showAgencies(filteredAgencies) {
  console.log(filteredAgencies);
  dir_search_results.innerText = "";
  const usasearch_sayt = document.createElement('div');
  usasearch_sayt.classList.add('usasearch_sayt');
  const ul = document.createElement('ul');

  filteredAgencies
    .forEach(agency => {
        const li = document.createElement('li');
        const anchor = document.createElement('a');

        anchor.href = agency.agency_url;
        anchor.innerText = agency.agency_title;

        li.appendChild(anchor);
        ul.appendChild(li);
    });

  usasearch_sayt.appendChild(ul);
  dir_search_results.appendChild(usasearch_sayt);
};

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

if (search_input) {
  search_input.addEventListener('input', e => {
    search_term = e.target.value;
    getAgencies();
  });
}

