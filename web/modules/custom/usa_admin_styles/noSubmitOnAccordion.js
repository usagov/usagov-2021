// Preview content in the rich text editor may contain buttons, which
// will, by default, submit the node edit form if pressed. This
// function adds a submit handler to all forms that suppresses form
// submission when the button pressed has the "usa-accordion__button"
// class.

(function ($, Drupal, drupalSettings) {

    $('form').on('submit', function(e) {
        if (e.originalEvent.submitter.classList.contains('usa-accordion__button')) {
            e.preventDefault();
            return false;
        }
    });

})(jQuery, Drupal, drupalSettings);
