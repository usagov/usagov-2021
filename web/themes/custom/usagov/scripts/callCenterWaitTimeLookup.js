jQuery(document).ready(async function () {
  "use strict";

  var waittime = (function() {
    jQuery.ajax({
      "url": "/wait-time",
      "type": "GET",
      "success": function (response) {
        var json = jQuery.parseJSON(response);
        var seconds = false;
        var content = "";

        if (jQuery("html[lang|='en']").length) {
          seconds = json.enEstimatedWaitTimeSeconds;
          content = "Estimated wait time: " + seconds + " seconds";
        }
        if (jQuery("html[lang|='es']").length) {
          seconds = json.spEstimatedWaitTimeSeconds;
          content = "Tiempo de espera estimado: " + seconds + " segundos";
        }

        if (seconds >= 0) {
          jQuery('#callCenterTime').html(content);
        }
      },
      "error": function (xhr, status, error) {
        console.log('fail');
        console.log(error);
        jQuery('#callCenterTime').html(error);
      }
    });
  });

  if (jQuery('#callCenterTime').length > 0) {
    waittime();
    setInterval(function() {
      waittime();
    }, 60 * 1000);
  }
});