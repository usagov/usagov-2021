jQuery(document).ready(async function () {
  "use strict";

  var waittime = (function() {
    if (jQuery("html[lang|='en']").length ||
        jQuery("html[lang|='es']").length) {
      jQuery.ajax({
        "url": "https://s3-us-gov-west-1.amazonaws.com/cg-4d6fb302-315f-403e-a96e-a7563ccddd3d/1.0/waittime.json",
        "type": "GET",
        "success": function (response) {
          var json = jQuery.parseJSON(response);
          var seconds = -1;

          if (jQuery("html[lang|='en']").length) {
            seconds = json.enEstimatedWaitTimeSeconds;
          }
          if (jQuery("html[lang|='es']").length) {
            seconds = json.spEstimatedWaitTimeSeconds;
          }

          if (seconds >= 0) {
            var minutes = Math.floor(seconds / 60);
            var remainingMinutes = minutes % 60;
            seconds = seconds - (minutes * 60);
            var content = "Estimated wait time: ";
            var secondsText = " second";
            var minuteText = " minute";
            var noneText = "None";

            if (jQuery("html[lang|='es']").length) {
              content = "Tiempo de espera estimado: ";
              secondsText = " segundo";
              minuteText = " minuto";
              noneText = "Ninguno";
            }

            if (remainingMinutes > 0) {
              content += remainingMinutes + minuteText;
              if (remainingMinutes > 1) {
                content += "s";
              }
            }

            if (seconds > 0) {
              if (remainingMinutes > 0) {
                content += ", ";
              }
              content += seconds + secondsText;
              if (seconds > 1) {
                content += "s";
              }
            }

            if (minutes === 0 && seconds === 0) {
              content = content + noneText;
            }

            var timeOfEstimate = json.timestamp;

            // If the estimated time was captured over 10 minutes ago, remain silent.
            if (Date.now()/1000 - json.timestamp > 600) {
              content = "";
            }

            jQuery('#callCenterTime').html(content);
          }
        },
        "error": function (xhr, status, error) {
          console.log('fail');
          console.log(error);
          jQuery('#callCenterTime').html(error);
        }
      });
    }
  });

  if (jQuery('#callCenterTime').length > 0) {
    waittime();
    setInterval(function() {
      waittime();
    }, 60 * 1000);
  }
});