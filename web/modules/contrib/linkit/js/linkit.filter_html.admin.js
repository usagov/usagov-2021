/**
 * @file
 * Send events to add or remove a tags to the filter_html allowed tags.
 */

(function ($, Drupal, document) {

  'use strict';

  /**
   * When enabling the linkit filter, also add linkit rules to filter_html.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches linkitFilterHtml behavior.
   */
  Drupal.behaviors.linkitFilterHtml = {
    attach: function (context) {
      var selector = '[data-drupal-selector="edit-filters-linkit-status"]';
      var feature = editorFeature();
      $(context).find(selector).once('filters-linkit-status').each(function () {
        $(this).on('click', function () {
          var eventName = $(this).is(':checked') ? 'drupalEditorFeatureAdded' : 'drupalEditorFeatureRemoved';
          $(document).trigger(eventName, feature);
        });
      });
    }
  };

  /**
   * Returns a editor feature.
   *
   * @return {Drupal.EditorFeature}
   *   A editor feature with linkit specific tags and attributes.
   */
  function editorFeature() {
    var linkitFeature = new Drupal.EditorFeature('linkit');
    var rule = new Drupal.EditorFeatureHTMLRule();
    // Tags.
    rule.required.tags = ['a'];
    rule.allowed.tags = ['a'];
    // Attributes.
    rule.required.attributes = ['data-entity-substitution', 'data-entity-type', 'data-entity-uuid', 'title'];
    rule.allowed.attributes = ['data-entity-substitution', 'data-entity-type', 'data-entity-uuid', 'title'];

    linkitFeature.addHTMLRule(rule);
    return linkitFeature;
  }

})(jQuery, Drupal, document);
