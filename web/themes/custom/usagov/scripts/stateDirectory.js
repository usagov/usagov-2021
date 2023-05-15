jQuery(document).ready(function ($) {
  "use strict";
  $("#statelist").after(
    '<label class="visuallyhidden">Select your state or territory:<select class="usa-select usa-sr-only usa-combo-box__select" name="state-info" id="stateselect" aria-hidden="true" tabindex="-1"></select></label>'
  );
  $("#statelist li a").each(function () {
    $("#stateselect").append(
      '<option value="' +
        $(this).attr("href") +
        '">' +
        $(this).text() +
        "</option>"
    );
  });
  $("#state-info").after(
    '<span class="usa-combo-box__clear-input__wrapper" tabindex="-1"><button type="button" class="usa-combo-box__clear-input" aria-label="Clear the select contents">&nbsp;</button></span><span class="usa-combo-box__input-button-separator">&nbsp;</span><span class="usa-combo-box__toggle-list__wrapper" tabindex="-1"><button type="button" tabindex="0" class="usa-combo-box__toggle-list" aria-label="Toggle the dropdown list">&nbsp;</button></span><ul tabindex="-1" id="state-info--list" class="usa-combo-box__list" role="listbox" aria-labelledby="state-info-label" hidden=""></ul><div class="usa-combo-box__status usa-sr-only" role="status"></div><span id="state-info--assistiveHint" class="usa-sr-only">When autocomplete results are available use up and down arrows to review and enter to select.Touch device users, explore by touch or with swipe gestures.</span>'
  );

  var b;
  if ($("html").attr("lang") === "en") {
    b = $('<button class="usa-button sd-go-btn" type="submit">Go</button>');
  }
 else {
    b = $('<button class="usa-button sd-go-btn" type="submit">Ir</button>');
  }

  $("#statelist").remove();
  $("#state-go").after(b);

  $('input[name="Alabama"]').val('Alabama');
  b.click(function() {
    let stateData = new FormData(stateForm);
    let stateValue = stateData.get('state-info');
    if (stateValue != null) {
      let stateName = stateValue.split("/")[2];
      dataLayer.push({
        'event': '50_state_submit',
        '50_state_url': stateValue,
        '50_state_name': stateName
      });
      window.location.assign(window.location.origin + stateValue);
    }
  });
});

