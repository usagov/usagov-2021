/**
 * @callback onClickCallback
 * @param {int} page -- page to display
 *
 * @typedef {Object} PaginationOptions
 * @property {string} page -- label for "Page X"
 * @property {string} next -- label for next page link
 * @property {string} nextAria -- aria role for next page link
 * @property {string} previous -- label for previous page link
 * @property {string} previousAria -- aria role for previous page link
 */

 /**
 * @param {int} total
 * @param {int} current
 * @param {PaginationOptions} labels
 * @param {onClickCallback} onClick -- runs when link is clicked
 * @property {PaginationOptions} labels
 * @property {onClickCallback} onClick
 * @constructor
 */
function Pagination(total, current, labels, onClick) {
  "use strict";

  this.total = total;
  this.current = current;
  this.labels = labels;
  this.assetBase = '/themes/custom/usagov';
  this.onClick = onClick;
  this.nav = null;

  let myself = this;

   /**
    * Handle when a pager link is clicked to internally update the displayed pager.
    * Then invoke the callback configured with the requested page number
    * @param ev
    */
  this.handlePageClick = function(ev) {
    /**
     * @var {Element} link
     */
    let link = ev.target;
    // get other links in the list
    const current = link.parentElement
      .parentElement
      .querySelector('li > a[class*="usa-current"]')
    ;
    // remove current marker from siblings
    current.classList.remove('usa-current');
    current.removeAttribute('aria-current');
    // mark our link as the current one
    link.classList.add('usa-current');
    link.setAttribute('aria-current', myself.labels.page);
    // trigger configured click handler
    const num = parseInt(link.innerHTML);

    myself.setCurrentPage(num);
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
    ><span class="usa-pagination__link-text">${this.labels.next}</span><svg class="usa-icon" aria-hidden="true" role="img">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_next"></use>
        </svg></a>`
    );

    link.addEventListener('click', function() {
      myself.current += 1;
      myself.onClick(myself.current);
    });

    if (myself.current === myself.total) {
      link.classList.add('display-none');
    }
    return link;
  };
  /**
   * Build the link to go to a specific numeric page
   * @param {int} num
   * @returns {HTMLLIElement}
   */
  this.makePageLink = function(num) {
    // get a basic link
    let link = this.makeLink(
      "usa-pagination__item usa-pagination__page-no",
      `<a
        href="javascript:void(0);"
        class="usa-pagination__button"
        aria-label="${this.labels.page} ${num}"
        >${num}</a>`
    );

    link.querySelector('a')
      .addEventListener('click', myself.handlePageClick);

    // highlight this page if it's the current one
    if (num === this.current) {
      let atag = link.querySelector('a');
      atag.classList.add('usa-current');
      atag.setAttribute('aria-current', myself.labels.page);
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
    ><svg class="usa-icon" aria-hidden="true" role="img">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_before"></use>
        </svg>
        <span class="usa-pagination__link-text">${this.labels.previous}</span></a>`
    );

    link.addEventListener('click', function(ev) {
      myself.current -= 1;
      myself.onClick(myself.current);
    });

    if (this.current === 1) {
      link.classList.add('display-none');
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
      link.classList.add('display-none');
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
    myself.current = num;

    // if on first page, need to hide previous link
    const prev = myself.nav.querySelector('a.usa-pagination__previous-page').parentNode;
    if (num === 1) {
      prev.classList.add('display-none');
    }
    else {
      prev.classList.remove('display-none');
    }

    // likewise, hide next if we're on last page
    const next = myself.nav.querySelector('a.usa-pagination__next-page').parentNode;
    if (num === myself.total) {
      next.classList.add('display-none');
    }
    else {
      next.classList.remove('display-none');
    }
  };
   /**
    * Create the nav element to add to the page
    * @returns {HTMLElement}
    */
  this.render = function() {
    let nav = document.createElement('nav');
    nav.className = "usa-pagination";
    nav.setAttribute('aria-label', 'Pagination');
    let list = document.createElement('ul');
    let startAt, stopAt;
    // figure out what pages we initially draw
    // let startAt = this.current > 4 ? this.current - 1 : 2;
    // let stopAt = this.current < this.total - 1 ? this.current + 1 : this.total - 1;
    // if we're near the beginning of the pagination,
    // we always display the same 4 slots after the first page
    // show enough pages if we're near the beginning
    if (this.current <= 4) {
      startAt = 2;
      stopAt = 5;
    }
    else {
      startAt = this.current - 1;
    }
    // if the current page is within the last 4 slots of the end of pagination,
    // we always display the same 4 slots before the last page
    if (this.current >= this.total - 4) {
      startAt = this.total - 4;
      stopAt = this.total - 1;
    }
    else if (this.current > 4) {
      stopAt = this.current + 1;
    }

    list.className = 'usa-pagination__list';
    // previous link
    list.append(this.makePreviousLink());
    // always show first page
    list.append(this.makePageLink(1));
    // first spacer
    list.append(this.makeSpacer(startAt > 3));
    // numeric pages
    for (let i = startAt; i <= stopAt; i++) {
      list.append(this.makePageLink(i));
    }
    // second spacer
    list.append(this.makeSpacer(stopAt < this.total - 2));
    // and always show the last page
    list.append(this.makePageLink(this.total));
    // next link
    list.append(this.makeNextLink());
    // add it all to container
    nav.append(list);

    this.nav = nav;
    return nav;
  };
}
