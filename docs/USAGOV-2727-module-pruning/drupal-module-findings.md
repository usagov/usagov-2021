# Drupal 10 Module Findings

Date: 2026-05-04
Project root: `/srv/usagov-2021`

## Sources used
- `config/sync/core.extension.yml`
- `composer.json`
- `composer.lock`
- Module definitions under `web/**/modules/**.info.yml` (excluding test fixture paths)
- Runtime validation via `/srv/usagov-2021/bin/drush pml --type=module --status=enabled --format=list`

## 1) Modules present in this installation (codebase)
- Total present modules (unique, non-test): **208**
- Core: **81**
- Contrib: **102**
- Custom: **25**

### Present custom modules (25)
`usa_admin_styles`, `usa_contact_center_api`, `usa_content_moderation_notifications`, `usa_orphaned_entities`, `usa_translation`, `usa_twig_vars`, `usa_workflow`, `usagov_benefit_category_search`, `usagov_benefit_finder`, `usagov_benefit_finder_api`, `usagov_benefit_finder_content`, `usagov_benefit_finder_page`, `usagov_blog_date_menu`, `usagov_ckeditor5_source_editing_fixup`, `usagov_directories`, `usagov_extra_functions`, `usagov_login`, `usagov_menus`, `usagov_redirect`, `usagov_sitemap`, `usagov_ssg_postprocessing`, `usagov_top_navigation_menu`, `usagov_unpub_es`, `usagov_uswds_paragraph_components_mods`, `usagov_wizard`

## 2) Modules enabled and in use (runtime in CMS container)
- Total enabled modules (Drush): **135**
- Enabled modules not present in codebase: **0**

### Runtime/config comparison
- In Drush runtime list but not `core.extension.yml`: `devel`, `devel_generate`
- In `core.extension.yml` but not Drush runtime list: `minimal` (install profile entry, not a module)

### Enabled custom modules (23)
`usa_admin_styles`, `usa_contact_center_api`, `usa_content_moderation_notifications`, `usa_orphaned_entities`, `usa_translation`, `usa_twig_vars`, `usagov_benefit_category_search`, `usagov_benefit_finder`, `usagov_benefit_finder_api`, `usagov_benefit_finder_content`, `usagov_benefit_finder_page`, `usagov_blog_date_menu`, `usagov_ckeditor5_source_editing_fixup`, `usagov_directories`, `usagov_extra_functions`, `usagov_login`, `usagov_menus`, `usagov_redirect`, `usagov_sitemap`, `usagov_ssg_postprocessing`, `usagov_top_navigation_menu`, `usagov_uswds_paragraph_components_mods`, `usagov_wizard`

### Present but not enabled (custom)
`usa_workflow`, `usagov_unpub_es`
