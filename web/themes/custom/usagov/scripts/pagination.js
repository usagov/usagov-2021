/**
 * @callback onClickCallback
 * @param {int} page -- page to display
 *
 * @typedef {Object} PaginationOptions
 * @property {string} page -- label for "Page X"
 * @property {string} next -- label for next page link
 * @property {string} nextAria -- aria label for next page link
 * @property {string} previous -- label for previous page link
 * @property {string} previousAria -- aria label for previous page link
 * @property {string} navAria -- aria label for nav container
 * @property {string} lastPageAria -- aria role for nav container
 */

/**
 * @param {int} total
 * @param {int} current
 * @param {string} assetBase
 * @param {PaginationOptions} labels
 * @param {onClickCallback} onClick -- runs when link is clicked
 * @property {PaginationOptions} labels
 * @property {onClickCallback} onClick
 * @constructor
 */
function Pagination(total, current, assetBase, labels, onClick) {
  "use strict";

  this.total = total;
  this.current = current;
  this.preCurrent = null;
  this.assetBase = assetBase;
  this.labels = labels;
  this.onClick = onClick;
  this.nav = null;

  let myself = this;
  myself.pageLinks = [];
   /**
    * Figures out what range of pages we need to show
    * @returns {{stopAt: number, startAt: number}}
    */
  this.getNumericRange = function() {
    let startAt, stopAt;
    // if we're near the beginning of the pagination,
    // we always display the same 4 slots after the first page
    if (this.current <= 4) {
      startAt = 2;
      stopAt = Math.min(5, this.total - 1);
    }
    // if the current page is within the last 4 slots of the end of pagination,
    // we always display the same 4 slots before the last page
    else if (this.current > this.total - 4) {
      startAt = Math.max(2, this.total - 4);
      stopAt = Math.max(1, this.total - 1);
    }
    // otherwise, show the page before and after the current one
    else {
      startAt = this.current - 1;
      stopAt = this.current + 1;
    }

    return {
      "startAt": startAt,
      "stopAt": stopAt
    };
  };
   /**
    * Handle when a pager link is clicked to internally update the displayed pager.
    * Then invoke the callback configured with the requested page number
    * @param {int} num
    * @param {Element} link
    */
  this.handlePageClick = function(num, link) {
    // set current page and then re-draw the pager elements
    myself.setCurrentPage(num);
    myself.updatePageLinks(link);
    // trigger configured click handler
    myself.onClick(num);
  };
  /**
   * Utility method to create a link element to use in our pager
   * @param className
   * @param innerHTML
   * @returns {HTMLLIElement}
   */
  this.makeLink = function(className, innerHTML) {
    const link = document.createElement('li');
    link.className = className;
    link.innerHTML = innerHTML;
    return link;
  };

  /**
   * Build the link to go to the next page
   * @returns {HTMLLIElement}
   */
  this.makeNextLink = function() {
    let link = this.makeLink(
      "usa-pagination__item usa-pagination__arrow",
      `<a href="javascript:void(0)" class="usa-pagination__link usa-pagination__next-page"
       aria-label="${this.labels.nextAria}"
    ><span class="usa-pagination__link-text">${this.labels.next}</span><svg class="usa-icon" aria-hidden="true" role="presentation">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_next"></use>
        </svg></a>`
    );

    link.addEventListener('click', function() {
      let num = myself.current + 1;
      myself.handlePageClick(num, link);
    });

    if (myself.current === myself.total) {
      myself.hideElement(link);
    }
    return link;
  };
  /**
   * Build the link to go to a specific numeric page
   * @param {int} num
   * @param {boolean} isLast
   * @returns {HTMLLIElement}
   */
  this.makePageLink = function(num, isLast) {
    // get a basic link
    let link = this.makeLink(
      "usa-pagination__item usa-pagination__page-no",
      `<a
        href="javascript:void(0);"
        class="usa-pagination__button"
        aria-label="${this.labels.page} ${num}"
        >${num}</a>`
    );
    // handle clicks
    link.querySelector('a')
      .addEventListener('click', function(ev) {
        const link = ev.target;
        const num = parseInt(link.innerText);
        myself.handlePageClick(num, link);
      });

    let atag = link.querySelector('a');
    if (num === this.current) {
      // highlight this page if it's the current one
      atag.classList.add('usa-current');
      atag.setAttribute('aria-current', 'page');
    }

    if (isLast) {
      atag.setAttribute(
        'aria-label',
        myself.labels.lastPageAria + ", " + atag.getAttribute('aria-label')
      );
    }


    return link;
  };
  /**
   * Build the link to go to the previous page
   * @returns {HTMLLIElement}
   */
  this.makePreviousLink = function() {
    let link = this.makeLink(
      "usa-pagination__item usa-pagination__arrow",
      `<a href="javascript:void(0)" class="usa-pagination__link usa-pagination__previous-page"
       aria-label="${this.labels.previousAria}"
    ><svg class="usa-icon" aria-hidden="true" role="presentation">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_before"></use>
        </svg>
        <span class="usa-pagination__link-text">${this.labels.previous}</span></a>`
    );

    link.addEventListener('click', function(ev) {
      let num = myself.current - 1;
      myself.handlePageClick(num, link);
    });

    if (this.current === 1) {
      myself.hideElement(link);
    }

    return link;
  };
   /**
    *
    * @param {boolean} isDisplayed
    * @returns {HTMLLIElement}
    */
  this.makeSpacer = function(isDisplayed) {
    let link = this.makeLink(
      'usa-pagination__item usa-pagination__overflow',
      '<span>…</span>'
      );
    if (isDisplayed === false) {
      myself.hideElement(link);
    }
    // todo translate
    link.setAttribute('aria-label', "ellipsis indicating non-visible pages");
    return link;
  };
   /**
    * Makes the requested page the current one and makes it visible.
    * @param num
    */
  this.setCurrentPage = function(num) {
    myself.preCurrent = myself.current;
    myself.current = num;

    // if on first page, need to hide previous link
    const prev = myself.nav.querySelector('a.usa-pagination__previous-page').parentNode;
    if (num === 1) {
      myself.hideElement(prev);
    }
    else {
      myself.showElement(prev);
    }
    // likewise, hide next if we're on last page
    const next = myself.nav.querySelector('a.usa-pagination__next-page').parentNode;
    if (num === myself.total) {
      myself.hideElement(next);
    }
    else {
      myself.showElement(next);
    }
    // Show the first separator if we are beyond the first four pages
    if (myself.firstSpacer) {
      if (num > 4) {
        myself.showElement(myself.firstSpacer);
      }
      else {
        myself.hideElement(myself.firstSpacer);
      }
    }
    // show the last separator if we are near the end of pagination
    if (myself.lastSpacer) {
      if (num <= this.total - 4 && myself.total > 6) {
        myself.showElement(myself.lastSpacer);
      }
      else {
        myself.hideElement(myself.lastSpacer);
      }
    }
  };
   /**
    * Returns the key of the node to the right of num in existing pager links
    * @param {int} num
    * @returns {*}
    */
  this.getNextSiblingKey = function(num) {
    let keys = [];
    for (const key in myself.pageLinks) {
      if (parseInt(key) > num) {
        keys.push(key);
      }
    }
    if (keys.length > 0) {
      return keys[0];
    }
    return null;
  };
   /**
    * @param {Element} elt
    */
   this.hideElement = function (elt) {
     elt.classList.add('display-none');
   };
   /**
    * @param {Element} elt
    */
   this.showElement = function (elt) {
     elt.classList.remove('display-none');
  };
   /**
    * Create the nav element to add to the page
    * @returns {HTMLElement}
    */
  this.render = function() {
    let nav = document.createElement('nav');
    nav.className = "usa-pagination";
    nav.setAttribute('aria-label', this.labels.navAria);
    let list = document.createElement('ul');
    list.className = 'usa-pagination__list';
    myself.list = list;

    // let's figure out what pages to draw
    const pages = this.getNumericRange();
    // previous link
    list.append(this.makePreviousLink());
    // always show first page
    list.append(this.makePageLink(1, false));
    // first spacer
    if (myself.total > 6) {
      myself.firstSpacer = this.makeSpacer(pages.startAt > 2);
      list.append(myself.firstSpacer);
    }
    // numeric pages
    for (let i = pages.startAt; i <= pages.stopAt; i++) {
      myself.pageLinks[i] = this.makePageLink(i, false);
      list.append(myself.pageLinks[i]);
    }
    if (myself.total > 6) {
      myself.lastSpacer = this.makeSpacer(pages.stopAt < this.total - 1);
      list.append(myself.lastSpacer);
    }
    // show the last page if we have more than one
    if (this.total > 1) {
      myself.lastPage = this.makePageLink(this.total, true);
      list.append(myself.lastPage);
    }
    // next link
    list.append(this.makeNextLink());

    // add it all to container
    nav.append(list);

    this.nav = nav;
    return nav;
  };

  this.updatePageLinks = function(link) {
    // figure out which pages we need to show
    const pages = this.getNumericRange();
    // hide the ones outside this range
    for (let i in myself.pageLinks) {
      if (i < pages.startAt || i > pages.stopAt) {
        this.hideElement(myself.pageLinks[i]);
      }
    }
    // now show the correct page links
    for (let i = pages.startAt; i <= pages.stopAt; i++) {
      if (myself.pageLinks[i]) {
        this.showElement(myself.pageLinks[i]);
      }
      else {
        myself.pageLinks[i] = this.makePageLink(i, false);
        if (i <= myself.preCurrent && i < myself.total - 4) {
          // Since we're counting up to insert nodes, we need a little
          // help here to find what numeric pager element in front of
          // which we need to insert the new one unless we're near the last page.
          const nextSibling = this.getNextSiblingKey(i);
          if (nextSibling) {
            myself.pageLinks[nextSibling]
              .insertAdjacentElement('beforebegin', myself.pageLinks[i]);
          }
        }
        else {
          // Unlike above, we can always insert this new element
          // before the last spacer. It will always be the rightmost one
          myself.lastSpacer
            .insertAdjacentElement('beforebegin', myself.pageLinks[i]);
        }
      }
    }

    // get other links in the list
    const current = link.parentElement
      .parentElement
      .querySelector('li > a[class*="usa-current"]')
    ;
    // remove current marker from siblings
    current.classList.remove('usa-current');
    current.removeAttribute('aria-current');
    // mark the current page
    const pageLinks = link.parentElement
      .parentElement
      .querySelectorAll('li.usa-pagination__page-no > a')
    ;

    pageLinks.forEach(function(link) {
      if (link.innerText === myself.current.toString()) {
        link.classList.add('usa-current');
        link.setAttribute('aria-current', 'page');
      }
    });
  };
}
