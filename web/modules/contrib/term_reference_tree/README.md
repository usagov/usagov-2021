# Taxonomy Term Reference Tree Widget

## Gitlab

This project is no longer supported on Gitlab.
Please head to [drupal.org](https://www.drupal.org/project/term_reference_tree) for the latest version.

## Summary

This module provides an expandable tree widget for the Taxonomy Term Reference
field in Drupal 7. This widget is intended to serve as a replacement for 
Drupal's core Taxonomy Term Reference widget, which is a flat list of radio 
buttons or checkboxes and not necessarily fit for medium to large taxonomy trees.

This widget has the following features:

- Expand/minimize buttons
- Fully theme-able
- Filter and sort available options with a view (if views is installed)
- The ability to start with the tree either minimized or maximized
- If you limit the number of selectable options, client-side javascript 
    limits the number of terms that can be selected by disabling the other 
    remaining options when the limit has been reached (this is enforced on 
    the server side too).
- For large trees, this widget now optionally keeps a list of selected items 
    below the tree.
- You can use tokens to alter the widget label (good for adding icons, turning 
    the options into links, etc).
  