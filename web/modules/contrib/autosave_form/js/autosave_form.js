/**
 * @file
 * Defines Javascript behaviors for the autosave_form module.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Define defaults.
   */
  Drupal.autosaveForm = {
    timer: null,
    interval: null,
    autosaveFormRunning: false,
    autosaveFormInstances: {},
    initialized: false,
    formHasErrors: false,
    message: '',
    dialog_options: [],
    autosave_submit_class: 'autosave-form-save',
    autosave_restore_class: 'autosave-form-restore',
    autosave_reject_class: 'autosave-form-reject',
    notification: {
      active: true,
      message: Drupal.t('Saving draft...'),
      delay: 1000
    }
  };

  /**
   * Add a variable which determines if the window is being unloaded.
   */
  Drupal.autosaveForm.beforeUnloadCalled = false;
  $(window).on('beforeunload pagehide', function () {
    Drupal.autosaveForm.beforeUnloadCalled = true;
  });

  /**
   * Default dialog options.
   */
  Drupal.autosaveForm.defaultDialogOptions = {
    open: function () {
      $(this).siblings('.ui-dialog-titlebar').remove();
    },
    modal: true,
    zIndex: 10000,
    position: {my: 'top', at: 'top+25%'},
    autoOpen: true,
    width: 'auto',
    resizable: false,
    closeOnEscape: false
  };

  /**
   * Behaviors the autosave form feature.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the autosave behavior.
   */
  Drupal.behaviors.autosaveForm = {
    attach: function (context, settings) {
      var $context = $(context);
      var autosave_submit = $context.find('form .' + Drupal.autosaveForm.autosave_submit_class);

      // Disable autosave when the form is submitted in order to prevent race
      // conditions and creating further autosave entries after the entity is
      // saved.
      if (autosave_submit.length > 0) {
        $context.find('form').submit(function () {
          if (Drupal.autosaveForm.autosaveFormRunning) {
            Drupal.autosaveForm.autosaveFormRunning = false;
            clearInterval(Drupal.autosaveForm.timer);
            Drupal.autosaveForm.timer = null;
          }
        });
      }

      if (settings.hasOwnProperty('autosaveForm')) {
        $.extend(Drupal.autosaveForm, settings.autosaveForm);
      }

      if (Drupal.autosaveForm.initialized) {
        // If requested so turn off ajax submission.
        if (!Drupal.autosaveForm.autosaveFormRunning && Drupal.autosaveForm.timer) {
          clearInterval(Drupal.autosaveForm.timer);
          Drupal.autosaveForm.timer = null;
        }
        else {
          return;
        }
      }

      // Continue to show the dialog or activate autosave functionality only in
      // case the context contains the autosave submit and it is not disabled.
      if (autosave_submit.length === 0 || autosave_submit.is(':disabled') || autosave_submit.hasClass('is-disabled')) {
        return;
      }
      Drupal.autosaveForm.autosaveSubmit = autosave_submit;

      if (!Drupal.autosaveForm.initialized && !Drupal.autosaveForm.autosaveFormRunning) {
        Drupal.autosaveForm.initialized = true;

        $('<div id="autosave-notification" />').appendTo('body').append(Drupal.autosaveForm.notification.message);

        // Show the resume/discard confirmation message only if the form does
        // not contain any errors, otherwise continue with normal autosave
        // submissions. This is for the use case where a form is submitted but
        // returned to the user with validation errors in which case we should
        // not show the resume/discard message but continue the autosave
        // submissions.
        if (Drupal.autosaveForm.message && !Drupal.autosaveForm.formHasErrors) {
          var dialogOptions = {
            buttons: {
              button_confirm: {
                text: Drupal.t('Resume editing'),
                class: 'autosave-form-resume-button',
                click: function () {
                  // Non ajax buttons are bound to click.
                  $('.' + Drupal.autosaveForm.autosave_restore_class).click();
                }
              },
              button_reject: {
                text: Drupal.t('Discard'),
                class: 'autosave-form-reject-button',
                click: function () {
                  triggerAjaxSubmitWithoutProgressIndication(Drupal.autosaveForm.autosave_reject_class);
                  $(this).dialog('close');
                },
                primary: true
              }
            },
            close: function (event, ui) {
              $(this).remove();
              $(context).find('.' + Drupal.autosaveForm.autosave_restore_class).remove();
              $(context).find('.' + Drupal.autosaveForm.autosave_reject_class).remove();
              $(context).find('.autosave-form-restore-discard').remove();
              autosavePeriodic();
            }
          };
          $.extend(true, dialogOptions, Drupal.autosaveForm.defaultDialogOptions, Drupal.autosaveForm.dialog_options);

          $('<div></div>').appendTo('body')
            .html('<div>' + Drupal.autosaveForm.message + '</div>')
            .dialog(dialogOptions);
        }
        else {
          autosavePeriodic();
        }
      }

      /**
       * Returns the ajax instance corresponding to an element.
       *
       * @param {string} class_name
       *   The element class name for which to return its ajax instance.
       *
       * @return {Drupal.Ajax | null}
       *   The ajax instance if found, otherwise null.
       */
      function findAjaxInstance(class_name) {
        if (!Drupal.autosaveForm.autosaveFormInstances.hasOwnProperty(class_name)) {
          var element = document.getElementsByClassName(class_name)[0];
          var ajax = null;
          var selector = '#' + element.id;
          for (var index in Drupal.ajax.instances) {
            if (Drupal.ajax.instances.hasOwnProperty(index)) {
              var ajaxInstance = Drupal.ajax.instances[index];
              if (ajaxInstance && (ajaxInstance.selector === selector)) {
                ajax = ajaxInstance;
                break;
              }
            }
          }
          Drupal.autosaveForm.autosaveFormInstances[class_name] = ajax;
        }
        return Drupal.autosaveForm.autosaveFormInstances[class_name];
      }

      /**
       * Triggers an ajax submit based on the class of the ajax element.
       *
       * @param {string} ajax_class
       *   The class of the ajax element.
       */
      function triggerAjaxSubmitWithoutProgressIndication(ajax_class) {
        // If the autosave button suddenly gets the 'is-disabled' class then
        // autosave submission should not run until the class is removed.
        if (Drupal.autosaveForm.autosaveSubmit.is(':disabled') || Drupal.autosaveForm.autosaveSubmit.hasClass('is-disabled')) {
          return;
        }
        var ajax = findAjaxInstance(ajax_class);
        if (ajax) {
          if (Drupal.autosaveForm.notification.active) {
            $('#autosave-notification').fadeIn().delay(Drupal.autosaveForm.notification.delay).fadeOut();
          }
          ajax.options.error = function (xmlhttprequest, text_status, error_thrown) {
            if (xmlhttprequest.status === 0 || xmlhttprequest.status >= 400) {
              Drupal.autosaveForm.autosaveFormRunning = false;
              clearInterval(Drupal.autosaveForm.timer);
              Drupal.autosaveForm.timer = null;

              if (!Drupal.autosaveForm.beforeUnloadCalled) {
                var dialogOptions = {
                  buttons: {
                    button_confirm: {
                      text: Drupal.t('Ok'),
                      primary: true,
                      click: function () {
                        $(this).dialog('close');
                      }
                    }
                  }
                };
                $.extend(true, dialogOptions, Drupal.autosaveForm.defaultDialogOptions);

                $('<div></div>').appendTo('body')
                  .html('<div>' + Drupal.t('A server error occurred during autosaving the current page. As a result autosave is disabled. To activate it please revisit the page and continue the editing from the last autosaved state of the current page.') + '</div>')
                  .dialog(dialogOptions);
              }
            }
          };
          // Disable progress indication.
          ajax.progress = false;
          $(ajax.element).trigger(ajax.element_settings.event);
        }
      }

      /**
       * Starts periodic autosave submission.
       */
      function autosavePeriodic() {
        if (Drupal.autosaveForm.interval) {
          Drupal.autosaveForm.autosaveFormRunning = true;

          // Run the autosave submission at the beginning to capture the user
          // input and compare it later for changes, however wait for sometime
          // until triggering the submission in order to let all the Drupal
          // behaviors be executed and probably alter the page before doing the
          // first submission, otherwise we might capture not the correct user
          // input and on the second submission detect changes even if there
          // aren't actually any changes.
          // @todo Remove this and let autosave attach itself instead as the
          // last behavior as soon as the following issues are fixed:
          // @see https://www.drupal.org/node/2367655
          // @see https://www.drupal.org/node/2474019
          if (Drupal.autosaveForm.interval > 500) {
            setTimeout(function () {triggerAjaxSubmitWithoutProgressIndication(Drupal.autosaveForm.autosave_submit_class);}, 500);
          }

          Drupal.autosaveForm.timer = setInterval(function () {
            if (!Drupal.ajax.instances.some(isAjaxing)) {
              triggerAjaxSubmitWithoutProgressIndication(Drupal.autosaveForm.autosave_submit_class);
            }
          }, Drupal.autosaveForm.interval);
        }
      }

      /**
       * Checks if an ajax instance is currently running a submission.
       *
       * @param {Drupal.Ajax}  instance
       *   The ajax instance.
       *
       * @return {boolean}
       *   TRUE if the ajax instance is in a state of submitting.
       */
      function isAjaxing(instance) {
        return instance && instance.hasOwnProperty('ajaxing') && instance.ajaxing === true;
      }

    }
  };

  /**
   * Command to open a dialog for notifying that autosave has been disabled.
   *
   * We have to use a dedicated command, as otherwise there is no way to define
   * the "click" on the button and close the dialog when the button is clicked.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.openAutosaveDisabledDialog = function(ajax, response, status) {
    response.dialogOptions.buttons = {
      button_confirm: {
        text: Drupal.t('Ok'),
        click: function () {
          $(this).dialog('close');
        }
      }
    };
    // Remove the "x" button and force confirmation through the "Ok" button.
    response.dialogOptions.open = function () {
      $(this).siblings('.ui-dialog-titlebar').find('.ui-dialog-titlebar-close').remove();
    };

    Drupal.AjaxCommands.prototype.openDialog(ajax, response, status);
  };

})(jQuery, Drupal, drupalSettings);
