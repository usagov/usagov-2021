jQuery(document).ready(function ($) {
  if (window.innerWidth <= 950) {
    $(".field--type-telephone").replaceWith(function () {
      function relaceNoCountry(num) {
        return num.replace(/(\d{3})(\d{3})(\d{4})/, "$1-$2-$3");
      }

      function replaceWithCountry(num) {
        return num.replace(/(\d{1})(\d{3})(\d{3})(\d{4})/, "+$1 $2-$3-$4");
      }
      // strip all non-numerical numbers to only link the actual phone number
      const numberSplitter = $(this).text().split(" ");
      const cleanNumber = numberSplitter[0].replace(/\D/g, "");
      const onlyDesc = numberSplitter.slice(1, numberSplitter.length).join(" ");

      // if length is 10 there's an area code, else there is not
      const formattedPhoneNumber =
        cleanNumber.length == 10
          ? relaceNoCountry(cleanNumber)
          : replaceWithCountry(cleanNumber);

      return $(
        "<p><a href='tel:" +
          cleanNumber +
          "'>" +
          formattedPhoneNumber +
          "</a> " +
          onlyDesc +
          "</p>"
      );
    });
  }
  //needed because default is for field to make into a link
  if (window.innerWidth > 950) {
    $('a[href^="tel:"]').replaceWith(function () {
      return $("<p> " + $(this).text() + " </p>");
    });
  }
});
