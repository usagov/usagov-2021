/**
 * @file
 * Provides Swagger integration.
 */

(function ($, Drupal, drupalSettings) {


  // SwaggerUI expects $ to be defined as the jQuery object.
  // @todo File a patch to Swagger UI to not require this.
  window.$ = $;

  /**
   * Attach a behavior to initialize the swagger ui.
   *
   * The behavior finds the element with the id `swagger-ui` and initializes
   * the library using that div.
   *
   * @TODO: Use a dynamic or calculated id, to allow for multiple instances of
   * the UI to be rendered on the same page.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to initilize the swagger-ui.
   */
  Drupal.behaviors.swaggerui = {
    attach: function (context, settings) {
      /**
       * Define a swagger ui plugin to remove the top bar from the swagger ui.
       */
      function SwaggerUIHideTopbarPlugin() {
        return {
          components: {
            Topbar: function() { return null }
          }
        }
      }
      var dom_id = '#swagger-ui';
      var $container = $(dom_id);
      var config = {
        dom_id: dom_id,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl,
          SwaggerUIHideTopbarPlugin
        ],
        layout: "StandaloneLayout"
      }
      var url = $container.data('openapi-ui-url');
      if (url === undefined) {
        config.spec = $container.data('openapi-ui-spec');
      }
      else {
        config.url = url;
      }
      // Build a display.
      const ui = SwaggerUIBundle(config);

      window.ui = ui;


    }
  };

})(jQuery, Drupal, drupalSettings);
