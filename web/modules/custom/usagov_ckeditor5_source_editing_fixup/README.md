Module: usagov_ckeditor5_source_editing_fixup

This module exists solely to remove a restriction on adding elements to the list of "Manually editable HTML tags" in the "Source editing" CKEditor 5 plugin. The problem is that if a plugin that is installed but not enabled for the current editor's configuration specifies an element as allowed, the Source Editing plugin won't let you add that element by itself -- an error message advises you to enable the plugin instead.

In our case, we want to allow editors to add divs with data attributes. The conflict is with the USWDS Grid plugin, which we've chosen not to enable -- it also allows divs with "data-*" (that is, any data- attribute). But we don't want the behavior of that plugin! And since it's supplied in a module that offers multiple plugins, we can't simply disable or uninstall it.

There's probably more than one active bug report about this, but this is the one I found, and specifically, the comment suggesting the workaround used here: https://www.drupal.org/project/drupal/issues/3410100#comment-15457015

