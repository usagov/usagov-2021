# USAGOV WIZARD

## Purpose
Hooks and service to improve Wizard management and customize Wizard display.

### Hooks

* form_alter() - Adds a "view term" button to the modal edit screen
in the wizard manager
* theme_suggestions_page_alter() - Adds a theme suggestions for page templates,
breadcrumbs, and regions based on vocabulary.
* preprocess_page() - Uses the MenuChecker service to put together a left hand
navigation menu that is based on the location of the parent taxonomy in the
menu.
* preprocess_html() - Sends the text from the header_html field to the <head>.

### MenuChecker Service

 This service returns information about menu entities. If used in conjunction
 with preprocess hooks, it can help generate a custom left navigation menu
 comprised of a specific section of a menu. Currently only works for a taxonomy
 term page.

 By getting an entity from the current route and passing it into
 getTermParents(), we can get an array of taxonomy term ids that are parents of
 the current entity.

 Then, by passing in the machine name of a menu into getMenuEntities(), we can
 get an array of taxonomy term ids in the menu, provided they meet certain
 conditions. We can also get some information about these entities. We can then
 get the parent of these entities, and load all the children.

 Then, once we have this data, we can check back in usagov_wizard.module
 in our preprocess hook to see if there is a match between the ids obtained by
 both methods. If there is, then we are on a taxonomy term page which is either
 linked to, or has a parent that is linked to, in the menu. And this link in
 the menu has a custom field boolean set to true, meaning it is intended to be
 used as an anchor to display this section of the menu in the left navigation.

 Finally, still in our preprocess hook in usagov_wizard.module,
 we can pass the entity information onto our twig template for display.
