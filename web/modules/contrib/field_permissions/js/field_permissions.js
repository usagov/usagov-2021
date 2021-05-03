/**
 * @file
 * Hide the permissions grid for all field permission types except custom.
 */

(function ($) {

  Drupal.behaviors.fieldPermissions = {
    attach: function (context, settings) {

      var PemTable = $(context).find('#permissions');
      var PermDefaultType = $(context).find('#edit-type input:checked');
      var PermInputType = $(context).find('#edit-type input');
      /*init*/
      if (PermDefaultType.val() != 'custom') {
        PemTable.hide();
      }
      /*change*/
      PermInputType.on('change', function () {
        var typeVal = $(this).val();
        if (typeVal != 'custom') {
          PemTable.hide();
        }
        else {
          PemTable.show();
        }
      });

    }};
})(jQuery);
