/**
 *
 * @param {string} src
 * @param {Element} form
 * @param {Element} resultsContainer
 * @constructor
 */
function BenefitSearch(src, form, resultsContainer) {
  "use strict";

  this.src = src;
  this.resultsContainer = resultsContainer;
  this.form = form;
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

    //  c) display matching pages (later - paginate results)
    myself.showResults(matches);
  };

  /**
   * @param benefits
   */
  this.showResults = function(benefits) {

    myself.resultsContainer.scrollIntoView({"behavior": 'smooth'});

    for (const b of benefits) {
      let elt = document.createElement('template');
      let descr;

      if (b.field_page_intro) {
        descr = b.field_page_intro;
      }
      else if (b.field_short_description) {
        descr = b.field_short_description;
      }

      elt.innerHTML += `<div><h3>${b.title}</h3><p>${descr}</p></div>`;

      myself.resultsContainer.innerHTML += elt.innerHTML;
    }
  };

  this.showError = function() {
    let elt = document.createElement('template');
    elt.innerHTML = '<div class="error">Select one or more categories</div>';

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
    document.querySelector('#matchingBenefits')
  );
  await ben.init();
});
