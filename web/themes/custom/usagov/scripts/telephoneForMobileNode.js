jQuery(document).ready(function ($) {
  if (window.innerWidth <= 950) {
    $(".field--type-telephone").replaceWith(function () {
      return $(
        "<a href='tel:" + $(this).html() + "'>" + $(this).html() + "</a>"
      );
    });
  }
});
