<?php

/**
 * @file
 * Preprocess function for this hook.
 */

/**
 * Implements hook_preprocess_page().
 */
function uswds_base_preprocess_page(&$variables) {

  // Provide the agency information for the footer.
  $variables['display_footer_agency'] = theme_get_setting('uswds_footer_agency');
  $footer_variables = [
    'footer_agency_name',
    'footer_agency_url',
    'footer_agency_logo',
    'contact_center',
    'phone',
    'email',
    'facebook',
    'twitter',
    'youtube',
    'rss',
  ];
  foreach ($footer_variables as $footer_variable) {
    $variables[$footer_variable] = theme_get_setting('uswds_' . $footer_variable);
  }
  // Fix the footer logo path.
  if ($variables['footer_agency_logo']) {
    $variables['footer_agency_logo'] = base_path() . uswds_base_theme_trail_file($variables['footer_agency_logo']);
  }

  // Decide on the type of header.
  $header_style = theme_get_setting('uswds_header_style');
  if (empty($header_style)) {
    $header_style = 'extended';
  }
  $variables['header_style'] = 'usa-header--' . $header_style;
  $variables['header_classes'] = 'usa-header ' . $variables['header_style'];
  if (theme_get_setting('uswds_header_mega')) {
    $variables['header_classes'] .= ' ' . $variables['header_style'] . '-megamenu';
  }
  // And some helpful flags for the header.
  $variables['header_basic'] = ('usa-header--basic' == $variables['header_style']);
  $variables['header_extended'] = ('usa-header--extended' == $variables['header_style']);

  // Hide the secondary menu if using the basic header.
  if ($variables['header_basic']) {
    $variables['page']['secondary_menu'] = FALSE;
  }

  // Show the official U.S. Government banner?
  if (theme_get_setting('uswds_government_banner')) {
    $variables['government_banner'] = [
      '#theme' => 'government_banner',
      '#image_base' => $variables['active_theme_path'] . '/assets/img',
    ];
  }

  // Decide on the type of footer.
  $footer_style = theme_get_setting('uswds_footer_style');
  if (empty($footer_style)) {
    $footer_style = 'slim';
  }
  $variables['footer_style'] = 'usa-footer--' . $footer_style;
  $variables['footer_classes'] = 'usa-footer ' . $variables['footer_style'];
  $variables['footer_menu_width'] = '';

  // Add some helpful flags for the footer.
  $variables['footer_slim'] = ('usa-footer--slim' == $variables['footer_style']);
  $variables['footer_medium'] = ('usa-footer--medium' == $variables['footer_style']);
  $variables['footer_big'] = ('usa-footer-big' == $variables['footer_style']);
  $variables['footer_agency_heading_class'] = 'usa-footer-logo-heading';
  $variables['footer_agency_logo_class'] = 'usa-footer-logo-img';

  // Whether to display the primary footer section.
  $display_primary_footer = (!empty($variables['page']['footer']));
  $display_primary_footer |= (!empty($variables['page']['footer_menu']));
  $display_primary_footer |= ($variables['footer_slim'] && ($variables['email'] || $variables['phone']));
  $variables['display_footer_primary'] = $display_primary_footer;

  // Whether to display the secondary footer section.
  $display_secondary_footer = (!empty($variables['page']['footer_secondary']));
  $display_secondary_footer |= $variables['display_footer_agency'];
  $variables['display_footer_secondary'] = $display_secondary_footer;

  // Provide this variable so that additional classes can be added to the main
  // section, similar to the header and footer.
  $variables['main_classes'] = '';
}
