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
    return this.makeLink(
      "usa-pagination__item usa-pagination__arrow",
      `<a href="javascript:void(0)" class="usa-pagination__link usa-pagination__previous-page"
       aria-label="${this.labels.nextAria}"
    ><span class="usa-pagination__link-text">${this.labels.next}</span><svg class="usa-icon" aria-hidden="true" role="img">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_next"></use>
        </svg></a>`
    );
  };

  /**
   * @param {int} num
   * @returns {HTMLLIElement}
   */
  this.makePageLink = function(num) {
    let link =this.makeLink(
      "usa-pagination__item usa-pagination__page-no",
      `<a
        href="javascript:void(0);"
        class="usa-pagination__button"
        aria-label="${this.labels.page} ${num}"
        >${num}</a>`
    );

    let myself = this;
    link.addEventListener('click', function(ev) {
      myself.onClick(num);
    });

    return link;
  };
  /**
   * @returns {HTMLLIElement}
   */
  this.makePreviousLink = function() {
    return this.makeLink(
      "usa-pagination__item usa-pagination__arrow",
      `<a href="javascript:void(0)" class="usa-pagination__link usa-pagination__next-page"
       aria-label="${this.labels.previousAria}"
    ><svg class="usa-icon" aria-hidden="true" role="img">
          <use xlink:href="${this.assetBase}/assets/img/sprite.svg#navigate_before"></use>
        </svg>
        <span class="usa-pagination__link-text">${this.labels.previous}</span></a>`
    );
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
