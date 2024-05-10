/**
 * Component to allow users to search benefits content and display
 * the paginated results
 *
 * @param {string} src
 * @param {Element} form
 * @param {Element} resultsContainer
 * @param {int} perPage
 * @constructor
 */
function BenefitSearch(src, form, resultsContainer, perPage) {
  "use strict";

  this.src = src;
  this.resultsContainer = resultsContainer;
  this.form = form;
  this.perPage = perPage;
  this.activePage = 1;

  this.boxes = this.form.querySelectorAll('#benefitSearch input[type="checkbox"]');

  let myself = this;

  /**
   * @returns {Promise<any>}
   */
  this.fetch = async function() {
    const response = await fetch(myself.src);
    if (!response.ok) {
      throw new Error('Error fetching benefits ' + response.status);
    }
    return await response.json();
  };

  this.handleClear = function() {
    myself.resultsContainer.innerHTML = '';
  };

  this.handlePagerClick = function(page) {
    // hide visible page
    const toHide = myself.resultsContainer
      .querySelector('.page-active');
    if (toHide) {
      toHide.classList.remove('page-active');
      toHide.classList.add('display-none');
    }
    // show requested page
    const toShow = myself.resultsContainer
      .querySelector(`div.page[data-page="${page}"]`);
    if (toShow) {
      toShow.classList.add('page-active');
      toShow.classList.remove('display-none');
    }
  };

  this.handleSubmit = function() {
    //  grab term ids from checked filters
    let checked = myself.form.querySelectorAll('#benefitSearch input[type="checkbox"]:checked');

    if (checked.length === 0) {
      myself.showError();
      return;
    }
    // prepare to show results
    for (const err of myself.form.querySelectorAll('.error')) {
      err.remove();
    }

    myself.resultsContainer.innerHTML = '';
    let terms = Array.from(checked).map((elt) => {
      return elt.value;
    });

    // keep the benefits that match
    let matches = myself.benefits.filter((item) => {
      let numMatches = item.field_category.filter((value) => terms.includes(value));
      return numMatches.length > 0;
    });

    // display matching pages
    myself.showResults(matches);
  };

  this.preparePages = function(matches) {
    const total = matches.length;

    // chunk the matches into pages
    return Array.from(
      {"length": Math.ceil(total / myself.perPage)},
      function(v, i) {
        let page = {
          "totalItems": total,
          "first": i * myself.perPage + 1,
          "last": i * myself.perPage + myself.perPage,
          "matches": matches.slice(i * myself.perPage, i * myself.perPage + myself.perPage),
        };

        if (page.last >= total) {
          page.last = total;
        }
        return page;
      });
  };

  this.renderMatch = function(benefit) {
    let elt = document.createElement('template');
    let descr = '';

    if (benefit.field_page_intro) {
      descr = benefit.field_page_intro;
    }
    else if (benefit.field_short_description) {
      descr = benefit.field_short_description;
    }

    elt.innerHTML += `<div><h3>${benefit.title}</h3><p>${descr}</p></div>`;
    return elt;
  };

  this.renderPage = function(page, index) {
    let elt = document.createElement('div');

    elt.className = index + 1 === myself.activePage ? 'page page-active' : 'page display-none';
    elt.setAttribute('data-page', index + 1);

    if (page.first !== page.last) {
      elt.innerHTML += `<h3>Showing ${page.first}&ndash;${page.last} of ${page.totalItems}</h3>`;
    }
    else {
      elt.innerHTML += `<h3>Showing ${page.first} of ${page.totalItems}</h3>`;
    }

    for (const benefit of page.matches) {
      elt.innerHTML += myself.renderMatch(benefit).innerHTML;
    }

    return elt;
  };
  /**
   * @param matches
   */
  this.showResults = function(matches) {
    const pages = myself.preparePages(matches);
    for (const page of pages.map(myself.renderPage)) {
      myself.resultsContainer.innerHTML += page.outerHTML;
    }

    myself.resultsContainer.scrollIntoView({"behavior": 'smooth'});

    // show/update pager
    const labels = {
      'page': "Page",
      'next': "Next",
      'nextAria': "Next Page",
      'previous': "Previous",
      'previousAria': "Previous page",
    };
    const pager = new Pagination(pages.length, 1, labels, myself.handlePagerClick);
    resultsContainer.append(pager.render());
  };

  this.showError = function() {
    let elt = document.createElement('template');
    elt.innerHTML = '<div class="usa-alert--error">Select one or more categories</div>';

    myself.form.prepend(elt.content);
  };

  /**
   * @param {Event} ev
   */
  this.toggleCheckboxes = function(ev) {
    let newState = ev.target.checked;
    for (const box of myself.boxes) {
      box.checked = newState;
    }
  };

  // init stuff
  this.init = async function() {
    this.benefits = await myself.fetch();

    // select/hide all
    const toggleAll = myself.form.querySelector('input[type="checkbox"][value="all"]');
    toggleAll.addEventListener('click', myself.toggleCheckboxes);

    // form events
    myself.form.addEventListener('submit', myself.handleSubmit);
    myself.form.addEventListener('reset', myself.handleClear);
  };
}

jQuery(document).ready(async function () {
  "use strict";

  // 1) load search json (todo: toggle languages)
  const src = "/benefits-search.json";

  const ben = new BenefitSearch(
    src,
    document.querySelector('#benefitSearch'),
    document.querySelector('#matchingBenefits'),
    5
  );
  await ben.init();
});
