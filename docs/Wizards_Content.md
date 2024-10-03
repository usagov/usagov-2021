# Wizard Content Management Guide

Wizard Management is currently under development.

## How to access the management interface

The Wizard Manager interface can be accessed by using the USAGov tools shortcut on the Drupal Menu Bar, and selecting Wizard Manager.

## Approach

The Wizard Manager allows you to manage wizards in a hierarchical tree view. Wizards are comprised of taxonomy terms that are related to each other in a parent-child relationship. 

## How to create a wizard

In order to create a new wizard, go to the Wizard Manager and press the button "Add term". This step will be used repeatedly in order to create a new wizard and new sub-terms and options of that header wizard. 

On the Add term page, you will be asked and need to fill out the following information:

* Name: Drupal's name for the taxonomy term
* Language: Language
* Option Name: This field is for naming the option within the options list for the term you are creating. 
* Description: This field is for additional text that you want displayed below the option in the option list. 
* Heading/Question: This is the heading that will display at the top of your wizard question, in large red text. 
* Language Toggle: Sets a translation for the current page and the translation is linked to in the language switch link in the top right.
* Intro: This field is a WYSIWYG text for below the Header/Question but above the options list and Body. Optional.
* Body: This field is for text that you want displayed above the options list and below the introduction. Optional
* Footer: This field is for text that you want displayed below the options list. Optional
* Header HTML: This is optional HTML field for content that will go at the top of the page, above all other things on the page. This is just for `<scripts>` that go in the `<head>`. Content managers should not use unless they know what they are doing.
* Relations: This drop down is completely optional but allows you to set the parent of the term you are creating at initial creation. This is optional as the benefit of the hierarchical tree view is that after you create some of these terms for your wizard, you will be able to drag and drop them into the specified parent, as well as specific order so that you do not need to manually set the parent or weight.
* Published: Do you want this wizard published or not
* URL Alias: Specify the path at which the page can be accessed, e.g. "/learn-where-report-scam" 

## How to use the hierarchical tree view

From the Wizard Manager, you can collapse, uncollapse, drag & drop, reorder, search by name, and edit taxonomy terms from a single page. 

## How to add left hand nav menu

After you create a wizard, a left nav menu will not be enabled by default. In order to add a left nav menu, go to Structure > Menus > Left Menu English. If you are creating a new left nav menu link, then you will add a new link. Otherwise, search for the link you are looking to connect up with left nav and add the URL to the "Link" field. 

If adding a whole new link, on the Add menu link page, enter the following information:

* Menu link title: Title for the menu link
* Link: The URL of your top level wizard, e.g. "/learn-where-report-scam"
* Enabled: Checked
* Description: Text that will be displayed when hovering over the menu link.
* Custom Parent: Checked
* Show as expanded: Unchecked
* Language: Language
* Weight: 0
* Parent link: This field is a dropdown showing all menu items and their parents, and should select the parent of the links you are looking to display in the menu. 

## How to add breadcrumb

As part of the wizard creation process, you will have the opportunity to configure breadcrumbs for the wizard. In the header level of the wizard, you can configure a breadcrumb trail by pressing the "Add another item" button under the Breadcrumb header. Breadcrumbs are displayed in order of Top->Bottom:Left->Right per the order within the wizard manager editing page, and can be re-ordered using the drag and drop handles.  Note: Do not set the homepage as a link, this is already assumed and configured by default. 

* URL: The URL of the breadcrumb link (This must be an internal path such as /node/add. You can also start typing the title of a piece of content to select it. Enter `<front>` to link to the front page. Enter `<nolink>` to display link text only. Enter `<button>`to display keyboard-accessible link text only.)
* Link Text: The text that the breadcrumb will display