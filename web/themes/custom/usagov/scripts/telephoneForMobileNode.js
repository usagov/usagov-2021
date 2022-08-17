jQuery(document).ready(function ($) {
  console.log("being called");

  if (window.innerWidth <= 950) {
    $(".field--type-telephone").replaceWith(function () {
      function relaceNoCountry(num) {
        return num.replace(/(\d{3})(\d{3})(\d{4})/, "$1-$2-$3");
      }

      function replaceWithCountry(num) {
        return num.replace(/(\d{1})(\d{3})(\d{3})(\d{4})/, "+$1 $2-$3-$4");
      }
      // strip all non-numerical numbers to only link the actual phone number
      const numberAndDesc = $(this).text();
      const onlyPhoneNumber = numberAndDesc.replace(/\D/g, "");
      const onlyDesc = numberAndDesc.replace(/\d/g, "");
      console.log("onlyDesc" + onlyDesc);
      // if length is 10 there's no country code
      const formattedPhoneNumber =
        onlyPhoneNumber.length == 10
          ? relaceNoCountry(onlyPhoneNumber)
          : replaceWithCountry(onlyPhoneNumber);
      console.log("formattedPhoneNumber " + formattedPhoneNumber);
      return $(
        "<p><a href='tel:" +
          onlyPhoneNumber +
          "'>" +
          formattedPhoneNumber +
          "</a> " +
          onlyDesc +
          "</p>"
      );
    });
  }
  if (window.innerWidth > 950) {
    $('a[href^="tel:"]').replaceWith(function () {
      return $("<p> " + $(this).text() + " </p>");
    });
  }
});
