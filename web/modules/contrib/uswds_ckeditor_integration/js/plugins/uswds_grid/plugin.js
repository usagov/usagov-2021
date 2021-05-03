/**
 * @file
 * USWDS Grid plugin.
 */

(function ($, Drupal, CKEDITOR) {

  "use strict";

  function findGridWrapper(element) {
    return element.getAscendant(function (el) {
      if (typeof el.hasClass === 'function') {
        return el.hasClass('grid-container');
      }
      return false;
    }, true);
  }

  function getSelectedGrid(editor) {
    var selection = editor.getSelection();
    var selectedElement = selection.getStartElement();

    if (selectedElement && selectedElement.hasClass('grid-container')) {
      return selectedElement;
    }

    return findGridWrapper(selectedElement);
  }

  function extractClasses(element, base, reverse) {
    reverse = reverse || false;
    var classes = '';

    if (typeof element.getAttribute === 'function') {
      classes = element.getAttribute('class');
    }
    else if (typeof element.className === 'string') {
      classes = element.className;
    }

    // Failsafe.
    if (!classes) {
      return '';
    }

    var classlist = classes.split(" ").filter(function (c) {
      if (c.lastIndexOf('cke_', 0) === 0) { return false; }
      return reverse ? c.lastIndexOf(base, 0) === 0 : c.lastIndexOf(base, 0) !== 0;
    });

    return classlist.length ? classlist.join(" ").trim() : '';
  }

  CKEDITOR.plugins.add('uswds_grid', {
    requires: 'widget',
    icons: 'uswds_grid',
    init: function (editor) {

      // Allow widget editing.
      editor.widgets.add('uswds_grid_widget', {
        template:
            '<div class="grid-container"></div>',
        allowedContent: '',
        requiredContent: 'div',
        init: function () {
          var row = this.element.findOne('.grid-row');
          if (row) {
            var cols = row.find('> div');
            for(var i = 1; i <= cols.count(); i++) {
              this.initEditable('grid-col-' + i, {
                selector: '.grid-row > div:nth-child(' + i + ')',
                allowedContent: '',
              })
            }
          }
        },

      });

      // Add the dialog command.
      editor.addCommand('uswds_grid', {
        allowedContent: 'div[class, data-*]',
        requiredContent: 'div[class, data-*]',
        modes: {wysiwyg: 1},
        canUndo: true,
        exec: function (editor) {
          var existingValues = {};
          var existingElement = getSelectedGrid(editor);

          // Existing elements need to pull the settings.
          if (existingElement) {
            existingValues.saved = 1;
            var existing_row;

            // Parse out the data we need.
            existingValues.container_wrapper_class = extractClasses(existingElement, 'grid-container');
            var first_element = existingElement.findOne('> div');

            // We have a container if no row (container can have no class).
            if (!first_element.hasClass('grid-row')) {
              existingValues.add_container = 1;
              existingValues.container_class = extractClasses(first_element, 'grid-container');

              // Container can have no classes, so need direct compare.
              var container_type = extractClasses(first_element, 'grid-container', true);
              if (container_type.length) {
                if (container_type.indexOf('container-fluid') !== -1) {
                  existingValues.container_type = 'fluid';
                }
                else {
                  existingValues.container_type = 'default';
                }
              }

              // Get row info.
              existing_row = first_element.findOne('.grid-row');
            }
            else {
              existing_row = first_element;
            }

            var row_classes = extractClasses(existing_row, 'grid-row');
            existingValues.no_gutter = row_classes.indexOf('no-gutters') !== -1 ? 1 : 0;
            existingValues.row_class = row_classes.replace('no-gutters', '');

            // Cols.
            var existing_cols = existing_row.find('> div');
            existingValues.num_columns = existing_cols.count();

            // Layouts.
            existingValues.breakpoints = {
              none: {layout: existing_row.getAttribute('data-row-none')},
              sm: {layout: existing_row.getAttribute('data-row-sm')},
              md: {layout: existing_row.getAttribute('data-row-md')},
              lg: {layout: existing_row.getAttribute('data-row-lg')},
            };

            for (var i = 1; i <= existingValues.num_columns; i++) {
              var col = existing_cols.getItem(i - 1);
              var col_class = extractClasses(col, 'grid-col');
              var key = 'grid-col_' + i + '_classes';
              existingValues[key] = col_class;
            }

          }

          // Fired when saving the dialog.
          var saveCallback = function (values) {
            editor.fire('saveSnapshot');

            // Always output a wrapper.
            var wrapper_class = 'grid-container';
            if (values.container_wrapper_class !== undefined) {
              wrapper_class += ' ' + values.container_wrapper_class;
            }
            if (existingElement) {
              existingElement.setAttribute('class', wrapper_class);
            }
            else {
              var uswds_wrapper = editor.document.createElement('div', {attributes: {class: wrapper_class}});
            }

            // Add the row.
            var row_attributes = {
              class: values.row_class,
              'data-row-none': values.breakpoints.none.layout,
              'data-row-sm': values.breakpoints.sm.layout,
              'data-row-md': values.breakpoints.md.layout,
              'data-row-lg': values.breakpoints.lg.layout,
            };
            if (existingElement) {
              existing_row.setAttributes(row_attributes);
            }
            else {
              var row = editor.document.createElement('div', {attributes: row_attributes});
            }

            // Iterated through the cols.
            for (var i = 1; i <= values.num_columns; i++) {
              var key = 'col_' + i + '_classes';
              if (existingElement) {
                existing_cols.getItem(i -1).setAttribute('class', values[key]);
              }
              else {
                var col = editor.document.createElement('div', {attributes: {class: values[key]}});
                col.setHtml('Column ' + i + ' content');
                row.append(col);
              }
            }

            // Append to Wrapper. @TODO: Support for dropping existing container.
            if (!existingElement) {
              if (values.add_container) {
                var container = editor.document.createElement('div', {attributes: {class: values.container_class}});
                container.append(row);
                uswds_wrapper.append(container);
              }
              else {
                uswds_wrapper.append(row);
              }
              editor.insertHtml(uswds_wrapper.getOuterHtml());
            }

            // Final save.
            editor.fire('saveSnapshot');
          };


          var dialogSettings = {
            dialogClass: 'uswds_grid-dialog',
          };

          // Open the entity embed dialog for corresponding EmbedButton.
          Drupal.ckeditor.openDialog(editor, Drupal.url('uswds_ckeditor_integration/dialog'), existingValues, saveCallback, dialogSettings);
        }
      });

      // UI Button
      editor.ui.addButton('uswds_grid', {
        label: 'Insert USWDS Grid',
        command: 'uswds_grid',
        icon: this.path + 'icons/uswds_grid.png'
      });

      // Context menu to edit existing.
      if (editor.contextMenu) {
        editor.addMenuGroup('uswdsGridGroup');
        editor.addMenuItem('uswdsGridItem', {
          label: 'Edit Grid',
          icon: this.path + 'icons/uswds_grid.png',
          command: 'uswds_grid',
          group: 'uswdsGridGroup'
        });

        // Load nearest grid.
        editor.contextMenu.addListener(function (element) {
          if (findGridWrapper(element)) {
            return {uswdsGridItem: CKEDITOR.TRISTATE_OFF};
          }
        });
      }

    }
  });

})(jQuery, Drupal, CKEDITOR);
