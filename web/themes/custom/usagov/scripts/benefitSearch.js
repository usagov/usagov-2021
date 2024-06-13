/**
 * Component to allow users to search benefits content and display
 * the paginated results
 *
 * @param {string} benefitsPath
 * @param {string} lifeEventsPath
 * @param {string} assetBase
 * @param {array} labels
 * @param {Element} form
 * @param {Element} resultsContainer
 * @param {int} perPage
 * @constructor
 */
function BenefitSearch(benefitsPath, lifeEventsPath, assetBase, labels, form, resultsContainer, perPage) {
  "use strict";

  this.benefitsSrc = benefitsPath;
  this.life = lifeEventsPath;
  this.assetBase = assetBase;
  this.labels = labels;
  this.resultsContainer = resultsContainer;
  this.form = form;
  this.perPage = perPage;
  this.activePage = 1;
  this.terms = [];
  this.boxes = this.form.querySelectorAll('input[type="checkbox"]:not([value="all"])');
  this.toggleAll = this.form.querySelector('input[type="checkbox"][value="all"]');

  // save a reference to our instance
  let myself = this;
  /**
   * Removes all error messages added to form
   */
  this.clearErrors = function() {
    for (const err of myself.form.querySelectorAll('.usa-alert--error')) {
      err.remove();
    }

    myself.form.querySelector('fieldset')
      .classList.remove('benefits-category-error');
  };
  /**
   * @returns {Promise<any>}
   */
  this.fetchBenefits = async function() {
    const response = await fetch(myself.benefitsSrc);
    if (!response.ok) {
      throw new Error('Error fetching benefits ' + response.status);
    }
    return await response.json();
  };
  /**
   * @returns {Promise<any>}
   */
  this.fetchLifeEvents = async function() {
    const response = await fetch(myself.life);
    if (!response.ok) {
      throw new Error('Error fetching benefits ' + response.status);
    }
    return await response.json();
  };
  /**
   * @returns {array}
   */
  this.findMatches = function() {
    let matches = myself.benefits.filter((item) => {
      let numMatches = item.field_benefits_category.filter((value) => myself.terms.includes(value));
      return numMatches.length > 0;
    });
    // score each match
    matches = matches.map(function(item) {
      let numMatches = item.field_benefits_category.filter((value) => myself.terms.includes(value));
      let base = parseInt(item.field_benefit_search_weight);
      let score = isNaN(base) ? 0 : base;
      item.rank = numMatches.length * 100 + score;
      return item;
    });
    matches = matches.sort(function(a, b) {
      // we want higher scores to come earlier so the return
      // values are -1 for items we want to display earlier
      // and +1 for later
      if (a.rank > b.rank) {
        return -1;
      }
      if (a.rank < b.rank) {
        return +1;
      }
      return 0;
    });
    // prepend any life events that match
    let events = myself.lifeEvents.filter((event) => {
      return myself.terms.includes(event.tid);
    });
    for (const e of events) {
      matches.unshift(e);
    }

    return matches;
  };
  /**
   * Clears the results container
   */
  this.handleClear = function() {
    myself.clearErrors();
    myself.resultsContainer.innerHTML = '';
  };
  /**
   * @param {int} page
   */
  this.handlePagerClick = function(page) {
    myself.setActivePage(page);
    myself.updateHistory();
  };
  /**
   * Initialize state on load and update when the URL's query string changes
   */
  this.parseUrlState = function() {
    const url = new URL(window.location.href);
    let page = url.searchParams.get('pg');
    let terms = url.searchParams.get('t');

    if (page && /^\d+$/.test(page)) {
      // ensure we set a number
      myself.setActivePage(parseInt(page));
    }
    else {
      myself.setActivePage(1);
    }

    if (terms) {
      terms = terms.split('-');
      myself.setTerms(terms);
    }
  };
  /**
   * Check form input and show the matching benefits
   */
  this.handleSubmit = function() {
    //  grab term ids from checked filters
    let checked = myself.form.querySelectorAll('input[type="checkbox"]:checked');
    if (checked.length === 0) {
      myself.showError();
      return;
    }
    // prepare to show results
    myself.handleClear();
    // keep the selected terms
    myself.terms = Array.from(checked).map((elt) => {
      return elt.value;
    });
    // display matching pages
    myself.showResults();
    // update browser history so that bookmarks work
    myself.updateHistory();
  };
  /**
   * Update UI when a term checkbox is clicked.
   */
  this.handleToggleCheck = function() {
    // we're resetting the search results here, so we should
    // also tell it to render page 1 of new search results
    myself.handleClear();
    myself.setActivePage(1);
  };
  /**
   * @param {Event} ev
   */
  this.handleToggleAll = function(ev) {
    myself.clearErrors();
    let newState = ev.target.checked;
    for (const box of myself.boxes) {
      box.checked = newState;
    }
  };
  /**
   * @param {Element} page
   */
  this.hidePage = function(page) {
    page.classList.remove('page-active');
    page.classList.add('display-none');
  };
  /**
   * Group results into pages of desired length and prepare output
   * @param matches
   * @returns {{totalItems: *, last: number, matches: *, first: number}[]}
   */
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
  /**
   * Render a single piece of matching content
   * @param benefit
   * @returns {HTMLTemplateElement}
   */
  this.renderMatch = function(benefit) {
    let elt = document.createElement('template');
    let description = '';

    if (benefit.field_page_intro) {
      description = benefit.field_page_intro;
    }
    else if (benefit.field_short_description) {
      description = benefit.field_short_description;
    }

    switch (benefit.type) {
      case 'Life Event':
        elt.innerHTML += `<div class="grid-row benefits-result">
<div class="desktop:grid-col-8 benefits-result-text"><h3>${benefit.title}</h3><p>${description}</p></div>
<div class="desktop:grid-col-4 benefits-result-categories"><h3>${myself.labels.appliedCategories}</h3>
  <span>${myself.labels.benefitFinderCategory}</span><span>${myself.labels.lifeEventsCategory}</span><span>${benefit.name}</div>
</div>`;
        break;

      case 'Basic Page':
      default:
        elt.innerHTML += `<div class="grid-row benefits-result">
<div class="desktop:grid-col-8 benefits-result-text"><h3>${benefit.title}</h3><p>${description}</p></div>
<div class="desktop:grid-col-4 benefits-result-categories"><h3>${myself.labels.appliedCategories}</h3>
${benefit.term_node_tid}
</div>
</div>`;
    }

    return elt;
  };
  /**
   * Generate HTML for a single page
   * @param page
   * @param index
   * @returns {HTMLDivElement}
   */
  this.renderPage = function(page, index) {
    let elt = document.createElement('div');
    elt.className = index + 1 === myself.activePage ? 'page page-active' : 'page display-none';
    elt.setAttribute('data-page', index + 1);
    // heading label
    if (page.first !== page.last) {
      elt.innerHTML += `<h2>Showing ${page.first}&ndash;${page.last} of ${page.totalItems}</h2>`;
    }
    else {
      elt.innerHTML += `<h2>Showing ${page.first} of ${page.totalItems}</h2>`;
    }
    // prepare pages
    for (const benefit of page.matches) {
      elt.innerHTML += myself.renderMatch(benefit).innerHTML;
    }
    return elt;
  };
  /**
   * Make the requested page active
   * @param {int} num
   */
  this.setActivePage = function(num) {
    if (num === this.activePage) {
      return;
    }
    // update the active page and show it
    this.activePage = num;
    const pages = this.resultsContainer.querySelectorAll('.page');
    if (pages.length > 0) {
      for (const page of pages) {
        if (parseInt(page.getAttribute('data-page')) === this.activePage) {
          this.showPage(page);
        }
        else {
          this.hidePage(page);
        }
      }
      myself.resultsContainer.scrollIntoView({"behavior": 'smooth'});
    }
  };
  /**
   * Update the terms for searching
   * @param {string[]} terms
   */
  this.setTerms = function(terms) {
    // keep only numbers
    terms = terms.filter(
      (item) => { return /^\d+$/.test(item); }
    );
    // sort them into a consistent state regardless of how we get them
    terms.sort();
    if (terms === this.terms) {
      // do nothing, no change
      return;
    }

    // re-run the search
    this.terms = terms;
    // check the matching boxes
    for (let box of myself.boxes) {
      box.checked = this.terms.includes(box.getAttribute('value'));
    }
    this.handleSubmit();
  };
  this.showError = function() {
    let elt = document.createElement('template');
    elt.innerHTML = `<div class="usa-alert usa-alert--slim usa-alert--error margin-bottom-4" aria-live=assertive>
        <div class="usa_alert__body">
           <h4 class="usa-alert__heading padding-left-6">${myself.labels.emptyCategoryError}</h4>
        </div>
    </div>`;
    myself.form.prepend(elt.content);

    const fieldset = myself.form.querySelector('fieldset');
    fieldset.classList.add('benefits-category-error');
  };
  /**
   * @param {Element} page
   */
  this.showPage = function (page) {
    page.classList.add('page-active');
    page.classList.remove('display-none');
  };
  /**
   * Display content matching selected categories
   */
  this.showResults = function() {
    myself.resultsContainer.innerHTML = '';
    // keep the benefits that match
    let matches = this.findMatches();

    const pages = myself.preparePages(matches);
    for (const page of pages.map(myself.renderPage)) {
      myself.resultsContainer.innerHTML += page.outerHTML;
    }

    if (myself.activePage > pages.length) {
      myself.setActivePage(pages.length);
    }
    else if (myself.activePage < 1) {
      myself.setActivePage(1);
    }
    myself.resultsContainer.scrollIntoView({"behavior": 'smooth'});
    myself.showPager(pages.length);
  };
  /**
   * Display the pager widget below the results
   * @param maxPages
   */
 this.showPager = function(maxPages) {
   const pager = new Pagination(
     maxPages,
     myself.activePage,
     myself.assetBase,
     myself.labels,
     myself.handlePagerClick
   );
   let existing = resultsContainer.querySelector('nav.usa-pagination');
   if (existing) {
     existing.remove();
     resultsContainer.append(pager.render());
     return;
   }

   resultsContainer.append(pager.render());
 };
  /**
   * Saves the selected terms and current page to the browser's history via query string
   */
  this.updateHistory = function() {
    const terms = myself.terms.filter((num) => {return false === isNaN(num);});
    // update query string
    const url = new URL(window.location.href);
    url.searchParams.set('t', terms.join('-'));
    url.searchParams.set('pg', myself.activePage);
    // update browser
    window.history.pushState(null, '', url.toString());
  };
  /**
   * Loads the data file and adds event listeners
   * @returns {Promise<void>}
   */
  this.init = async function() {
    // load data and initial URL state
    this.benefits = await myself.fetchBenefits();
    this.lifeEvents = await myself.fetchLifeEvents();

    this.parseUrlState();

    // checkbox events
    this.toggleAll.addEventListener('click', myself.handleToggleAll);
    for (const box of myself.boxes) {
      box.addEventListener('click', myself.handleToggleCheck);
    }
    // form events
    myself.form.addEventListener('submit', myself.handleSubmit);
    myself.form.addEventListener('reset', myself.handleClear);
    // history events
    window.addEventListener('popstate', myself.parseUrlState);
  };
}

jQuery(document).ready(async function () {
  "use strict";
  let docLang = [document.documentElement.lang];
  let benefitsPath, lifeEventsPath, labels;
  // using relative URL so that this works on static pages
  // Setup language specific inputs
  if (docLang[0] === 'en') {
    benefitsPath = "../benefits-search/en.json";
    lifeEventsPath = "../benefits-search/life-en.json";
    labels = {
      'page': "Page",
      'next': "Next",
      'nextAria': "Next Page",
      'previous': "Previous",
      'previousAria': "Previous page",
      'navAria': "Pagination",
      'lastPageAria': 'Last page',
      'emptyCategoryError': 'Error: Please select at least one or more categories',
      'appliedCategories': 'Applied Categories',
      'lifeEventsCategory': 'Life Events',
      'benefitFinderCategory': 'Benefit Finder Tool'
    };
  }
  else if (docLang[0] === 'es') {
     benefitsPath = "../../benefits-search/es.json";
     lifeEventsPath = "../../benefits-search/life-es.json";
     labels = {
      'page': "Página",
      'next': "Siguiente",
      'nextAria': "Página siguiente",
      'previous': "Anterior",
      'previousAria': "Página anterior",
      'navAria': "Paginación",
      'lastPageAria': 'Ultima página',
      'emptyCategoryError': 'Error: Por favor seleccione una o más categorías.',
      'appliedCategories': 'Categorías',
      'lifeEventsCategory': 'Eventos de la vida',
      'benefitFinderCategory': 'Buscador de beneficios'
    };
  }
  // creat and initialize the search tool
  const ben = new BenefitSearch(
    benefitsPath,
    lifeEventsPath,
    '/themes/custom/usagov',
    labels,
    document.querySelector('#benefitSearch'),
    document.querySelector('#matchingBenefits'),
    5
  );
  await ben.init();
});
