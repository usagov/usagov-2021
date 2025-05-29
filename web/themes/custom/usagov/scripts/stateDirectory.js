/**
 * ⚠️ NOTICE:
 *
 * This script is included and executed while the page loads. It executes before jQuery is loaded.
 * Hence this script in intentionally now written in a way that does not utilize jQuery.
 * **/

(function () {
  'use strict';

  const docLang = String(document.documentElement.lang);

  // hidden label for a11y
  const hiddenLabel = docLang === "es"
    ? "Elija o escriba el estado o territorio:"
    : "Select or type your state or territory:";

  const comboBoxDiv = document.getElementById("comboBoxDiv");

  // Create label and select
  const label = document.createElement("label");
  label.className = "visuallyhidden";
  label.innerHTML = `${hiddenLabel}:<select class="usa-select usa-sr-only usa-combo-box__select" name="state-info" id="stateselect" aria-hidden="true" tabindex="-1"></select>`;
  comboBoxDiv.appendChild(label);

  // Add empty option
  const select = document.getElementById("stateselect");
  const emptyOption = document.createElement("option");
  emptyOption.value = "";
  select.appendChild(emptyOption);

  // Add states to the select options
  const stateListLinks = document.querySelectorAll("#statelist li a");
  stateListLinks.forEach(link => {
    const option = document.createElement("option");
    option.setAttribute("key", link.getAttribute("key"));
    option.value = link.getAttribute("href");
    option.textContent = link.textContent;
    select.appendChild(option);
  });

  // Create the input for the combobox
  const input = document.createElement("input");
  input.id = "state-info";
  input.setAttribute("aria-autocomplete", "list");
  input.setAttribute("aria-expanded", "false");
  input.setAttribute("autocapitalize", "off");
  input.setAttribute("autocomplete", "off");
  input.className = "usa-combo-box__input";
  input.type = "text";
  input.setAttribute("aria-labelledby", "state-info-label");
  input.setAttribute("role", "combobox");
  input.setAttribute("data-test", "stateInput");
  input.required = true;

  comboBoxDiv.appendChild(input);

  // Add combobox UI elements after input
  input.insertAdjacentHTML("afterend", `
  </span><span class="usa-combo-box__input-button-separator">&nbsp;</span>
  <span class="usa-combo-box__toggle-list__wrapper" tabindex="-1">
    <button type="button" tabindex="0" class="usa-combo-box__toggle-list" aria-label="Toggle the dropdown list">&nbsp;</button>
  </span>
  <ul tabindex="-1" id="state-info--list" class="usa-combo-box__list" role="listbox" aria-labelledby="state-info-label" data-test="stateDropDown" hidden=""></ul>
  <div class="usa-combo-box__status usa-sr-only" role="status"></div>
  <span id="state-info--assistiveHint" class="usa-sr-only">
    When autocomplete results are available use up and down arrows to review and enter to select.
    Touch device users, explore by touch or with swipe gestures.
  </span>`);

  // Add the submit button
  const sumBtn = docLang === "es" ? "Ir" : "Go";
  const submitAfter = document.getElementById("submitAfter");

  const button = document.createElement("button");
  button.className = "usa-button sd-go-btn";
  button.type = "submit";
  button.textContent = sumBtn;

  submitAfter.appendChild(button);

  const stateList = document.getElementById("statelist");
  if (stateList) stateList.remove();

  let statename;

  button.addEventListener("click", function () {
    // const stateForm = document.forms[0]; // Assuming the form is the first one on the page
    // const stateData = new FormData(stateForm);
    // const stateValue = stateData.get('state-info');

    if (String(select.value) !== "") {
      const allowedUrls = Array.from(document.querySelectorAll("#comboBoxDiv select option")).map(option => option.value).filter(value => value.trim() !== "");
      if (String(select.value) !== "" && allowedUrls.includes(select.value)) {
        window.location = select.value;
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
          'event': '50_state_submit',
          '50_state_url': url,
          '50_state_name': statename
        });
      }
    }
  });

  select.addEventListener("change", function () {
    url = this.value;
    statename = this.options[this.selectedIndex].text;
  });

})();

