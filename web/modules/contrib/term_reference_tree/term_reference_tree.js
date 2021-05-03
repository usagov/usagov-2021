(function($) {

/**
 * Attaches the tree behavior to the term widget form.
 */
Drupal.behaviors.termReferenceTree = {
  attach: function(context, settings) {
    // Bind the term expand/contract button to slide toggle the list underneath.
    $('.term-reference-tree-button', context).once('term-reference-tree-button').click(function() {
      $(this).toggleClass('term-reference-tree-collapsed');
      $(this).siblings('ul').slideToggle('fast');
    });

    // An expand all button (unimplemented)
    /*
    $('.expandbutton').click(function() {
      $(this).siblings('.term-reference-tree-button').trigger('click');
    });
    */

    $('.term-reference-tree', context).once('term-reference-tree').each(function() {

      // On page load, check whether the maximum number of choices is already selected.
      // If so, disable the other options.
      var tree = $(this);
      checkMaxChoices(tree, false);
      $(this).find('input[type=checkbox]').change(function() {
        checkMaxChoices(tree, $(this));
      });

      //On page load, check if the user wants a cascading selection.
      if($(this).hasClass('term-reference-tree-select-parents')) {
        $(this).find('.form-checkbox').parent().addClass('select-parents');
      }

      //On page load, check if the user wants a track list. If so, add the
      //currently selected items to it.
      if($(this).hasClass('term-reference-tree-track-list-shown')) {
        var track_list_container = $(this).find('.term-reference-tree-track-list');

        //Var to track whether using checkboxes or radio buttons.
        var input_type =
          ( $(this).has('input[type=checkbox]').length > 0 ) ? 'checkbox' : 'radio';

        //Find all the checked controls.
        var checked_controls = $(this).find('input[type=' + input_type + ']:checked');

        //Get their labels.
        var labels = checked_controls.next();
        var label_element;

        //For each label of the checked boxes, add item to the track list.
        labels.each(function(index) {
          label_element = $(labels[index]);
          addItemToTrackList(
            track_list_container,         //Where to add new item.
            label_element.html(),         //Text of new item.
            $(label_element).attr('for'), //Id of control new item is for.
            input_type                    //checkbox or radio
          );
        }); //End labels.each

        //Show "nothing selected" message, if needed.
        showNothingSelectedMessage(track_list_container);

        //Event - when an element on the track list is clicked on:
        //  1. Delete it.
        //  2. Uncheck the associated checkbox.
        //The event is bound to the track list container, not each element.
        $(track_list_container).click(function(event){
          //Remove the "nothing selected" message if showing - add it later if needed.
          //removeNothingSelectedMessage(track_list_container);
          var event_target = $(event.target);
          var control_id = event_target.data('control_id');

          if(control_id) {
            event_target.remove();

            var checkbox = $('#' + control_id);
            checkbox.removeAttr('checked');
            checkMaxChoices(tree, checkbox);

            //Show "nothing selected" message, if needed.
            showNothingSelectedMessage(track_list_container);
          }
        });

        //Change track list when controls are clicked.
        $(this).find('.form-' + input_type).change(function(event){
          //Remove the "nothing selected" message if showing - add it later if needed.
          removeNothingSelectedMessage(track_list_container);
          var event_target = $(event.target);
          var control_id = event_target.attr('id');
          if ( event_target.attr('checked') ) {
            //Control checked - add item to the track list.
            label_element = event_target.next();
            addItemToTrackList(
              track_list_container,         //Where to add new item.
              label_element.html(),         //Text of new item.
              $(label_element).attr('for'), //Id of control new item is for.
              input_type                    //checkbox or radio
            );
          }
          else {
            //Checkbox unchecked. Remove from the track list.
            $('#' + control_id + '_list').remove();
          }

          //Show "nothing selected" message, if needed.
          showNothingSelectedMessage(track_list_container);
        }); //End process checkbox changes.
      } //End Want a track list.

      //On page load, check if the user wants a cascading selection.
      if($(this).hasClass('term-reference-tree-cascading-selection')) {
        var mode_select = $(this).hasClass('term-reference-tree-cascading-selection-mode-select');
        var mode_deselect = $(this).hasClass('term-reference-tree-cascading-selection-mode-deselect');

        //Check children when checkboxes are clicked.
        $(this).find('.form-checkbox').change(function(event) {
          var event_target = $(event.target);
          var event_target_checked = event_target.is(':checked');
          var control_id = event_target.attr('id');
          var children = event_target.parent().next().children().find('> :not(ul) > input[id^="' + control_id + '-children"]');

          if (!mode_select && !mode_deselect) {
            if(event_target_checked) {
              $(children).filter(':not(:checked)').click().trigger('change');
            }
            else {
              $(children).filter(':checked').click().trigger('change');
            }
          } else if (mode_select && event_target_checked) {
            $(children).filter(':not(:checked)').click().trigger('change');
          } else if (mode_deselect && !event_target_checked) {
            $(children).filter(':checked').click().trigger('change');
          }
        });
        //End process checkbox changes.
      } //End Want a cascading checking.

    });
  }
};

/**
 * Add a new item to the track list.
 * If more than one item can be selected, the new item is positioned to
 * match the order of the terms in the checkbox tree.
 *
 * @param track_list_container Container where the new item will be added.
 *
 * @param item_text Text of the item to add.
 *
 * @param control_id Id of the checkbox/radio control the item matches.
 *
 * @param control_type Control type - 'checkbox' or 'radio'.
 */
function addItemToTrackList(track_list_container, item_text, control_id, control_type) {
  var new_item = $('<li class="track-item">' + item_text + '</li>');
  new_item.data('control_id', control_id);

  //Add an id for easy finding of the item.
  new_item.attr('id', control_id + '_list');

  //Process radio controls - only one item can be selected.
  if ( control_type == 'radio') {
    //Find the existing element on the track list, if there is one.
    var current_items = track_list_container.find('li');

    //If there are no items on the track list, add the new item.
    if ( current_items.length == 0 ) {
      track_list_container.append(new_item);
    }
    else {
      //There is an item on the list.
      var current_item = $(current_items.get(0));

      //Is the item we want to add different from what is there?
      if ( current_item.data('control_id') != control_id ) {
        //Remove exiting element from track list, and add the new one.
        current_item.remove();
        track_list_container.append(new_item);
      }
    }
    return;
  }

  //Using checkboxes, so there can be more than one selected item.
  //Find the right place to put the new item, to match the order of the
  //checkboxes.
  var list_items = track_list_container.find('li');
  var item_comparing_to;

  //Flag to tell whether the item was inserted.
  var inserted_flag = false;
  list_items.each(function(index){
    item_comparing_to = $(list_items[index]);

    //If item is already on the track list, do nothing.
    if ( control_id == item_comparing_to.data('control_id') ) {
      inserted_flag = true;
      return false; //Returning false stops the loop.
    }
    else if ( control_id < item_comparing_to.data('control_id') ) {
      //Add it here.
      item_comparing_to.before(new_item);
      inserted_flag = true;
      return false; //Returning false stops the loop.
    }
  });

  //If not inserted yet, add new item at the end of the track list.
  if ( ! inserted_flag ) {
    track_list_container.append(new_item);
  }
}

/**
 * Show the 'nothing selected' message if it applies.
 *
 * @param track_list_container Where the message is to be shown.
 */
function showNothingSelectedMessage(track_list_container) {
  //Is the message there already?
  var message_showing =
      (track_list_container.find('.term_ref_tree_nothing_message').length != 0);

  //Number of real items showing.
  var num_real_items_showing =
      message_showing
      ? track_list_container.find('li').length - 1
      : track_list_container.find('li').length;
  if ( num_real_items_showing == 0 ) {
    //No items showing, so show the message.
    if ( ! message_showing ) {
      track_list_container.append(
          '<li class="term_ref_tree_nothing_message">' + termReferenceTreeNothingSelectedText + '</li>'
      );
    }
  }
  else { // !(num_real_items_showing == 0)
    //There are real items.
    if ( message_showing ) {
      track_list_container.find('.term_ref_tree_nothing_message').remove();
    }
  }
}

/**
 * Remove the 'nothing selected' message. Makes processing easier.
 *
 * @param track_list_container Where the message is shown.
 */
function removeNothingSelectedMessage(track_list_container) {
  track_list_container.find('.term_ref_tree_nothing_message').remove();
}

// This helper function checks if the maximum number of choices is already selected.
// If so, it disables all the other options.  If not, it enables them.
function checkMaxChoices(item, checkbox) {
  var maxChoices = -1;
  try {
    maxChoices = parseInt(Drupal.settings.term_reference_tree.trees[item.attr('id')]['max_choices']);
  }
  catch (e){}
  var count = item.find(':checked').length;

  if(maxChoices > 0 && count >= maxChoices) {
    item.find('input[type=checkbox]:not(:checked)').attr('disabled', 'disabled').parent().addClass('disabled');
  } else {
    item.find('input[type=checkbox]').removeAttr('disabled').parent().removeClass('disabled');
  }

  if(checkbox) {
    if(item.hasClass('term-reference-tree-select-parents')) {
      var track_list_container = item.find('.term-reference-tree-track-list');

      if(checkbox.prop('checked')) {
        checkbox.parents('ul.term-reference-tree-level li').children('div.form-item').find('input[type=checkbox]').each(function() {
          $(this).prop('checked', true);

          if(track_list_container) {
            var label_element = $(this).next();
            addItemToTrackList(
              track_list_container,         //Where to add new item.
              label_element.html(),         //Text of new item.
              $(label_element).attr('for'), //Id of control new item is for.
              (item.has('input[type=checkbox]').length > 0) ? 'checkbox' : 'radio'
            );
          }
        });
      }
    }
  }
}

})(jQuery);
