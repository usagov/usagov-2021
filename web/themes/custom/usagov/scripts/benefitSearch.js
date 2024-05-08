const benefitSearch = {
  /**
   * @param {string} url
   * @returns {Promise<any>}
   */
  "fetch": async function(url) {
    "use strict";

    const response = await fetch(url);
    if (!response.ok) {
      throw new Error('Error fetching benefits ' + response.status);
    }
    return await response.json();
  },

  /**
   *
   * @param {Event} ev
   * @param {NodeListOf<Element>} boxes
   */
  "toggleCheckboxes": function(ev, boxes) {
    "use strict";

    let newState = ev.target.checked;
    for (const box of boxes) {
      box.checked = newState;
    }
  },

  /**
   *
   * @param {Element} container
   * @param benefits
   */
  "showResults": function(container, benefits) {
    "use strict";

    container.scrollIntoView({"behavior": 'smooth'});

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

      container.innerHTML = container.innerHTML + elt.innerHTML;
    }
  }
};

jQuery(document).ready(async function () {
  "use strict";

  let toggleAll = document.querySelector('#benefitSearch input[type="checkbox"][value="all"]');
  let boxes = document.querySelectorAll('#benefitSearch input[type="checkbox"]');
  let container = document.querySelector('#matchingBenefits');

  toggleAll.addEventListener('click', (ev) => {
    benefitSearch.toggleCheckboxes(ev, boxes);
  });

  // 1) load search json (todo: toggle languages)
  const src = "/benefits-search.json";
  const benefits = await benefitSearch.fetch(src);

  let form = document.querySelector('#benefitSearch');
  // 2) on click of submit, find potential matches for selected categories
  form.addEventListener('submit', (ev) => {
    //  a) grab term ids from checked filters
    let checked = form.querySelectorAll('#benefitSearch input[type="checkbox"]:checked');

    if (checked.length === 0) {
      alert('Select at least one');
      return;
    }

    container.innerHTML = '';
    let terms = Array.from(checked).map((elt) => {
      return elt.value;
    });

    // keep the benefits that match
    let matches = benefits.filter((item) => {
      let numMatches = item.field_category.filter((value) => terms.includes(value));
      return numMatches.length > 0;
    });

    console.log(terms);

    //  c) display matching pages (later - paginate results)
    benefitSearch.showResults(container, matches);
  });

  form.addEventListener('reset', () => {
    container.innerHTML = '';
  });
});
