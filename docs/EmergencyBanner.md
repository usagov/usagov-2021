
# Steps to add an emergency banner:
* Select `Structure -> Block Layout` from the Drupal menu
* Click the `Place Block` button next to `Above Header` (A modal will appear)
* Find the `Emergency Header Banner block` (you can filter by typing in the text field above the list)
* Click `Place Block` (A new modal will appear)
* Deselect `Display Title`
* If you plan to create a separate Spanish banner
    * Click `Language`
    * Select `English`
* Click the `Save Block` button (The Block Layout page will load)
* Drag the `Emergency Header Banner` block above the Language Switcher block within `Above Header`
* Click the `Save Blocks` button
* Select `Structure -> Block Layout -> Custom Block Library` from the Drupal menu
* Click `Emergency Header Banner`
* Deselect `Slim`
* Select `Warning` in the `Alert Status` field
* If you want to add text to the `Alert Title`, it will be displayed as a h2 heading
* Add the emergency message to the `Alert Body` field
    * You can use some HTML to add things like links or bold text
* Click `Save`
* Visit a page to see the emergency banner.
 


# Steps to add another emergency banner (for Spanish pages):
* Select `Structure -> Block Layout -> Add Custom Block` from the Drupal Menu
* Click `USWDS Paragraphs`
* Provide a `Block Description` like "Emergency Header Banner Spanish"
* Click the arrow on the dropdown under `USWDS Paragraph Bundles`
* Select `Add USWDS Alert` (A series of fields will load)
* Fill the fields similar to the english emergency banner
* Click `Save` (The configure block page will load)
* Deselect `Display Title`
* Click `Language`
* Select `Spanish`
* Select `Above Header` in the `Region` dropdown
* Click the `Save Block` button (The block layout page will load)
* Drag Emergency Header Banner block above the Language Switcher block within `Above Header`
* Click the `Save Blocks` button
* Visit a Spanish page to see the Spanish emergency banner.


# Making changes after creating a banner
To change the content and design of a banner:

* Visit a page that shows the banner you want to change
* Hover over the banner and click the pencil icon that appears
* Select `Edit`

To change other block configuration:

* Visit a page that shows the banner you want to change
* Hover over the banner and click the pencil icon that appears
* Select `Configure block`

To change the position of the emergency banner, select `Structure -> Block Layout` from the Drupal menu, and drag the emergency banner block to another position.


# Design Choices
These steps mention using the Warning status. This adds a yellow background and a triangle  icon. There are other options but I dont think any of them look right for an emergency banner.

The `Slim` option makes the banner more compact, but on larger screens the icon gets separated from the content. So I dont recommend using slim if you're including the icon.

Adding an `Alert Title` makes the banner much taller. If you wish to keep the banner small, add some \<strong> text in the `Alert Body` field instead.

Following the steps above would position the emergency banner below the government banner. This positioning can be adjusted via the `Block Layout` page, by dragging the Emergency Banner blocks above or below the English banner code and Spanish banner code blocks. I recommend keeping the emergency banners above the Language Toggle blocks because otherwise they react strangely with the language toggle on some screen sizes.

