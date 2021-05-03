# Term Reference Fancytree

This module provides a hierarchical checkbox widget for term reference fields, based on the [fancytree javascript plugin](https://github.com/mar10/fancytree).

Its main use case is to provide a flexible lean implementation that can deal with extremely large taxonomies, using dynamic loading of tree levels.

## Editor UX? 

When you have large hierarchical taxonomies, the widgets provided by Drupal Core (select, checkboxes, autocomplete) lack considerably in terms of editor UX.

Imagine you need to select 100 terms in a hierarchy of a thousand terms. This means you would need to display 1000 terms in your edit page or have to add 100 autocomplete fields.

Term Reference Fancytree provides a clean hierarchical widget, that provides an accurate representation of the taxonomy tree, making it easier to manage your term references.

## Performance

By loading terms dynamically only when you expand tree levels you can have an unlimited number of terms in your taxonomy without impacting the performance of your edit page.

## Main features

This module provides the following features:

* Expandable checkbox tree widget to select terms in your form pages
* Support term references using multiple taxonomies
* Ajax dynamic loading for optimal performance
* Bulk selection/de-selection by double clicking term parents
* Full keyboard navigation and WAI-ARIA support for accessibility
* Quicksearch support to allow navigate to next node by typing the first letters

## Getting started

See the [How it works](how-it-works.md) section of the documentation.


## Resources

Contributing: https://github.com/duartegarin/term_reference_fancytree
Documentation: https://term-reference-fancytree.readthedocs.io

