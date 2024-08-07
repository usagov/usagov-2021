jQuery(document).ready(function () {
  "use strict";

  // map of class names to relative image paths
  var imageMap = {
    'icon-about': '/themes/custom/usagov/images/topics/ICONS_Reimagined_About_the_US_and_Its_Government.svg',
    'icon-laws': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Laws_and_Legal_Issues.svg',
    'icon-money': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Money.svg',
    'icon-benefits': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Benefits.svg',
    'icon-immigration': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Immigration.svg',
    'icon-voting': '/themes/custom/usagov/images/topics/ICONS_Voting_and_elections_patriotic.svg',
    'icon-military': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Military_and_Veterans.svg',
    'icon-travel': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Travel.svg',
    'icon-education': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Education.svg',
    'icon-housing': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Housing.svg',
    'icon-disasters': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Disasters_and_Emergencies.svg',
    'icon-covid19': '/themes/custom/usagov/images/topics/ICONS_Reimagined_COVID19.svg',
    'icon-health': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Health.svg',
    'icon-scams': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Scams_and_Fraud.svg',
    'icon-disability': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Disability_Services.svg',
    'icon-jobs': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Jobs_and_Unemployment.svg',
    'icon-business': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Small_Business.svg',
    'icon-taxes': '/themes/custom/usagov/images/topics/ICONS_Reimagined_Taxes.svg',
    'icon-complaints': '/themes/custom/usagov/images/topics/ICONS_Complaints.svg',
    'icon-life-events': '/themes/custom/usagov/images/topics/ICONS_Life_events.svg'
  };

  let options = {
    "rootMargin": "0px",
    "threshold": 0.001,
  };

  const lazyLoadingObserver = new IntersectionObserver((elements) => {
    elements.forEach((element) => {
      if (element.isIntersecting) {

        var className, baseURL, relativePath, bg;

        // get target element via data-bg attribute
        className = element.target.getAttribute('data-bg');

        // get window location and relative path of image
        baseURL = window.location.origin;
        relativePath = imageMap[className];

        // build full background image URL
        if (relativePath) {
          bg = `url(${baseURL}${relativePath})`;
        }

        // Set the background image of the target element
        if (bg && bg !== 'none') {
          element.target.style.backgroundImage = bg;
          lazyLoadingObserver.unobserve(element.target);
        }
      }
    });
  }, options);

  for (const imgClass in imageMap) {
    if (imageMap.hasOwnProperty(imgClass)) {
      var imageElement = document.getElementsByClassName(`${imgClass} lazyload`)[0];
      if (imageElement) {
        lazyLoadingObserver.observe(imageElement);
      }
    }
  }
});
