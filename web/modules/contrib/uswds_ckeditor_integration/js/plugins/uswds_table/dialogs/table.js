/**
 * @license Copyright (c) 2003-2015, CKSource - Frederico Knabben. All rights
 *   reserved. For licensing, see LICENSE.md or http://ckeditor.com/license
 */
(function() {

  let commitValue = function(data) {
    let id = this.id;
    if (!data.info)
      data.info = {};
    data.info[id] = this.getValue();
  };

  function tableColumns(table) {
    let cols = 0,
      maxCols = 0;
    for (let i = 0, row, rows = table.$.rows.length; i < rows; i++) {
      row = table.$.rows[i];
      cols = 0;
      for (let j = 0, cell, cells = row.cells.length; j < cells; j++) {
        cell = row.cells[j];
        cols += cell.colSpan;
      }
      cols > maxCols && (maxCols = cols);
    }

    return maxCols;
  }

  // Whole-positive-integer validator.
  function validatorNum(msg) {
    return function() {
      let value = this.getValue(),
        pass = !!(CKEDITOR.dialog.validate.integer()(value) && value > 0);
      if (!pass) {
        alert(msg);
        this.select();
      }
      return pass;
    };
  }

  function tableDialog(editor, command) {
    let makeElement = function(name) {
      return new CKEDITOR.dom.element(name, editor.document);
    };

    return {
      title: editor.lang.table.title,
      minWidth: 400,
      minHeight: CKEDITOR.env.ie ? 400 : 280,

      onShow: function() {
        // Detect if there's a selected table.
        let selection = editor.getSelection(),
          ranges = selection.getRanges(),
          table;

        let rowsInput = this.getContentElement('info', 'txtRows'),
          colsInput = this.getContentElement('info', 'txtCols');

        if (command === 'tableProperties') {
          let selected = selection.getSelectedElement();
          if (selected && selected.is('table'))
            table = selected;
          else if (ranges.length > 0) {
            if (CKEDITOR.env.webkit)
              ranges[0].shrink(CKEDITOR.NODE_ELEMENT);

            table = editor.elementPath(ranges[0].getCommonAncestor(true)).contains('table', 1);
          }

          // Save a reference to the selected table, and push a new set of default values.
          this._.selectedElement = table;
        }

        // Enable or disable the row, cols, width fields.
        if (table) {
          this.setupContent(table);
          rowsInput && rowsInput.disable();
          colsInput && colsInput.disable();
        } else {
          rowsInput && rowsInput.enable();
          colsInput && colsInput.enable();
        }

        // Call the onChange method for the width field so
        // this get reflected into the Advanced tab.
        //  widthInput && widthInput.onChange();
      },
      onOk: function() {
        //  console.log('onOk: react on table insertion/change');
        let selection = editor.getSelection(),
          bms = this._.selectedElement && selection.createBookmarks();

        let table = this._.selectedElement || makeElement('table'),
          data = {};
        this.commitContent(data, table);

        let thead;
        let tbody;
        let theRow;
        let newCell;
        let row;
        if (data.info) {
          var info = data.info;

          // Generate the rows and cols.
          if (!this._.selectedElement) {
            let tbody = table.append(makeElement('tbody')),
              rows = parseInt(info['txtRows'], 10) || 0,
              cols = parseInt(info['txtCols'], 10) || 0;

            for (let i = 0; i < rows; i++) {
              let row = tbody.append(makeElement('tr'));
              for (let j = 0; j < cols; j++) {
                let cell = row.append(makeElement('td'));
                cell.appendBogus();
              }
            }
          }

          // Modify the table headers. Depends on having rows and cols generated
          // correctly so it can't be done in commit functions.

          // Should we make a <thead>?
          let headers = info['selHeaders'];
          if (!table.$.tHead && (headers === 'row' || headers === 'both')) {
            let thead = new CKEDITOR.dom.element(table.$.createTHead());
            tbody = table.getElementsByTag('tbody').getItem(0);
            let theRow = tbody.getElementsByTag('tr').getItem(0);

            // Change TD to TH:
            for (let i = 0; i < theRow.getChildCount(); i++) {
              let th = theRow.getChild(i);
              if (th.type === CKEDITOR.NODE_ELEMENT && !th.data('cke-bookmark')) {
                th.renameNode('th');
                th.setAttribute('scope', 'col');
              }
            }
            thead.append(theRow.remove());
          }

          if (table.$.tHead !== null && !(headers === 'row' || headers === 'both')) {
            // Move the row out of the THead and put it in the TBody:
            thead = new CKEDITOR.dom.element(table.$.tHead);
            tbody = table.getElementsByTag('tbody').getItem(0);

            let previousFirstRow = tbody.getFirst();
            while (thead.getChildCount() > 0) {
              theRow = thead.getFirst();
              for (i = 0; i < theRow.getChildCount(); i++) {
                let newCell = theRow.getChild(i);
                if (newCell.type === CKEDITOR.NODE_ELEMENT) {
                  newCell.renameNode('td');
                  newCell.removeAttribute('scope');
                }
              }
              theRow.insertBefore(previousFirstRow);
            }
            thead.remove();
          }

          // Should we make all first cells in a row TH?
          if (!this.hasColumnHeaders && (headers === 'col' || headers === 'both')) {
            for (let row = 0; row < table.$.rows.length; row++) {
              newCell = new CKEDITOR.dom.element(table.$.rows[row].cells[0]);
              newCell.renameNode('th');
              newCell.setAttribute('scope', 'row');
            }
          }

          // Should we make all first TH-cells in a row make TD? If 'yes' we do
          if ((this.hasColumnHeaders) && !(headers === 'col' || headers === 'both')) {
            for (let i = 0; i < table.$.rows.length; i++) {
              row = new CKEDITOR.dom.element(table.$.rows[i]);
              if (row.getParent().getName() === 'tbody') {
                newCell = new CKEDITOR.dom.element(row.$.cells[0]);
                newCell.renameNode('td');
                newCell.removeAttribute('scope');
              }
            }
          }

        }

        // Insert the table element if we're creating one.
        if (!this._.selectedElement) {
          editor.insertElement(table);
          // Override the default cursor position after insertElement to place
          setTimeout(function() {
            let firstCell = new CKEDITOR.dom.element(table.$.rows[0].cells[0]);
            let range = editor.createRange();
            range.moveToPosition(firstCell, CKEDITOR.POSITION_AFTER_START);
            range.select();
          }, 0);
        }
        else {
          try {
            selection.selectBookmarks(bms);
          } catch (er) {}
        }
        // There should be table. So lets add our classes.
        table.addClass('usa-table');
        table.addClass('cke_show_border');
        table.setStyle('width', '100%');
        if (info['tableStripped'])
          table.addClass('usa-table--striped');
        else
          table.removeClass('usa-table--striped');

        if (info['tableBorderless'])
          table.addClass('usa-table--borderless');
        else
          table.removeClass('usa-table--borderless');

        if (info['tableScrollable']) {
          let tablewrapper = makeElement('div');
          tablewrapper.addClass('usa-table-container--scrollable');
          tablewrapper.append(table);
          editor.insertElement(tablewrapper);
        } else {
          if (table.getParent().hasClass("usa-table-container--scrollable")) {
            let table_html = table.$.outerHTML;
            table.getParent().remove();
            editor.insertHtml(table_html);
          }
        }

        if (info['tableStackable']) {
          table.addClass('usa-table--stacked');
        } else {
          table.removeClass('usa-table--stacked');
        }
      },
      contents: [{
        id: 'info',
        label: editor.lang.table.title,
        elements: [{
          type: 'hbox',
          widths: ['50%', '50%'],
          styles: ['vertical-align:top'],
          children: [{
            type: 'vbox',
            padding: 0,
            children: [{
              type: 'text',
              id: 'txtRows',
              'default': 3,
              label: editor.lang.table.rows,
              required: true,
              controlStyle: 'width:5em',
              validate: validatorNum(editor.lang.table.invalidRows),
              setup: function(selectedElement) {
                this.setValue(selectedElement.$.rows.length);
              },
              commit: commitValue
            }, {
              type: 'text',
              id: 'txtCols',
              'default': 2,
              label: editor.lang.table.columns,
              required: true,
              controlStyle: 'width:5em',
              validate: validatorNum(editor.lang.table.invalidCols),
              setup: function(selectedTable) {
                this.setValue(tableColumns(selectedTable));
              },
              commit: commitValue
            }, {
              type: 'html',
              html: '&nbsp;'
            }, {
              type: 'select',
              id: 'selHeaders',
              requiredContent: 'th',
              'default': '',
              label: editor.lang.table.headers,
              items: [
                [editor.lang.table.headersNone, ''],
                [editor.lang.table.headersRow, 'row'],
                [editor.lang.table.headersColumn, 'col'],
                [editor.lang.table.headersBoth, 'both']
              ],
              setup: function(selectedTable) {
                // Fill in the headers field.
                let dialog = this.getDialog();
                dialog.hasColumnHeaders = true;

                // Check if all the first cells in every row are TH
                for (let row = 0; row < selectedTable.$.rows.length; row++) {
                  // If just one cell isn't a TH then it isn't a header column
                  let headCell = selectedTable.$.rows[row].cells[0];
                  if (headCell && headCell.nodeName.toLowerCase() !== 'th') {
                    dialog.hasColumnHeaders = false;
                    break;
                  }
                }

                // Check if the table contains <thead>.
                if ((selectedTable.$.tHead !== null))
                  this.setValue(dialog.hasColumnHeaders ? 'both' : 'row');
                else
                  this.setValue(dialog.hasColumnHeaders ? 'col' : '');
              },
              commit: commitValue
            },
              {
                type: 'html',
                id: 'txtBorder',
                html: '&nbsp;',
                commit: function() {
                  // We can remove it after changing the name of this plugin.
                }
              }
            ]
          }, {
            type: 'hbox',
            padding: 0,
            children: [{
              type: 'vbox',
              widths: ['12em'],
              children: [
                {
                  type: 'checkbox',
                  id: 'tableStripped',
                  labelStyle: 'display: inline',
                  label: 'Add Stripes to table.',
                  'default': '',
                  setup: function(selectedTable) {
                    let val = selectedTable.getParent().hasClass('usa-table--striped');
                    this.setValue(val);
                  },
                  commit: commitValue
                },
                {
                  type: 'checkbox',
                  id: 'tableBorderless',
                  labelStyle: 'display: inline',
                  label: 'Make Borderless',
                  'default': '',
                  setup: function(selectedTable) {
                    let val = selectedTable.hasClass('usa-table--borderless');
                    this.setValue(val);
                  },
                  commit: commitValue
                },
                {
                  type: 'checkbox',
                  id: 'tableScrollable',
                  labelStyle: 'display: inline',
                  label: 'Make Table Scrollable',
                  'default': '',
                  setup: function(selectedTable) {
                    let val = selectedTable.getParent().hasClass('usa-table-container--scrollable');
                    this.setValue(val);
                  },
                  commit: commitValue
                },
                {
                  type: 'checkbox',
                  id: 'tableStackable',
                  labelStyle: 'display: inline',
                  label: 'Make Table Stackable (In Mobile)',
                  'default': '',
                  setup: function(selectedTable) {
                    let val = selectedTable.hasClass('usa-table--stacked');
                    this.setValue(val);
                  },
                  commit: commitValue
                }]
            }]
          }]
        }, {
          type: 'vbox',
          padding: 0,
          children: [{
            type: 'text',
            id: 'txtCaption',
            requiredContent: 'caption',
            label: editor.lang.table.caption,
            setup: function(selectedTable) {
              this.enable();

              let nodeList = selectedTable.getElementsByTag('caption');
              if (nodeList.count() > 0) {
                let caption = nodeList.getItem(0);
                let firstElementChild = caption.getFirst(CKEDITOR.dom.walker.nodeType(CKEDITOR.NODE_ELEMENT));

                if (firstElementChild && !firstElementChild.equals(caption.getBogus())) {
                  this.disable();
                  this.setValue(caption.getText());
                  return;
                }

                caption = CKEDITOR.tools.trim(caption.getText());
                this.setValue(caption);
              }
            },
            commit: function(data, table) {
              if (!this.isEnabled())
                return;

              let caption = this.getValue(),
                captionElement = table.getElementsByTag('caption');
              if (caption) {
                if (captionElement.count() > 0) {
                  captionElement = captionElement.getItem(0);
                  captionElement.setHtml('');
                } else {
                  captionElement = new CKEDITOR.dom.element('caption', editor.document);
                  if (table.getChildCount())
                    captionElement.insertBefore(table.getFirst());
                  else
                    captionElement.appendTo(table);
                }
                captionElement.append(new CKEDITOR.dom.text(caption, editor.document));
              } else if (captionElement.count() > 0) {
                for (let i = captionElement.count() - 1; i >= 0; i--)
                  captionElement.getItem(i).remove();
              }
            }
          }]
        }]
      },
      ]
    };
  }

  CKEDITOR.dialog.add('uswds_table', function(editor) {
    return tableDialog(editor, 'table');
  });
  CKEDITOR.dialog.add('uswds_tableProperties', function(editor) {
    return tableDialog(editor, 'tableProperties');
  });
})();
