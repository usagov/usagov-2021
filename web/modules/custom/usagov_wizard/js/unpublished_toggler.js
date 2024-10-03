// Toggle to only show unpublished wizard terms.
(function($, Drupal) {
  "use strict";
  Drupal.behaviors.unpublishedToggler = {
    "attach": function (context, settings) {
      var toggleUnpublishedButton = $('#edit-unpublished-toggler', context);
      toggleUnpublishedButton.on('click', function() {
        $(context).find(".jstree-node:not(.hm-tree-node-disabled)").toggle();
      });

    }
  };
})(jQuery, Drupal);
