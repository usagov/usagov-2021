
(function disableMobileNavToggle() {
	"use strict";
	// USWDS adds a handler (on the body element) that closes the menu when a nav link
	// is clicked. We don't want this when the user clicks to another page, so we
	// "stop propagation" on "regular" mobile nav links.

	let nav_link_selector = '.usagov-mobile-menu a';
	let current_page_selector = '.usagov-mobile-menu .navigation__item.active>a';
	document.querySelectorAll(nav_link_selector)
		.forEach(function (mobileNavLink) {
			if (!mobileNavLink.matches(current_page_selector)) {
				mobileNavLink.addEventListener("click", function (e) {
					e.stopPropagation();
				});
			}
		});
})();


(function menuButtonModifications() {
	"use strict";
	// USWDS applies aria-hidden attribute to non-nav elements but misses some because Drupal adds markup that USWDS does not account for.
	// As a result, screen reader users can navigate out of the mobile menu while it is open.
	// To prevent this, we are applying aria-hidden to the the elements that USWDS missed.
	// We also set the focus to the home link because that is the first focusable element in our mobile menu.

	let menu_button = document.querySelector('.usa-menu-btn');
	let home_link = document.querySelector('#mobile-menu-home-link');
	menu_button.addEventListener("click", function (e) {
		let header_non_nav_elements = document.querySelectorAll('.usa-banner-inner>div>:not(.usagov-mobile-menu)');
		for (const element of header_non_nav_elements) {
			element.setAttribute('aria-hidden', 'true');
			element.setAttribute('data-nav-hidden', 'true');
		}
		setTimeout(() => {
			home_link.focus();
		}, 100);
	});
})();

