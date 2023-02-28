jQuery(document).ready(function ($) {
  var previousButton, nextButton;
  var slidesContainer, slides, slideDots;
  var leftMostSlideIndex = 0;
  previousButton = document.querySelector(".previous");
  nextButton = document.querySelector(".next");
  slidesContainer = document.querySelector(".slides");
  slides = slidesContainer.querySelectorAll(".slide");
  slidesForFocus = slidesContainer.querySelectorAll(".slide a");
  carouselHeaders = document.querySelectorAll(".carouselHeaders");
  makeDots();
  slideDots = document.querySelectorAll(".navigation li button");

  // Set up the slide dot behaviors
  slideDots.forEach(function (dot, index) {
    dot.addEventListener("click", function (e) {
      goToSlide(index);
    });
  });
  // Set up previous/next button behaviors
  previousButton.addEventListener("click", previousSlide);
  nextButton.addEventListener("click", nextSlide);

  // Ensure that all non-visible slides are impossible to reach.
  hideNonVisibleSlides();

  var currentSlideIndex = 0;
  let indexInSS = sessionStorage.getItem("currentSlideIndexSS");
  if (indexInSS != null) {
    currentSlideIndex = indexInSS;
    goToSlide(currentSlideIndex);
  } else {
    previousButton.style.visibility = "hidden";
  }

  if (slideDots.length > 0) {
    slideDots[currentSlideIndex].setAttribute("aria-current", true);
  }

  // For Pagination
  function makeDots() {
    var numSlides = slides.length;
    var dots = document.getElementsByClassName("navigation")[0];
    for (var i = 0; i < numSlides; i++) {
      var li = document.createElement("li");
      var pageNum = i + 1;
      var title = carouselHeaders[i].textContent.trim();
      var titleWoQuotes = title.replace(/['"]+/g, '');
      var label = `Card ${pageNum} of ${numSlides}: ${titleWoQuotes}`;
      li.innerHTML = '<button class="carousel__navigation_button" aria-label=" '+ label + '"> <svg class="carousel__navigation_dot" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" > <circle cx="50%" cy="50%" r="47" /> </svg> </button>';
      dots.appendChild(li);
    }
  }

  // Go to previous slide
  function previousSlide() {
    if (leftMostSlideIndex > 0) {
      goToSlide(leftMostSlideIndex - 1);
    } else {
      goToSlide(slides.length - 1);
    }

  }

  // Go to next slide
  function nextSlide() {
    if (leftMostSlideIndex < slides.length - 1) {
      goToSlide(leftMostSlideIndex + 1);
    } else {
      goToSlide(0);
    }
  }

  // Go to a specific slide
  function goToSlide(nextLeftMostSlideIndex) {
    // console.log(`nextLeftMostSlideIndex: ${nextLeftMostSlideIndex}`);
    sessionStorage.setItem("currentSlideIndexSS", nextLeftMostSlideIndex);

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
    leftMostSlideIndex = Number(nextLeftMostSlideIndex);

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

    //set focus on current slide
    slidesForFocus[nextLeftMostSlideIndex].focus();
  }

  //Fully hide non-visible slides by adding aria-hidden="true" and tabindex="-1" when they go out of view

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
