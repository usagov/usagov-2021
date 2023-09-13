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

$(".slides").on("breakpoint", function (event, slick, breakpoint) {
  removeTabAbility();
});

window.addEventListener("resize", removeTabAbility);
window.addEventListener("load", removeTabAbility);

function removeTabAbility() {
  var slideDots = document.querySelectorAll(
    "#slidesList .slick-dots li button"
  );
  slideDots.forEach(function (buttonDot, index) {
    buttonDot.setAttribute("tabindex", "-1");
  });
}
