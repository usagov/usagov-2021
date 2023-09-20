$(".slides").slick({
  "dots": true,
  "infinite": true,
  "speed": 300,
  "slidesToShow": 3,
  "slidesToScroll": 1,
  "swipeToSlide": true,
  "touchMove": true,
  "arrowsPlacement": "split",
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
});

var carouselSlides = document.querySelector("#slides-list");
var slideIndex;

addIndexAttributeToDots();
setUpDotsListener();
setUpNavButtonListeners();
addAriaLabel();

function addIndexAttributeToDots() {
  var dotsList = document.querySelectorAll(
    "#slides-list .slick-dots li button"
  );
  dotsList.forEach((btn, i) => {
    btn.setAttribute("dots-index", i);
  });
}

function setUpDotsListener() {
  var dotsForListeners = document.querySelectorAll(
    "#slides-list .slick-dots li button"
  );
  dotsForListeners.forEach((btn) => {
    btn.addEventListener("click", moveFocusToCurrent);
  });
}

function setUpNavButtonListeners() {
  var navForListeners = document.querySelectorAll(".slick-arrow");
  navForListeners.forEach((btn) => {
    btn.addEventListener("click", updateAriaText);
  });
}

function moveFocusToCurrent() {
  window.setTimeout(function () {
    var slideForFocus = document.querySelector(
      "#slides-list .slick-list .slick-track .slick-slide.slick-current .slide a"
    );
    slideForFocus.focus({"focusVisible": true});
  }, 200);
}

function addAriaLabel() {
  var liveregion = document.createElement("div");
  liveregion.setAttribute("aria-live", "polite");
  liveregion.setAttribute("aria-atomic", "true");
  liveregion.setAttribute("class", "liveregion visuallyhidden");
  carouselSlides.appendChild(liveregion);
}

$(".slides").on(
  "beforeChange",
  function (event, slick, currentSlide, nextSlide) {
    slideIndex = nextSlide + 1;
  }
);

function updateAriaText() {
  carouselSlides.querySelector(".liveregion").textContent =
    "Slide " + slideIndex + " of 6";
}
