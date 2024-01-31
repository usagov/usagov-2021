const search_input = document.getElementById('search-field-en-small');
const dir_search_results = document.getElementById('fed-dir-search-results');

let search_term = '';
let agencies;

const fetchAgencies = async () => {
  console.log('test fetch');
  agencies = [{
      "agency_name": "AbilityOne Commission",
      "agency_url": "http://localhost/agencies/u-s-abilityone-commission",
    }, {
      "agency_name": "Access Board",
      "agency_url": "http://localhost/agencies/u-s-access-board",
    }, {
      "agency_name": "Administration for Children and Families",
      "agency_url": "http://localhost/agencies/administration-for-children-and-families",
    }, {
      "agency_name": "Administration for Community Living",
      "agency_url": "http://localhost/agencies/administration-for-community-living",
    }];
};

const showAgencies = async () => {
  await fetchAgencies();
  dir_search_results.innerText = "";
  const usasearch_sayt = document.createElement('div');
  usasearch_sayt.classList.add('usasearch_sayt');
  const ul = document.createElement('ul');

  agencies
    .forEach(agency => {
        const li = document.createElement('li');

        const anchor = document.createElement('a');
        anchor.href = agency.agency_url;
        anchor.innerText = agency.agency_name;

        li.appendChild(anchor);

        ul.appendChild(li);
    });

  usasearch_sayt.appendChild(ul);
  dir_search_results.appendChild(usasearch_sayt);
  console.log(dir_search_results);
};

search_input.addEventListener('input', e => {
  console.log('test event listener');
  search_term = e.target.value;
  showAgencies();
});
