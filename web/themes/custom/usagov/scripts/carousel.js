$(".slides").slick({
  "dots": true,
  "infinite": true,
  "speed": 300,
  "slidesToShow": 3,
  "slidesToScroll": 1,
  "swipeToSlide": true,
  "touchMove": true,
  "arrowsPlacement": "split",
  "lazyLoad": "progressive",
  "prevArrow":
    '<button class="previous slick-prev slick-arrow">' +
    '  <span class="sr-only">Previous slides</span>' +
    '  <img src="/themes/custom/usagov/images/Reimagined_Carousel_Left_Arrow.svg" alt="Go to previous card"/>' +
    "</button>",
  "nextArrow":
    '<button class="next slick-next slick-arrow">' +
    '  <span class="sr-only">Next slides</span>' +
    '  <img src="/themes/custom/usagov/images/Reimagined_Carousel_Right_Arrow.svg" alt="Go to next card"/>' +
    "</button>",
  "responsive": [
    {
      "breakpoint": 2048,
      "settings": {
        "slidesToShow": 3,
        "slidesToScroll": 1,
        "infinite": true,
        "dots": true,
      },
    },
    {
      "breakpoint": 1024,
      "settings": {
        "slidesToShow": 2,
        "slidesToScroll": 1,
      },
    },
    {
      "breakpoint": 639,
      "settings": {
        "slidesToShow": 1,
        "slidesToScroll": 1,
      },
    },
  ],
})
.on("setPosition", function () {
  "use strict";

  resizeSlider();
});

var initSlide = getInitialSlide();
var slickHeight = $(".slick-track").outerHeight();

function resizeSlider() {
  "use strict";

  $(".slick-track")
    .find(".slick-slide .usa-card")
    .css("height", slickHeight + "px");
}

$('.slides').slick('slickGoTo', initSlide);

var carouselSlides = document.querySelector("#slides-list");
var slideIndex;
var slideTitle;

addIndexAttributeToDots();
setUpDotsListener();
setUpNavButtonListeners();
addAriaLabel();

function getInitialSlide() {
  "use strict";
  var currentSlideIndex = 0;
  var indexInSS;
  if ($("html").attr("lang") === "es") {
    indexInSS = sessionStorage.getItem("storedCarouselIndexSpanish");
  }
  else {
    indexInSS = sessionStorage.getItem("storedCarouselIndexEnglish");
  }
  if (indexInSS != null) {
    currentSlideIndex = indexInSS;
  }
  return currentSlideIndex;
}

function addIndexAttributeToDots() {
  "use strict";
  var dotsList = document.querySelectorAll(
    "#slides-list .slick-dots li button"
  );
  dotsList.forEach((btn, i) => {
    btn.setAttribute("dots-index", i);
  });
}

function setUpDotsListener() {
  "use strict";
  var dotsForListeners = document.querySelectorAll(
    "#slides-list .slick-dots li button"
  );
  dotsForListeners.forEach((btn) => {
    btn.addEventListener("click", moveFocusToCurrent);
  });
}

function setUpNavButtonListeners() {
  "use strict";
  var navForListeners = document.querySelectorAll(".slick-arrow");
  navForListeners.forEach((btn) => {
    btn.addEventListener("click", updateAriaText);
  });
}

function moveFocusToCurrent() {
  "use strict";
  window.setTimeout(function () {
    var slideForFocus = document.querySelector(
      "#slides-list .slick-list .slick-track .slick-slide.slick-current .slide a"
    );
    slideForFocus.focus({"focusVisible": true});
  }, 200);
}

function addAriaLabel() {
  "use strict";
  var liveregion = document.createElement("div");
  liveregion.setAttribute("aria-live", "polite");
  liveregion.setAttribute("aria-atomic", "true");
  liveregion.setAttribute("class", "liveregion visuallyhidden");
  carouselSlides.appendChild(liveregion);
}

$(".slides").on(
  "beforeChange",
  function (event, slick, currentSlide, nextSlide) {
    "use strict";
    slideIndex = nextSlide + 1;

    var NextSlideDom=$(slick.$slides.get(nextSlide));

    slideTitle = NextSlideDom.find('h3')[0].textContent || NextSlideDom.find('h3')[0].innerText;
  }
);

$(".slides").on(
  "afterChange",
  function (event, slick, currentSlide) {
    "use strict";
    updateSessionStorage(currentSlide);
  }
);

function updateAriaText() {
  "use strict";
  carouselSlides.querySelector(".liveregion").textContent =
    "Slide " + slideIndex + " of 6 " + slideTitle;
}

function updateSessionStorage(currentIndex) {
  "use strict";
  if ($("html").attr("lang") === "es") {
    sessionStorage.setItem("storedCarouselIndexSpanish", currentIndex);
  }
  else {
    sessionStorage.setItem("storedCarouselIndexEnglish", currentIndex);
  }
}