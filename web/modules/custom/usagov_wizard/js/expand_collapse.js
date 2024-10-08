// Expand/collapse all terms in the wizard overview screen.
(function($, Drupal) {
  "use strict";
  Drupal.behaviors.expand_collapse = {
    "attach": function (context, settings) {
      var expandCollapseButton = $('#edit-expand-collapse-all', context);
      var open= false;
      expandCollapseButton.on('click', function() {
        if (open !== true) {
          $("#hm-jstree-taxonomy_overview_terms").jstree("open_all");
          open = true;
        }
        else {
          $("#hm-jstree-taxonomy_overview_terms").jstree("close_all");
          open = false;
        }

      });

    }
  };
})(jQuery, Drupal);
