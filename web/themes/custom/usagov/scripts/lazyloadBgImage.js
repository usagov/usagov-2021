document.addEventListener('lazybeforeunveil', function (e) {
  'use strict';
  var className, tempDiv, style, bg;

  className = e.target.getAttribute('data-bg');
  if (className) {
    // Create a temporary element with the same class
    tempDiv = document.createElement('div');
    tempDiv.className = className;
    document.body.appendChild(tempDiv);

    // Get the computed style of the temporary element
    style = getComputedStyle(tempDiv);
    bg = style.backgroundImage;

    // Remove the temporary element
    document.body.removeChild(tempDiv);

    // Set the background image of the target element
    if (bg && bg !== 'none') {
      e.target.style.backgroundImage = bg;
      e.target.classList.remove('lazyload');
      e.target.classList.add('lazyloaded');
    }
  }
});

