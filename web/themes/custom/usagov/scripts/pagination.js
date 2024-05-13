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

  let myself = this;

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
    myself.current = num;
    myself.onClick(num);
  };
  /**
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
   * @returns {HTMLLIElement}
   */
  this.makeNextLink = function() {
    let link = this.makeLink(
      "usa-pagination__item usa-pagination__arrow",
      `<a href="javascript:void(0)" class="usa-pagination__link usa-pagination__previous-page"
       aria-label="${this.labels.nextAria}"
    ><span class="usa-pagination__link-text">${this.labels.next}</span><svg class="usa-icon" aria-hidden="true" role="img">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_next"></use>
        </svg></a>`
    );

    let myself = this;
    link.addEventListener('click', function() {
      myself.current += 1;
      myself.onClick(myself.current);
    });

    return link;
  };

  /**
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
   * @returns {HTMLLIElement}
   */
  this.makePreviousLink = function() {
    let link = this.makeLink(
      "usa-pagination__item usa-pagination__arrow",
      `<a href="javascript:void(0)" class="usa-pagination__link usa-pagination__next-page"
       aria-label="${this.labels.previousAria}"
    ><svg class="usa-icon" aria-hidden="true" role="img">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_before"></use>
        </svg>
        <span class="usa-pagination__link-text">${this.labels.previous}</span></a>`
    );

    let myself =this;
    link.addEventListener('click', function(ev) {
      myself.current -= 1;
      myself.onClick(myself.current);
    });

    return link;
  };
  this.setToCurrent = function(num) {
    // @todo refactor setting the current page to one
    //       spot that has the html structure?
  };
  this.render = function() {
    let nav = document.createElement('nav');
    nav.className = "usa-pagination";
    nav.setAttribute('aria-label', 'Pagination');
    let list = document.createElement('ul');
    list.className = 'usa-pagination__list';
    // previous link
    list.append(this.makePreviousLink());
    // numeric pages
    for (let i = 1; i <= this.total ; i++) {
      list.append(this.makePageLink(i));
    }
    // next link
    list.append(this.makeNextLink());
    // add it all to container
    nav.append(list);
    return nav;
  };
}
