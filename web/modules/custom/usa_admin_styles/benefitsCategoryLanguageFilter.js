/*
Terms in the "Benefits Category" taxonomy can be English or Español, and
the "Benefits Category Search" field displayed while editing a Basic Page
content type shows all the items by default.

This script filters those categories by the content's selected language.

This script assumes that each category includes its language at the end of the <label> element
*/

(function (jQuery, Drupal, drupalSettings) {

    storeLanguageInDataAttribute(jQuery);

    filterCategoriesByLanguage(jQuery);

    jQuery('#edit-langcode-0-value').change(function(){
        filterCategoriesByLanguage(jQuery);
    });

})(jQuery);

/*
Each Benefits Category is rendered with text that ends with its language.
The storeLanguageInDataAttribute function adds the languageas a data attribute,
and then strips away the visible language text.

It would be more ideal to let the View render the language into the data attribute,
but when the View is displayed as a field on an admin page, it seems to be limited
to showing visible text.
*/
function storeLanguageInDataAttribute(jQuery){
    jQuery('#edit-field-benefits-category').children().each(function () {
        if(jQuery(this).text().trim().endsWith("English")){
            jQuery(this).attr( "data-language", "English" );
        }
        if(jQuery(this).text().trim().endsWith("Español")){
            jQuery(this).attr( "data-language", "Español" );
        }
        let label = jQuery(this).children("label");
        label.html(label.children("span")[0].outerHTML);
    });
}

/*
The filterCategoriesByLanguage function filters the Benefits Categories 
by the currently selected language. It also updates the text within the 
<legend> element to indicate that the Benefits Categories are being filtered
as well as which language is currently selected.

It is called when the edit page loads as well as anytime the language field
is changed.
*/
function filterCategoriesByLanguage(jQuery){
    let selectedLanguage = jQuery("#edit-langcode-0-value :selected").text();
    jQuery('#edit-field-benefits-category').children().each(function () {
        if(jQuery(this).attr( "data-language" ) != selectedLanguage){
            jQuery(this).hide();
        }else{
            jQuery(this).show();
        }
    });
    jQuery('#edit-field-benefits-category--wrapper legend span').text("Benefits Category (Filtered by selected language: "+selectedLanguage+")");
}