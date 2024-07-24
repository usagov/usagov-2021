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
* Option Name: This is the name that will show up when you are using the hierarchical tree-view within the Wizard Manager interface
* Description: This field is for additional text that you want displayed below the option in the option list. 
* Heading/Question: This is the heading that will display at the top of your wizard question, in large red text. 
* Language Toggle: Sets a translation for the current page and the translation goes into the language dropdown in the top right
* Intro: This field is a WYSIWYG text for below the Header/Question but above the options list and Body.
* Body: This field is for text that you want displayed above the options list and below the introduction. Optional
* Footer: This field is for text that you want displayed below the options list. Optional
* Header HTML: This is optional HTML field for content that will go at the top of the page, above all other things on the page. This is where analytics team can put in gtags and other scripts.
* Relations: This drop down is completely optional but allows you to set the parent of the term you are creating at initial creation. This is optional as the benefit of the hierarchical tree view is that after you create some of these terms for your wizard, you will be able to drag and drop them into the specified parent, as well as specific order so that you do not need to manually set the parent or weight.
* Published: Do you want this wizard published or not
* URL Alias: Specify an alternative path which the data can be accessed. 

## How to use the hierarchical tree view

From the Wizard Manager, you can collapse, uncollapse, drag & drop, reorder and edit taxonomy terms from a single page. 

