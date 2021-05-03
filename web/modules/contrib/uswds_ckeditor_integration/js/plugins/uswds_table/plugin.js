/**
 * @license Copyright (c) 2003-2015, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */
CKEDITOR.plugins.add( 'uswds_table', {
  requires: 'dialog,table',
  icons: 'table',
  hidpi: true,
    init: function( editor ) {
      if ( editor.blockless )
        return;

      var lang = editor.lang.table;

      editor.addCommand( 'uswds_table', new CKEDITOR.dialogCommand( 'uswds_table', {
        context: 'table',
        allowedContent: 'table{width,height, style}[align,border,cellpadding,cellspacing,summary];' +
          'caption tbody thead tfoot;' +
          'th td tr[scope];' +
          ( editor.plugins.dialogadvtab ? 'table' + editor.plugins.dialogadvtab.allowedContent() : '' ),
        requiredContent: 'table',
        contentTransformations: [
          [ ]
        ]
      } ) );

      function createDef( def ) {
        return CKEDITOR.tools.extend( def || {}, {
          contextSensitive: 1,
          refresh: function( editor, path ) {
            this.setState( path.contains( 'table', 1 ) ? CKEDITOR.TRISTATE_OFF : CKEDITOR.TRISTATE_DISABLED );
          }
        } );
      }

      editor.addCommand( 'uswds_tableProperties', new CKEDITOR.dialogCommand( 'uswds_tableProperties', createDef() ) );

      editor.ui.addButton && editor.ui.addButton( 'Table', {
        label: lang.toolbar,
        command: 'uswds_table',
        toolbar: 'insert,30'
      } );

      CKEDITOR.dialog.add( 'uswds_table', this.path + 'dialogs/table.js' );
      CKEDITOR.dialog.add( 'uswds_tableProperties', this.path + 'dialogs/table.js' );

      // If the "menu" plugin is loaded, register the menu items.
      if ( editor.addMenuItems ) {
        editor.addMenuItems( {
          table: {
            label: lang.menu,
            command: 'uswds_tableProperties',
            group: 'table',
            order: 5
          },
        } );
      }

      editor.on( 'doubleclick', function( evt ) {
        var element = evt.data.element;

        if ( element.is( 'table' ) )
          evt.data.dialog = 'uswds_tableProperties';
      });

      // If the "contextmenu" plugin is loaded, register the listeners.
      if ( editor.contextMenu ) {
        editor.contextMenu.addListener( function() {
          // menu item state is resolved on commands.
          return {
            uswds_table: CKEDITOR.TRISTATE_OFF
          };
        });
      }
    }
  }
);
