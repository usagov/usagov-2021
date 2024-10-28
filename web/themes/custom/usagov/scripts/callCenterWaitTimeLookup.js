jQuery(document).ready(async function () {
  "use strict";

  function checkPagePath() {
    if (
      window.location.pathname === "/phone" ||
      window.location.pathname === "/es/llamenos"
    ) {
      return true;
    }
  }

  function getCallCenterWaitTime() {
    var jsonSeconds;
    var jsonTimestamp;
    jQuery.ajax({
      "url": "https://s3-us-gov-west-1.amazonaws.com/cg-4d6fb302-315f-403e-a96e-a7563ccddd3d/1.0/waittime.json",
      "type": "GET",
      "dataType": "json",
      "success": function (response) {
        if (jQuery("html[lang|='en']").length) {
          jsonSeconds = response.call.estimatedWaitTimeSeconds.en;
        }
        if (jQuery("html[lang|='es']").length) {
          jsonSeconds = response.call.estimatedWaitTimeSeconds.sp;
        }

        jsonTimestamp = response.timestamp;
        createDisplayWaitTime(jsonSeconds, jsonTimestamp);
      },
      "error": function (xhr, status, error) {
        console.log(error);
      },
    });
  }

  function checkTimeStamp(timestamp) {
    var timeOfEstimate = timestamp ? timestamp : 0;
    if (Date.now() / 1000 - timeOfEstimate < 600) {
      return true;
    }
  }

  function createDisplayWaitTime(actualSeconds, timestamp) {
    // If the estimated time was captured over 10 minutes ago, remain silent.
    if (checkTimeStamp(timestamp)) {
      var displayTime;
      if (actualSeconds != -1) {
        if (actualSeconds < 60) {
          displayTime = 1;
        }
        else {
          displayTime = Math.round(actualSeconds / 60);
        }
        displayWaitTime(displayTime);
      }
    }
  }

  function displayWaitTime(displayTime) {
    var docLang = [document.documentElement.lang];

    var plural = displayTime > 1;
    const plurals = plural ? "s" : "";

    const displayText =
      docLang[0] === "es"
        ? `El tiempo aproximado de espera es <strong>${displayTime} minuto${plurals}</strong>.`
        : `The current estimated wait time is <strong>${displayTime} minute${plurals}</strong>.`;

    jQuery("#block-usagov-content").append(
      `<div class="paragraph paragraph--type--uswds-alert paragraph--view-mode--embed paragraph--id--2485 usa-alert usa-alert--slim usa-alert--no-icon"><div class="usa-alert__body"> <div class="field field--name-field-alert-body field--type-text field--label-hidden field__item">${displayText}</div> </div> </div>`
    );
  }

  // upgrade: only apply to phone pages using Drupal library in usagov.libraries.yml
  if (checkPagePath()) {
    getCallCenterWaitTime();
  }
});
