jQuery(document).ready(function ($) {
  "use strict";
  // {# For the data-filter =
  //   "{{ '{{' }}query}}.*| -> suggest states that start with the query
  //   .*\s{{ '{{' }}query}}.*| -> suggest states that start with the query
  //   .*\({{ '{{' }}query}}.*" -> suggest states with an abbreviation that matches the query #}
  var comboBoxDiv =`<div id="comboBoxDiv" class="usa-combo-box width-full mobile-lg:width-mobile" data-filter="{{ '{{' }}query}}.*|.*\\s{{ '{{' }}query}}.*|.*\\({{ '{{' }}query}}.*">`;

  $("#comboBoxAfter").append(comboBoxDiv);

  // hidden label for a11y
  $("#comboBoxDiv").append(
    '<label class="visuallyhidden">Select or type your state or territory:<select class="usa-select usa-sr-only usa-combo-box__select" name="state-info" id="stateselect" aria-hidden="true" tabindex="-1"></select></label>'
  );

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
    '<input id="state-info" aria-autocomplete="list" aria-expanded="false" autocapitalize="off" autocomplete="off" class="usa-combo-box__input" type="text" aria-labelledby="state-info-label" role="combobox" required>'
  );

  // options for combobox
  $("#state-info").after(
    '<span class="usa-combo-box__clear-input__wrapper" tabindex="-1"><button type="button" class="usa-combo-box__clear-input" aria-label="Clear the select contents">&nbsp;</button></span><span class="usa-combo-box__input-button-separator">&nbsp;</span><span class="usa-combo-box__toggle-list__wrapper" tabindex="-1"><button type="button" tabindex="0" class="usa-combo-box__toggle-list" aria-label="Toggle the dropdown list">&nbsp;</button></span><ul tabindex="-1" id="state-info--list" class="usa-combo-box__list" role="listbox" aria-labelledby="state-info-label" hidden=""></ul><div class="usa-combo-box__status usa-sr-only" role="status"></div><span id="state-info--assistiveHint" class="usa-sr-only">When autocomplete results are available use up and down arrows to review and enter to select.Touch device users, explore by touch or with swipe gestures.</span>'
  );

  var docLang = [document.documentElement.lang];
  const sumBtn = docLang == "es" ? "Ir" : "Go";
  $("#submitAfter").append(
    `<button class="usa-button sd-go-btn usa-button--secondary" type="submit">${sumBtn}</button>`
  );
  var goButton = $(".sd-go-btn");

  $("#statelist").remove();

  var url=$('#stateselect').val();
  var statename;

  goButton.click(function() {
    let stateData = new FormData(stateForm);
    let stateValue = stateData.get('state-info');
    if (stateValue != null) {
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
