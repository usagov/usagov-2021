jQuery(document).ready(function () {
  "use strict";

  jQuery('a.usa-button[role="button"]').on('keydown', function(ev) {
    if (ev.originalEvent.code === "Space") {
      this.click();
      ev.preventDefault();
    }
  });
});
