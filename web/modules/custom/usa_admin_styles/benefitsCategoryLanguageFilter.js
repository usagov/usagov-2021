/*
Terms in the "Benefits Category" taxonomy can be English or Español, and
the "Benefits Category Search" field displayed while editing a Basic Page
content type shows all the items by default.

This script filters those categories by the content's selected language.
*/

(function (jQuery, Drupal, drupalSettings) {

    jQuery('#edit-field-benefits-category--wrapper legend').append("<span id='benefits-category-filter-details' data-filtered='true'></span>");

    updateCategoryFilter(jQuery);

    jQuery('#edit-langcode-0-value').on('change', function(){
        updateCategoryFilter(jQuery);
    });

})(jQuery);

/*
The updateCategoryFilter function either filters the Benefits Categories by the currently selected language or
shows all categories depending on a data-attribute on the filter-details. It also updates the text within the 
filter-details to indicate whether the Benefits Categories are being filtered and provides a button a button to switch
modes.

It is called when the edit page loads as well as anytime the language field is changed.
*/
function updateCategoryFilter(jQuery){
    let filtered = jQuery('#benefits-category-filter-details').attr('data-filtered');
    if(filtered=="true"){
        let selectedLanguage = jQuery("#edit-langcode-0-value :selected").text();
        let otherCheckedCategories = 0;

        jQuery('#edit-field-benefits-category').children().each(function () {
            if( jQuery(this).find('[data-language='+selectedLanguage+']').length ){
                jQuery(this).show();
            }else if( jQuery(this).find('input').is(":checked") ){
                jQuery(this).show();
                otherCheckedCategories++;
            }else{
                jQuery(this).hide();
            }
        });

        let extraDetail = "";
        if(otherCheckedCategories > 0){
            extraDetail = " and "+otherCheckedCategories+" other checked categor";
            extraDetail += otherCheckedCategories > 1 ? "ies" : "y";
        }

        jQuery('#benefits-category-filter-details').html("Showing "+selectedLanguage+" categories"+extraDetail+". <button onclick='showAllCategories()'>Show all categories</button>");
    }else{
        jQuery('#edit-field-benefits-category').children().show();
        jQuery('#benefits-category-filter-details').html("Showing all categories. <button onclick='filterCategories()'>Filter categories by selected language</button>");
    }
}

function showAllCategories(){
    jQuery('#benefits-category-filter-details').attr('data-filtered', 'false');
    updateCategoryFilter(jQuery);
}

function filterCategories(){
    jQuery('#benefits-category-filter-details').attr('data-filtered', 'true');
    updateCategoryFilter(jQuery);
}