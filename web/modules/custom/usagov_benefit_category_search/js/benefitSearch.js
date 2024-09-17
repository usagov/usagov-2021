/**
 * @typedef TranslationLabels
 * @type Object
 * @property {string} page Label for numeric page
 * @property {string} next Label for next page link
 * @property {string} nextAria Aria property for next page link
 * @property {string} previous Label for previous page link
 * @property {string} previousAria Aria property for previous page link
 * @property {string} navAria Aria property for numeric page link
 * @property {string} lastPageAria Aria property for lat page link
 * @property {string} emptyCategoryError Error message when no category is selected
 * @property {string} appliedCategories Heading for applied categories column
 * @property {string} lifeEventsCategory Life events category name
 * @property {string} benefitFinderCategory Benefit finder category name
 * @property {string} showingResults Template for count and range of results shown
 * @property {string} selectionsCleared Template for count and range of results shown
 */
/**
 * Component to allow users to search benefits content and display
 * the paginated results
 *
 * @param {string} benefitsPath
 * @param {string} lifeEventsPath
 * @param {string} assetBase
 * @param {string} docLang
 * @param {TranslationLabels} labels
 * @param {Element} form
 * @param {Element} resultsContainer
 * @param {int} perPage
 * @constructor
 */
function BenefitSearch(benefitsPath, lifeEventsPath, assetBase, docLang, labels, form, resultsContainer, perPage) {
  "use strict";

  this.benefitsSrc = benefitsPath;
  this.life = lifeEventsPath;
  this.assetBase = assetBase;
  this.docLang = docLang;
  this.labels = labels;
  this.resultsContainer = resultsContainer;
  this.form = form;
  this.perPage = perPage;
  this.activePage = null;
  this.terms = [];
  this.boxes = this.form.querySelectorAll('input[type="checkbox"]:not([value="all"])');
  this.toggleAll = this.form.querySelector('input[type="checkbox"][value="all"]');

  // save a reference to our instance
  let myself = this;
  /**
   * Removes all error messages added to form
   */
  this.clearErrors = function() {
    myself.form.querySelector('.alert-container').innerHTML = '';
    myself.form.querySelector('div[role="group"]')
      .classList.remove('benefits-category-error');
  };
  /**
   * @typedef Benefit
   * @type Object
   * @property {int} nid
   * @property {string} field_benefit_search_weight
   * @property {int} weight
   * @property {int[]} field_benefits_category
   * @property {string} term_node_tid Term name markup
   * @property {string} title Title to display
   * @property {string} field_page_intro Page Intro
   * @property {string} field_short_description Short Description
   * @property {string} title Life event title
   * @property {string} type
   * @property {string} view_node
   */
  /**
   * @returns {Promise<Benefit[]>}
   */
  this.fetchBenefits = async function() {
    const response = await fetch(myself.benefitsSrc);
    if (!response.ok) {
      throw new Error('Error fetching benefits ' + response.status);
    }
    return await response.json();
  };
  /**
   * @typedef LifeEvent
   * @type Object
   * @property {int} nid
   * @property {int|int[]} tid
   * @property {string} name Term name
   * @property {string} field_b_search_title Title to display
   * @property {string} field_short_description Short Description
   * @property {string} title Life event title
   * @property {string} type
   * @property {string[]} terms
   */
  /**
   * @returns {Promise<Map<int, LifeEvent>>}
   */
  this.fetchLifeEvents = async function() {
    const response = await fetch(myself.life);
    if (!response.ok) {
      throw new Error('Error fetching benefits ' + response.status);
    }
    /**
     * @var {LifeEvent[]} raw
     */
    const raw = await response.json();
    // We need to consolidate life events that may reference more
    // than one category into a single entry per life event.
    /**
     * @var {Map<int, LifeEvent>} lifeEvents
     */
    let lifeEvents = new Map();
    for (const lifeEvent of raw) {
      if (lifeEvents.has(lifeEvent.nid)) {
        let existing = lifeEvents.get(lifeEvent.nid);
        existing.tid.push(lifeEvent.tid);
        existing.terms.push(lifeEvent.name);
        lifeEvents.set(lifeEvent.nid, existing);
      }
      else {
        lifeEvent.tid = [lifeEvent.tid];
        lifeEvent.terms = [lifeEvent.name];
        lifeEvents.set(lifeEvent.nid, lifeEvent);
      }
    }
    return lifeEvents;
  };
  /**
   * @returns {array}
   */
  this.findMatches = function() {
    let matches = myself.benefits.filter((item) => {
      let numMatches = item.field_benefits_category.filter((value) => myself.terms.includes(value));
      return numMatches.length > 0;
    });
    // score and sort remaining matches
    matches = matches.map(myself.getItemWeight);
    matches = matches.sort(myself.compareResults);

    if (!myself.areAllChecked()) {
      // prepend any life events that match
      for (const [, lifeEvent] of myself.lifeEvents) {
        if (myself.lifeEventHasTopic(lifeEvent.tid, myself.terms)) {
          matches.unshift(lifeEvent);
        }
      }
    }
    return matches;
  };
  /**
   *
   * @param {Benefit} item
   * @return {Benefit}
   */
  this.getItemWeight = function(item) {
    let base = parseInt(item.field_benefit_search_weight);
    item.weight = isNaN(base) ? 0 : base;
    return item;
  };
  /**
   * @param {Benefit} a
   * @param {Benefit} b
   * @return {number}
   */
  this.compareResults = function(a, b) {
    // we want higher scores to come earlier so the return
    // values are -1 for items we want to display earlier
    // and +1 for later
    if (a.weight > b.weight) {
      return -1;
    }
    if (a.weight < b.weight) {
      return +1;
    }
    return 0;
  };
  /**
   * @param {int[]} tids
   * @param {int[]} terms
   * @return boolean
   */
  this.lifeEventHasTopic = function(tids, terms) {
    for (const tid of tids) {
      if (terms.includes(tid)) {
        return true;
      }
    }
    return false;
  };
  /**
   * Clears the results container
   * @param {boolean} announceClear
   */
  this.handleClear = function(announceClear = false) {
    myself.clearErrors();

    if (announceClear)  {
      let alert = myself.form.querySelector('.alert-container');
      alert.innerHTML = `<div class="visuallyhidden">${myself.labels.selectionsCleared}</div>`;
    }

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
    myself.clearErrors();
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
    // send data to GTM for a success in applying selections
    if (dataLayer != null) {
      dataLayer.push({'event': 'category_apply_success'});
  }
  };
  /**
   * Update UI when a term checkbox is clicked.
   */
  this.handleToggleCheck = function() {
    // we're resetting the search results here, so we should
    // also tell it to render page 1 of new search results
    myself.handleClear();
    myself.setActivePage(1);
    myself.toggleAll.checked = myself.areAllChecked();
  };
  /**
   * Update UI when the select all is clicked
   * @param {MouseEvent} ev
   */
  this.handleToggleAll = function(ev) {
    myself.clearErrors();

    const announceClear = myself.areAllChecked();

    myself.handleClear(announceClear);
    myself.setActivePage(1);

    let newState = ev.target.checked;
    for (const box of myself.boxes) {
      box.checked = newState;
    }
  };
  /**
   * Test if all the category checkboxes are ticked
   * @return boolean
   */
  this.areAllChecked = function() {
    for (const box of myself.boxes) {
      if (box.checked === false) {
        return false;
      }
    }
    // if we get here, all must be checked
    return true;
  };
  /**
   * Test if any of the category checkboxes are ticked.
   * @return boolean
   */
  this.areAnyChecked = function() {
    for (const box of myself.boxes) {
      if (box.checked === true) {
        return true;
      }
    }
    // if we get here, none must be checked
    return false;
  };
  /**
   * Hide the indicated page
   * @param {Element} page
   */
  this.hidePage = function(page) {
    page.classList.remove('page-active');
    page.classList.add('display-none');
  };
  /**
   * Mark the last column items in multi-column CSS layout.
   */
  this.markCheckboxColumns = function() {
    let lastCheckbox = null, lastPos = null, lastListItem = null;
    for (const box of myself.boxes) {
      let pos = box.getBoundingClientRect();
      let listItem = box.parentElement.parentElement;
      // Is the current checkbox higher than the last one?
      if (listItem && listItem.classList.contains('end-column')) {
        listItem.classList.remove('end-column');
      }
      if (lastPos && pos.top < lastPos.top) {
        // mark the LI of the last element as end of column too
        lastListItem.classList.add('end-column');
      }
      lastCheckbox = box;
      lastPos = pos;
      lastListItem = listItem;
    }
    // mark the LI of the last element as end of column too
    lastListItem.classList.add('end-column');
  }
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
   * @param {Benefit} benefit
   * @returns {HTMLTemplateElement}
   */
  this.renderMatch = function(benefit) {
    let elt = document.createElement('template');
    let description = '';

    if (benefit.field_short_description) {
      description = benefit.field_short_description;
    }
    else if (benefit.field_page_intro) {
      description = benefit.field_page_intro;
    }

    switch (benefit.type) {
      case 'Life Event':
        let termMarkup = '';

        benefit.terms.forEach(term => termMarkup += `<li>${term}</li>`);

        elt.innerHTML += `<div class="grid-row benefits-result">
<div class="desktop:grid-col-8 benefits-result-text">
  <h3><a href="${benefit.view_node}" data-analytics="category-results-links" hreflang="${myself.docLang}">${benefit.field_b_search_title}</a></h3>
  <p>${description}</p></div>
<div class="desktop:grid-col-4 benefits-result-categories"><h4>${myself.labels.appliedCategories}</h4>
  <ul>
  <li>${myself.labels.benefitFinderCategory}</li><li>${myself.labels.lifeEventsCategory}</li>${termMarkup}</div>
  </ul>
</div>`;
        break;

      case 'Basic Page':
      default:
        elt.innerHTML += `<div class="grid-row benefits-result">
<div class="desktop:grid-col-8 benefits-result-text">
  <h3><a href="${benefit.view_node}" data-analytics="category-results-links" hreflang="${myself.docLang}">${benefit.title}</a></h3>
  <p>${description}</p></div>
<div class="desktop:grid-col-4 benefits-result-categories"><h4>${myself.labels.appliedCategories}</h4>
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
    let label = myself.labels.showingResults
      .replace('@first@', page.first)
      .replace('@last@', page.last)
      .replace('@totalItems@', page.totalItems);
    elt.innerHTML += `<h2 tabindex="-1">${label}</h2>`;
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
      myself.scrollAndFocusResults();
    }

  };
  /**
   * Bring the results into view and focus on the heading
   */
  this.scrollAndFocusResults = function() {
    myself.resultsContainer.scrollIntoView({"behavior": 'auto'});
    myself.resultsContainer.querySelector('.page-active > h2').focus();
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
    let alert = myself.form.querySelectorAll('.usa-alert');
    if (alert.length > 0) {
      // if we're showing an error already, don't add another
      return;
    }

    let elt = document.createElement('template');
    elt.innerHTML = `<div class="usa-alert usa-alert--slim usa-alert--error margin-bottom-4">
        <div class="usa_alert__body" data-analytics="errorMessage">
           <h3 tabindex="-1" class="usa-alert__heading padding-left-6">${myself.labels.emptyCategoryError}</h3>
        </div>
    </div>`;

    let container = myself.form.querySelector('.alert-container')
    container.prepend(elt.content);

    const fieldset = myself.form.querySelector('div[role="group"]');
    fieldset.classList.add('benefits-category-error');

    container.querySelector('.usa-alert__heading').focus();

    // sending data to GTM when the error message appears
    if (dataLayer != null && document.querySelector('[data-analytics="errorMessage"]')) {
      dataLayer.push({'event': 'category_apply_error'});
    }
  };
  /**
   * Show indicated page
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
    myself.scrollAndFocusResults();
    myself.showPager(pages.length);
  };
  /**
   * Display the pager widget below the results
   * @param maxPages
   */
 this.showPager = function(maxPages) {

   if (maxPages < 2) {
     // don't show pagers with only one page
     return;
   }
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

    let newUrl = url.toString();
    if (newUrl !== window.location.href) {
      // update browser
      window.history.pushState(null, '', newUrl);
    }
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
    this.toggleAll.checked = myself.areAllChecked();
    // form events
    myself.form.addEventListener('submit', myself.handleSubmit);
    myself.form.addEventListener('reset', function() {
      myself.handleClear(myself.areAnyChecked());
    });
    // history events
    window.addEventListener('popstate', myself.parseUrlState);

    this.markCheckboxColumns();
    window.addEventListener('resize', myself.markCheckboxColumns);
  };
}

jQuery(document).ready(async function () {
  "use strict";
  let docLang = [document.documentElement.lang];
  let benefitsPath, lifeEventsPath, labels;
  // using relative URL so that this works on static pages
  // Setup language specific inputs
  if (docLang[0] === 'en') {
    benefitsPath = "../_data/benefits-search/en/pages.json";
    lifeEventsPath = "../_data/benefits-search/en/life-events.json";
    labels = {
      'showingResults': '@first@&ndash;@last@ of @totalItems@ results',
      'page': "page",
      'next': "Next",
      'nextAria': "Next page",
      'previous': "Previous",
      'previousAria': "Previous page",
      'navAria': "Pagination",
      'lastPageAria': 'Last page',
      'emptyCategoryError': 'Error: Please select at least one or more categories',
      'appliedCategories': 'Applied categories',
      'lifeEventsCategory': 'Life events',
      'benefitFinderCategory': 'Benefit finder tool',
      'selectionsCleared': 'Your selections were cleared.',
    };
  }
  else if (docLang[0] === 'es') {
    benefitsPath = "../../_data/benefits-search/es/pages.json";
    lifeEventsPath = "../../_data/benefits-search/es/life-events.json";
    labels = {
      'showingResults': '@first@&ndash;@last@ de @totalItems@ resultados',
      'page': "página",
      'next': "Siguiente",
      'nextAria': "Página siguiente",
      'previous': "Anterior",
      'previousAria': "Página anterior",
      'navAria': "Paginación",
      'lastPageAria': 'Ultima página',
      'emptyCategoryError': 'Error: Por favor seleccione una o más categorías.',
      'appliedCategories': 'Categorías seleccionadas',
      'lifeEventsCategory': 'Etapas de la vida',
      'benefitFinderCategory': 'Buscador de beneficios',
      'selectionsCleared': 'Sus selecciones de categorías fueron reiniciadas.',
    };
  }
  // creat and initialize the search tool
  const ben = new BenefitSearch(
    benefitsPath,
    lifeEventsPath,
    '/themes/custom/usagov',
    docLang[0],
    labels,
    document.querySelector('#benefitSearch'),
    document.querySelector('#matchingBenefits'),
    5
  );
  await ben.init();
});
