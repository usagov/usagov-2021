/*
 * References to "Life Events" can be English or Spanish. The entity reference
 * field on a term form shows all terms regardless of language.
 *
 * This script dynamically shows/hides life events that match the language of
 * the term being added or edited.
 */
(function (jQuery, Drupal, drupalSettings) {

  jQuery('#edit-field-category-life-events--wrapper legend').append(
    "<span id='life-events-filter-details' data-filtered='true'></span>"
  );

  updateLifeEventRef(jQuery);

  jQuery('#edit-langcode-0-value').on('change', function(){
    updateLifeEventRef(jQuery);
  });

})(jQuery);

function updateLifeEventRef(jQuery) {

  const $details = jQuery('#life-events-filter-details');
  const $editField = jQuery('#edit-field-category-life-events');
  let filtered = $details.attr('data-filtered');

  if (filtered === "true") {
    let selectedLanguage = jQuery("#edit-langcode-0-value :selected").text();
    let otherCheckedEvents = 0;

    $editField.children().each(
      function () {
        if (jQuery(this).find('[data-language=' + selectedLanguage + ']').length) {
          jQuery(this).show();
        } else if (jQuery(this).find('input').is(":checked")) {
          jQuery(this).show();
          otherCheckedEvents++;
        } else {
          jQuery(this).hide();
        }
      }
    );

    let extraDetail = "";
    if (otherCheckedEvents > 0){
      extraDetail = " and " + otherCheckedEvents + " other checked life event"
                  + otherCheckedEvents > 1 ? "s" : "";
    }

    $details.html(
      "Showing " + selectedLanguage + " life events" + extraDetail
      + " <button onclick='updateFilterState(\"false\")'>Show all life events. </button>"
    );

    return;
  }

  $editField.children().show();
  $details.html(
    "Showing all life events. <button onclick='updateFilterState(\"true\")'>Filter life events by selected language</button>"
  );
}

function updateFilterState(state) {
  jQuery('#life-events-filter-details').attr('data-filtered', state);
  updateLifeEventRef(jQuery);
}
