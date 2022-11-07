jQuery(document).ready(function ($) {
  // console.log("in carousel js");
  var previousButton, nextButton;
  var slidesContainer, slides, slideDots;
  var leftMostSlideIndex = 0;

  previousButton = document.querySelector(".previous");
  nextButton = document.querySelector(".next");
  slidesContainer = document.querySelector(".slides");
  slides = slidesContainer.querySelectorAll(".slide");
  makeDots();
  slideDots = document.querySelectorAll(".navigation li a");
  previousButton.style.visibility = "hidden";
  if (slideDots.length > 0) {
    slideDots[0].setAttribute("aria-current", true);
  }

  // Set up the slide dot behaviors
  slideDots.forEach(function (dot) {
    dot.addEventListener("click", function (e) {
      goToSlide(Array.prototype.slice.call(slideDots).indexOf(e.target));
    });
  });
  // Set up previous/next button behaviors
  previousButton.addEventListener("click", previousSlide);
  nextButton.addEventListener("click", nextSlide);

  // Ensure that all non-visible slides are impossible to reach.
  hideNonVisibleSlides();

  /** For Pagination */
  function makeDots() {
    // console.log("in make dots");
    var numSlides = slides.length;
    // console.log(numSlides);
    var dots = document.getElementsByClassName("navigation")[0];
    for (var i = 0; i < numSlides; i++) {
      var li = document.createElement("li");
      li.classList.add("usa-pagination__item");
      li.classList.add("usa-pagination__page-no");
      // var klass = 'class="sr-only" ';
      var pageNum = i + 1;
      li.innerHTML =
        // '<button> <span class="sr-only"> Go to slide ' + i + "</span></button>";
        ' <a href="javascript:void(0);" class="usa-pagination__button" aria-label="Page ' +
        pageNum +
        '">' +
        pageNum +
        "</a> ";
      dots.appendChild(li);
    }
  }

  /** Go to previous slide */
  function previousSlide() {
    if (leftMostSlideIndex > 0) {
      goToSlide(leftMostSlideIndex - 1);
    } else {
      goToSlide(slides.length - 1);
    }

  }

  /** Go to next slide */
  function nextSlide() {
    if (leftMostSlideIndex < slides.length - 1) {
      goToSlide(leftMostSlideIndex + 1);
    } else {
      goToSlide(0);
    }
  }

  /** Go to a specific slide */
  function goToSlide(nextLeftMostSlideIndex) {
    // Smoothly scroll to the requested slide
    if (window.innerWidth >= 1024) {
      $(slidesContainer).animate(
        {
          scrollLeft:
            (slidesContainer.offsetWidth / 3) * nextLeftMostSlideIndex,
        },
        {
          duration: 200,
        }
      );
    } else if (window.innerWidth > 480 && window.innerWidth < 1024) {
      console.log("OFFSET WIDTH: " + slidesContainer.offsetWidth);
      $(slidesContainer).animate(
        {
          scrollLeft:
            (slidesContainer.offsetWidth / 2) * nextLeftMostSlideIndex,
        },
        {
          duration: 200,
        }
      );
    } else {
      $(slidesContainer).animate(
        {
          scrollLeft: slidesContainer.offsetWidth * nextLeftMostSlideIndex,
        },
        {
          duration: 200,
        }
      );
    }

    // Unset aria-current attribute from any slide dots that have it
    slideDots.forEach(function (dot) {
      dot.removeAttribute("aria-current");
    });

    // Set aria-current attribute on the correct slide dot
    slideDots[nextLeftMostSlideIndex].setAttribute("aria-current", true);

    // Update the record of the left-most slide
    leftMostSlideIndex = nextLeftMostSlideIndex;

    // Update each slide so that the ones that are now off-screen are fully hidden.
    hideNonVisibleSlides();

    //check if the left or right arrow should be hidden
    if (leftMostSlideIndex == 0) {
      previousButton.style.visibility = "hidden";
      nextButton.style.visibility = "visible";
    } else if (leftMostSlideIndex == slides.length - 1) {
      previousButton.style.visibility = "visible";
      nextButton.style.visibility = "hidden";
    } else {
      previousButton.style.visibility = "visible";
      nextButton.style.visibility = "visible";
    }
  }

  /**
  Fully hide non-visible slides by adding aria-hidden="true" and tabindex="-1" when they go out of view
*/
  function hideNonVisibleSlides() {
    // Start by hiding all the slides and their content
    slides.forEach(function (slide) {
      slide.setAttribute("aria-hidden", true);

      slide
        .querySelectorAll('a, button, select, input, textarea, [tabindex="0"]')
        .forEach(function (focusableElement) {
          focusableElement.setAttribute("tabindex", -1);
        });
    });

    var offset = 0;
    var rightLimit = 0;
    var numItems = 6;
    var leftLimit = 0;

    if (window.innerWidth >= 1024) {
      offset = 3;
      rightLimit = 3;
      leftLimit = 3;
    } else if (window.innerWidth > 480 && window.innerWidth < 1024) {
      offset = 2;
      rightLimit = 2;
      leftLimit = 4;
    } else {
      offset = 1;
      rightLimit = 1;
      leftLimit = 5;
    }

    if (leftMostSlideIndex < rightLimit) {
      for (var i = leftMostSlideIndex; i < leftMostSlideIndex + offset; i++) {
        slides[i].removeAttribute("aria-hidden");

        slides[i]
          .querySelectorAll(
            'a, button, select, input, textarea, [tabindex="0"]'
          )
          .forEach(function (focusableElement) {
            focusableElement.removeAttribute("tabindex");
          });
      }
    } else {
      for (var i = leftLimit; i < numItems; i++) {
        slides[i].removeAttribute("aria-hidden");

        slides[i]
          .querySelectorAll(
            'a, button, select, input, textarea, [tabindex="0"]'
          )
          .forEach(function (focusableElement) {
            focusableElement.removeAttribute("tabindex");
          });
      }
    }
  }
});
