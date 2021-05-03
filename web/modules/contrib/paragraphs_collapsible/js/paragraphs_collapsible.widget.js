(function($) {
  /**
   * Paragraphs Drag&Drop functions
   */
  Drupal.behaviors.paragraphs = {
    attach: function(context, settings) {
      var paragraphGuide = '> td > div > .form-wrapper > .paragraph-type-top, > td > div.ajax-new-content > div > .form-wrapper > .paragraph-type-top';
      var collapseAllText = Drupal.t('Collapse all');
      var expandAllText = Drupal.t('Expand all');
      var collapseRowText = Drupal.t('Collapse row');
      var expandRowText = Drupal.t('Expand row');
      var plusText = '[+]';
      var minusText = '[-]';

      /**
       * Setting up all the toggler and reference attributes
       */
      $('.field--widget-entity-reference-paragraphs table.field-multiple-table').each(function(paragraphIndex) {
        var $this = $(this); // set reference attribute for the table

        // only those with titles should have the expand or collapse button
        // get paragraph tiles and do not add expand or collapse to those without titles
        if (!$this.find('.paragraph-type-title').length) {
          return;
        }

        $this.attr('data-paragraph-reference', paragraphIndex) // set reference attribute for the table
          .find('> tbody > tr').once('paragraph-item-once').each(function(paragraphRowIndex) {
            var $row = $(this);
            var character = plusText;
            var label = expandRowText;

            // set references for each row
            $row.attr('data-row-reference', paragraphIndex + '-' + paragraphRowIndex);

            // check for error elements
            if ($row.find('.error').length || $row.find(' > td > .ajax-new-content').length) {
              $row.addClass('expanded');
              character = minusText;
              label = collapseRowText;
            }

            // create toggler for each paragraph element
            if ($row.find(paragraphGuide).find('+ .paragraphs-subform').length) {
              $row.find(paragraphGuide).find('> .paragraph-type-title').once('paragraph-item-toggle-once').append('<button class="paragraph-item-toggle" data-row-reference="' + paragraphIndex + '-' + paragraphRowIndex + '" aria-label="' + label + '" aria-expanded="' + (label === expandRowText) + '">' + character + '</button>');
            }
          });

        // create overarching toggler
        var hasExpandedRow = $this.find('> tbody > tr').length && $this.find('> tbody > tr.expanded').length;
        var togglerText = hasExpandedRow ? collapseAllText : expandAllText;
        var className = hasExpandedRow ? 'expanded' : '';

        $this.find('.field-label').first().once('paragraph-toggle-once').append('<button class="paragraph-toggle ' + className + '" data-paragraph-reference="' + paragraphIndex + '">' + togglerText + '</button>');
      });

      /**
       * Complete paragraph toggler
       */
      $('.paragraph-toggle', context).once('paragraph-rows-toggle').on('click', function(e) {
        e.preventDefault();

        var $toggle = $(this);
        var $rows = $('tr[data-row-reference^="' + $toggle.attr('data-paragraph-reference') + '-"]');

        if ($toggle.hasClass('expanded')) {
          $toggle.text(expandAllText).removeClass('expanded');
          $rows.removeClass('expanded').find(paragraphGuide).find('> .paragraph-type-title .paragraph-item-toggle').text(plusText).attr('aria-label', expandRowText).attr('aria-expanded', false);
        } else {
          $toggle.text(collapseAllText).addClass('expanded');
          $rows.addClass('expanded').find(paragraphGuide).find('> .paragraph-type-title .paragraph-item-toggle').text(minusText).attr('aria-label', collapseRowText).attr('aria-expanded', true);
        }
      });

      /**
       * Individual paragraph element toggler
       */
      $('.paragraph-item-toggle', context).once('paragraph-row-toggle').on('click', function(e) {
        e.preventDefault();

        var $toggle = $(this);
        var reference = $toggle.attr('data-row-reference');
        var $row = $('tr[data-row-reference="' + reference + '"]');

        // expand / collapse row
        $row.toggleClass('expanded');
        var rowIsExpanded = $row.hasClass('expanded');

        // visually show expanded / collapsed
        $toggle.text(rowIsExpanded ? minusText : plusText).attr('aria-label', rowIsExpanded ? collapseRowText : expandRowText).attr('aria-expanded', rowIsExpanded);

        // check if we expanded / collapsed all the rows
        var $rowsToggler = $('table[data-paragraph-reference="' + reference.charAt(0) + '"]').find(' > thead .paragraph-toggle');

        // change overarching toggler text when all row items are expanded / collapsed
        if ($row.hasClass('expanded') && $row.siblings().length === $row.siblings('.expanded').length) {
          $rowsToggler.text(collapseAllText).addClass('expanded');
        } else if ($row.siblings().length === $row.siblings(':not(.expanded)').length) {
          $rowsToggler.text(expandAllText).removeClass('expanded');
        }
      });
    },
  };
})(jQuery);
