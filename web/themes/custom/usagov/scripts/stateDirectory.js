jQuery(document).ready(function ($) {
  "use strict";
  var docLang = [document.documentElement.lang];

  // hidden label for a11y
  const hiddenLabel = docLang === "es" ? "Elija o escriba el estado o territorio:" : "Select or type your state or territory:";

  $("#comboBoxDiv").append(
    `<label class="visuallyhidden">${hiddenLabel}:<select class="usa-select usa-sr-only usa-combo-box__select" name="state-info" id="stateselect" aria-hidden="true" tabindex="-1"></select></label>`
  );

  // add an empty option so that it does not default to first choice if user does not make a selection in drop down
  $("#stateselect").append(
    '<option value=""></option>'
  );

  // add the states to the options
  $("#statelist li a").each(function () {
    $("#stateselect").append(
      '<option key="' + $(this).attr("key") +'"' +
      ' value="' +
        $(this).attr("href") +
        '">' +
        $(this).text() +
        "</option>"
    );
  });

  // input for select options
  $("#comboBoxDiv").append(
    '<input id="state-info" aria-autocomplete="list" aria-expanded="false" autocapitalize="off" autocomplete="off" class="usa-combo-box__input" type="text" aria-labelledby="state-info-label" role="combobox" data-test="stateInput" required>'
  );

  // options for combobox
  $("#state-info").after(
    '</span><span class="usa-combo-box__input-button-separator">&nbsp;</span><span class="usa-combo-box__toggle-list__wrapper" tabindex="-1"><button type="button" tabindex="0" class="usa-combo-box__toggle-list" aria-label="Toggle the dropdown list">&nbsp;</button></span><ul tabindex="-1" id="state-info--list" class="usa-combo-box__list" role="listbox" aria-labelledby="state-info-label" data-test="stateDropDown" hidden=""></ul><div class="usa-combo-box__status usa-sr-only" role="status"></div><span id="state-info--assistiveHint" class="usa-sr-only">When autocomplete results are available use up and down arrows to review and enter to select.Touch device users, explore by touch or with swipe gestures.</span>'
  );

  // add the submit button
  const sumBtn = docLang === "es" ? "Ir" : "Go";
  $("#submitAfter").append(
    `<button class="usa-button sd-go-btn usa-button--secondary" type="submit">${sumBtn}</button>`
  );
  var goButton = $(".sd-go-btn");

  // remove the non-js list version
  $("#statelist").remove();

  // submission
  var url=$('#stateselect').val();
  var statename;

  goButton.click(function() {
    let stateData = new FormData(stateForm);
    let stateIsEmpty = $.isEmptyObject(stateData.get('state-info'));
    if (!stateIsEmpty) {
      window.location.href = url;
      dataLayer.push({
        'event': '50_state_submit',
        '50_state_url': url,
        '50_state_name': statename
      });
    }
  });

  $('#stateselect').on('change', function() {
    url=$(this).val();
    statename=$('#stateselect option:selected').text();
  });
});
