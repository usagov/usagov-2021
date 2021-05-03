/**
 * @file
 * Attaches entity-type selection behaviors to the widget form.
 */

(function (Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.dynamicEntityReferenceWidget = {
    attach: function (context) {
      function dynamicEntityReferenceWidgetSelect(e) {
        var selectElement = e.currentTarget;
        // @todo Replace this loop with
        //    container = selectElement.closest('.container-inline'); when core
        //    drops IE support.
        var container = selectElement;
        do {
          container = container.parentElement || container.parentNode;
        } while (!container.getAttribute('data-drupal-selector'));
        var autocomplete = container.querySelector('.form-autocomplete');
        autocomplete.value = '';
        var entityTypeId = selectElement.value;
        autocomplete.dataset['autocompletePath'] = drupalSettings.dynamic_entity_reference[selectElement.dataset['dynamicEntityReference']][entityTypeId];
        Drupal.autocomplete.cache[autocomplete.id] = {};
      }
      Object.keys(drupalSettings.dynamic_entity_reference || {}).forEach(function(field_class) {
        var field = context.querySelector('.' + field_class);
        if (field && !field.classList.contains(field_class + '-processed')) {
          field.classList.add(field_class + '-processed');
          field.addEventListener('change', dynamicEntityReferenceWidgetSelect);
        }
      });
    }
  };
})(Drupal, drupalSettings);
