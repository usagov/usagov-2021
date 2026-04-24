# Drupal Module Inventory

Generated: 2026-04-23 09:38:47 EDT

## Sources Used

- config/sync/core.extension.yml (enabled modules)
- web/core/modules/*.info.yml (core modules present on disk)
- web/modules/*.info.yml excluding test-only paths (contrib/custom modules present on disk)
- config/sync/*.yml (usage evidence via module-owned config names and dependency references)

## Summary

- Total module entries in inventory (union of present + enabled): 207
- Modules present on disk: 206
- Modules enabled in core.extension.yml: 134
- Enabled modules with config evidence: 91
- Enabled modules without direct config evidence: 43

> Note: minimal is listed under module in core.extension.yml but is the install profile (profile: minimal), not a regular module in web/modules.

## Enabled And In Use (Config Evidence Found)

- admin_toolbar
- admin_toolbar_tools
- allowed_formats
- autologout
- block
- block_content
- ckeditor5
- composer_deploy
- conditional_fields
- config_pages
- config_split
- content_lock
- content_lock_timeout
- content_moderation
- content_moderation_notifications
- content_translation
- csv_serialization
- datetime
- diff
- editor
- embed
- embedded_content
- entity_embed
- entity_reference_revisions
- externalauth
- faqfield
- feeds
- field
- field_defaults
- field_group
- field_permissions
- field_ui
- file
- filter
- hierarchy_manager
- image
- image_style_warmer
- jsonapi
- language
- language_switcher_extended
- link
- log_stdout
- media
- media_library
- menu_block
- menu_breadcrumb
- menu_item_fields
- menu_link_content
- menu_ui
- new_relic_rpm
- node
- node_menus
- options
- paragraphs
- paragraphs_entity_embed
- path
- path_alias
- pathauto
- permission_spreadsheet
- redirect
- remove_http_headers
- responsive_image
- rest
- s3fs
- samlauth
- scheduler
- scheduler_content_moderation_integration
- serialization
- simple_sitemap
- sortableviews
- structure_sync
- system
- taxonomy
- telephone
- term_condition
- text
- tome_static
- toolbar
- usa_content_moderation_notifications
- usagov_benefit_category_search
- usagov_benefit_finder
- usagov_login
- usagov_menus
- user
- uswds_ckeditor_integration
- views
- views_data_export
- views_menu_children_filter
- views_ui
- viewsreference
- workflows

## Enabled But No Direct Config Evidence

_These are often foundational/runtime modules that may still be actively required by code paths._

- breakpoint
- ckeditor_media_resize
- config
- contextual
- ctools
- datetime_range
- dynamic_page_cache
- jquery_ui
- jquery_ui_accordion
- js_cookie
- menu_item_fields_ui
- minimal
- mysql
- page_cache
- redis
- settings_tray
- simplify_menu
- token
- tome_base
- twig_field_value
- twig_tweak
- update
- usa_admin_styles
- usa_contact_center_api
- usa_orphaned_entities
- usa_translation
- usa_twig_vars
- usagov_benefit_finder_api
- usagov_benefit_finder_content
- usagov_benefit_finder_page
- usagov_blog_date_menu
- usagov_ckeditor5_source_editing_fixup
- usagov_directories
- usagov_extra_functions
- usagov_redirect
- usagov_sitemap
- usagov_ssg_postprocessing
- usagov_top_navigation_menu
- usagov_uswds_paragraph_components_mods
- usagov_wizard
- uswds_paragraph_components
- uswds_paragraph_components_breakpoints
- uswds_paragraph_components_cards

## Present On Disk But Not Enabled

- action
- address
- admin_toolbar_links_access_filter
- admin_toolbar_search
- announcements_feed
- automated_cron
- ban
- basic_auth
- big_pipe
- book
- comment
- config_translation
- contact
- ctools_block
- ctools_entity_mask
- ctools_views
- dblog
- devel
- devel_generate
- feeds_log
- field_group_accordion
- field_group_migrate
- field_layout
- forum
- help
- help_topics
- history
- inline_form_errors
- layout_builder
- layout_discovery
- locale
- meaofd
- migrate
- migrate_drupal
- migrate_drupal_ui
- navigation
- paragraphs_demo
- paragraphs_library
- paragraphs_type_permissions
- pgsql
- phpass
- redirect_404
- redirect_domain
- samlauth_user_fields
- samlauth_user_roles
- scheduler_rules_integration
- sdc
- search
- shortcut
- simple_sitemap_engines
- simple_sitemap_views
- simplify_menu_test
- sqlite
- statistics
- syslog
- tome
- tome_static_cron
- tome_static_super_cache
- tome_sync
- tome_sync_autoclean
- tour
- tracker
- usa_workflow
- usagov_unpub_es
- uswds_ckeditor_integration_embed
- uswds_paragraph_components_accordions
- uswds_paragraph_components_alerts
- uswds_paragraph_components_columns
- uswds_paragraph_components_modal
- uswds_paragraph_components_process_list
- uswds_paragraph_components_step_indicator
- uswds_paragraph_components_summary_box
- workspaces

## Enabled But Not Present On Disk

- minimal

## CSV

See: docs/drupal-module-inventory.csv

Columns:
- module
- present_on_disk (1 yes, 0 no)
- enabled_in_core_extension (1 yes, 0 no)
- config_evidence (1 yes, 0 no)

## Tabular View (From CSV)

| module | present_on_disk | enabled_in_core_extension | config_evidence |
|---|---:|---:|---:|
| action | 1 | 0 | 0 |
| address | 1 | 0 | 0 |
| admin_toolbar | 1 | 1 | 1 |
| admin_toolbar_links_access_filter | 1 | 0 | 0 |
| admin_toolbar_search | 1 | 0 | 0 |
| admin_toolbar_tools | 1 | 1 | 1 |
| allowed_formats | 1 | 1 | 1 |
| announcements_feed | 1 | 0 | 0 |
| autologout | 1 | 1 | 1 |
| automated_cron | 1 | 0 | 0 |
| ban | 1 | 0 | 0 |
| basic_auth | 1 | 0 | 0 |
| big_pipe | 1 | 0 | 0 |
| block | 1 | 1 | 1 |
| block_content | 1 | 1 | 1 |
| book | 1 | 0 | 0 |
| breakpoint | 1 | 1 | 0 |
| ckeditor5 | 1 | 1 | 1 |
| ckeditor_media_resize | 1 | 1 | 0 |
| comment | 1 | 0 | 0 |
| composer_deploy | 1 | 1 | 1 |
| conditional_fields | 1 | 1 | 1 |
| config | 1 | 1 | 0 |
| config_pages | 1 | 1 | 1 |
| config_split | 1 | 1 | 1 |
| config_translation | 1 | 0 | 0 |
| contact | 1 | 0 | 0 |
| content_lock | 1 | 1 | 1 |
| content_lock_timeout | 1 | 1 | 1 |
| content_moderation | 1 | 1 | 1 |
| content_moderation_notifications | 1 | 1 | 1 |
| content_translation | 1 | 1 | 1 |
| contextual | 1 | 1 | 0 |
| csv_serialization | 1 | 1 | 1 |
| ctools | 1 | 1 | 0 |
| ctools_block | 1 | 0 | 0 |
| ctools_entity_mask | 1 | 0 | 0 |
| ctools_views | 1 | 0 | 0 |
| datetime | 1 | 1 | 1 |
| datetime_range | 1 | 1 | 0 |
| dblog | 1 | 0 | 0 |
| devel | 1 | 0 | 0 |
| devel_generate | 1 | 0 | 0 |
| diff | 1 | 1 | 1 |
| dynamic_page_cache | 1 | 1 | 0 |
| editor | 1 | 1 | 1 |
| embed | 1 | 1 | 1 |
| embedded_content | 1 | 1 | 1 |
| entity_embed | 1 | 1 | 1 |
| entity_reference_revisions | 1 | 1 | 1 |
| externalauth | 1 | 1 | 1 |
| faqfield | 1 | 1 | 1 |
| feeds | 1 | 1 | 1 |
| feeds_log | 1 | 0 | 0 |
| field | 1 | 1 | 1 |
| field_defaults | 1 | 1 | 1 |
| field_group | 1 | 1 | 1 |
| field_group_accordion | 1 | 0 | 0 |
| field_group_migrate | 1 | 0 | 0 |
| field_layout | 1 | 0 | 0 |
| field_permissions | 1 | 1 | 1 |
| field_ui | 1 | 1 | 1 |
| file | 1 | 1 | 1 |
| filter | 1 | 1 | 1 |
| forum | 1 | 0 | 0 |
| help | 1 | 0 | 0 |
| help_topics | 1 | 0 | 0 |
| hierarchy_manager | 1 | 1 | 1 |
| history | 1 | 0 | 0 |
| image | 1 | 1 | 1 |
| image_style_warmer | 1 | 1 | 1 |
| inline_form_errors | 1 | 0 | 0 |
| jquery_ui | 1 | 1 | 0 |
| jquery_ui_accordion | 1 | 1 | 0 |
| js_cookie | 1 | 1 | 0 |
| jsonapi | 1 | 1 | 1 |
| language | 1 | 1 | 1 |
| language_switcher_extended | 1 | 1 | 1 |
| layout_builder | 1 | 0 | 0 |
| layout_discovery | 1 | 0 | 0 |
| link | 1 | 1 | 1 |
| locale | 1 | 0 | 0 |
| log_stdout | 1 | 1 | 1 |
| meaofd | 1 | 0 | 0 |
| media | 1 | 1 | 1 |
| media_library | 1 | 1 | 1 |
| menu_block | 1 | 1 | 1 |
| menu_breadcrumb | 1 | 1 | 1 |
| menu_item_fields | 1 | 1 | 1 |
| menu_item_fields_ui | 1 | 1 | 0 |
| menu_link_content | 1 | 1 | 1 |
| menu_ui | 1 | 1 | 1 |
| migrate | 1 | 0 | 0 |
| migrate_drupal | 1 | 0 | 0 |
| migrate_drupal_ui | 1 | 0 | 0 |
| minimal | 0 | 1 | 0 |
| mysql | 1 | 1 | 0 |
| navigation | 1 | 0 | 0 |
| new_relic_rpm | 1 | 1 | 1 |
| node | 1 | 1 | 1 |
| node_menus | 1 | 1 | 1 |
| options | 1 | 1 | 1 |
| page_cache | 1 | 1 | 0 |
| paragraphs | 1 | 1 | 1 |
| paragraphs_demo | 1 | 0 | 0 |
| paragraphs_entity_embed | 1 | 1 | 1 |
| paragraphs_library | 1 | 0 | 0 |
| paragraphs_type_permissions | 1 | 0 | 0 |
| path | 1 | 1 | 1 |
| path_alias | 1 | 1 | 1 |
| pathauto | 1 | 1 | 1 |
| permission_spreadsheet | 1 | 1 | 1 |
| pgsql | 1 | 0 | 0 |
| phpass | 1 | 0 | 0 |
| redirect | 1 | 1 | 1 |
| redirect_404 | 1 | 0 | 0 |
| redirect_domain | 1 | 0 | 0 |
| redis | 1 | 1 | 0 |
| remove_http_headers | 1 | 1 | 1 |
| responsive_image | 1 | 1 | 1 |
| rest | 1 | 1 | 1 |
| s3fs | 1 | 1 | 1 |
| samlauth | 1 | 1 | 1 |
| samlauth_user_fields | 1 | 0 | 0 |
| samlauth_user_roles | 1 | 0 | 0 |
| scheduler | 1 | 1 | 1 |
| scheduler_content_moderation_integration | 1 | 1 | 1 |
| scheduler_rules_integration | 1 | 0 | 0 |
| sdc | 1 | 0 | 0 |
| search | 1 | 0 | 0 |
| serialization | 1 | 1 | 1 |
| settings_tray | 1 | 1 | 0 |
| shortcut | 1 | 0 | 0 |
| simple_sitemap | 1 | 1 | 1 |
| simple_sitemap_engines | 1 | 0 | 0 |
| simple_sitemap_views | 1 | 0 | 0 |
| simplify_menu | 1 | 1 | 0 |
| simplify_menu_test | 1 | 0 | 0 |
| sortableviews | 1 | 1 | 1 |
| sqlite | 1 | 0 | 0 |
| statistics | 1 | 0 | 0 |
| structure_sync | 1 | 1 | 1 |
| syslog | 1 | 0 | 0 |
| system | 1 | 1 | 1 |
| taxonomy | 1 | 1 | 1 |
| telephone | 1 | 1 | 1 |
| term_condition | 1 | 1 | 1 |
| text | 1 | 1 | 1 |
| token | 1 | 1 | 0 |
| tome | 1 | 0 | 0 |
| tome_base | 1 | 1 | 0 |
| tome_static | 1 | 1 | 1 |
| tome_static_cron | 1 | 0 | 0 |
| tome_static_super_cache | 1 | 0 | 0 |
| tome_sync | 1 | 0 | 0 |
| tome_sync_autoclean | 1 | 0 | 0 |
| toolbar | 1 | 1 | 1 |
| tour | 1 | 0 | 0 |
| tracker | 1 | 0 | 0 |
| twig_field_value | 1 | 1 | 0 |
| twig_tweak | 1 | 1 | 0 |
| update | 1 | 1 | 0 |
| usa_admin_styles | 1 | 1 | 0 |
| usa_contact_center_api | 1 | 1 | 0 |
| usa_content_moderation_notifications | 1 | 1 | 1 |
| usa_orphaned_entities | 1 | 1 | 0 |
| usa_translation | 1 | 1 | 0 |
| usa_twig_vars | 1 | 1 | 0 |
| usa_workflow | 1 | 0 | 0 |
| usagov_benefit_category_search | 1 | 1 | 1 |
| usagov_benefit_finder | 1 | 1 | 1 |
| usagov_benefit_finder_api | 1 | 1 | 0 |
| usagov_benefit_finder_content | 1 | 1 | 0 |
| usagov_benefit_finder_page | 1 | 1 | 0 |
| usagov_blog_date_menu | 1 | 1 | 0 |
| usagov_ckeditor5_source_editing_fixup | 1 | 1 | 0 |
| usagov_directories | 1 | 1 | 0 |
| usagov_extra_functions | 1 | 1 | 0 |
| usagov_login | 1 | 1 | 1 |
| usagov_menus | 1 | 1 | 1 |
| usagov_redirect | 1 | 1 | 0 |
| usagov_sitemap | 1 | 1 | 0 |
| usagov_ssg_postprocessing | 1 | 1 | 0 |
| usagov_top_navigation_menu | 1 | 1 | 0 |
| usagov_unpub_es | 1 | 0 | 0 |
| usagov_uswds_paragraph_components_mods | 1 | 1 | 0 |
| usagov_wizard | 1 | 1 | 0 |
| user | 1 | 1 | 1 |
| uswds_ckeditor_integration | 1 | 1 | 1 |
| uswds_ckeditor_integration_embed | 1 | 0 | 0 |
| uswds_paragraph_components | 1 | 1 | 0 |
| uswds_paragraph_components_accordions | 1 | 0 | 0 |
| uswds_paragraph_components_alerts | 1 | 0 | 0 |
| uswds_paragraph_components_breakpoints | 1 | 1 | 0 |
| uswds_paragraph_components_cards | 1 | 1 | 0 |
| uswds_paragraph_components_columns | 1 | 0 | 0 |
| uswds_paragraph_components_modal | 1 | 0 | 0 |
| uswds_paragraph_components_process_list | 1 | 0 | 0 |
| uswds_paragraph_components_step_indicator | 1 | 0 | 0 |
| uswds_paragraph_components_summary_box | 1 | 0 | 0 |
| views | 1 | 1 | 1 |
| views_data_export | 1 | 1 | 1 |
| views_menu_children_filter | 1 | 1 | 1 |
| views_ui | 1 | 1 | 1 |
| viewsreference | 1 | 1 | 1 |
| workflows | 1 | 1 | 1 |
| workspaces | 1 | 0 | 0 |
