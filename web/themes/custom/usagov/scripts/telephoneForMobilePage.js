(function ($) {
  if (window.innerWidth <= 950) {
    $(".field--type-telephone").replaceWith(function () {
      return $(
        "<a href='tel:" + $(this).html() + "'>" + $(this).html() + "</a>"
      );
    });
  }

  if (window.innerWidth >= 950) {
    $('a[href^="tel:"]').replaceWith(function () {
      return $('<span class="num">' + $(this).html() + "</span>");
    });
  }
})(jQuery);
