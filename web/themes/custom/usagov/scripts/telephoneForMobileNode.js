jQuery(document).ready(function ($) {
  if (window.innerWidth <= 950) {
    $(".field--type-telephone").replaceWith(function () {
      // using same delimiter as mothership (" ") split number and description
      // unable to
      const numberSplitter = $(this).text().split(" ");
      const cleanNumber = numberSplitter[0].replace(/\D/g, "");
      const onlyDesc = numberSplitter.slice(1, numberSplitter.length).join(" ");

      if (cleanNumber.length == 10 || cleanNumber.length == 11) {
        return $(
          "<p><a href='tel:" +
            cleanNumber +
            "'>" +
            numberSplitter[0] +
            "</a> " +
            onlyDesc +
            "</p>"
        );
      } else {
        // split using " " didn't work so return unformatted number
        return $("<p> " + $(this).text() + " </p>");
      }
    });
  }
  //needed because default is for field to make into a link
  if (window.innerWidth > 950) {
    $('a[href^="tel:"]').replaceWith(function () {
      return $("<p> " + $(this).text() + " </p>");
    });
  }
});
