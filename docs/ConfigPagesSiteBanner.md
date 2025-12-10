# Config Pages Site Banner

## Overview

This document describes the Config Pages site banner system, which provides centralized management of site-wide alert banners. The system supports multiple alert banners and has completely replaced the previous block-based site banner implementation.

## Implementation Summary

### What was implemented

1. **Config Pages site banner system** using the Config Pages module for centralized banner management
2. **Multiple banner support** allowing multiple alert paragraphs to display as separate banners
3. **Custom template system** for config_pages site banners with analytics tracking
4. **Static site generation support** by pre-rendering paragraphs in the preprocess function
5. **Complete block system removal** eliminating the previous block-based site banner implementation

### Why Pre-rendering is Required for Static Sites

Config_pages entities don't have canonical URLs like nodes or taxonomy terms, so Tome's normal entity discovery process doesn't include them. Instead, config_pages content is embedded within actual pages (like every page that includes the header_top region).

When using `{{ drupal_entity('paragraph', id) }}` in Twig templates:
- **On the CMS**: Drupal dynamically loads and renders the entity at request time ✓
- **On the static site**: The entity reference can't be resolved because the page HTML is pre-generated ✗

**Solution**: Pre-render the paragraph entities in `usa_twig_vars_preprocess_region()` so the full HTML is generated during Tome's static site export, rather than relying on lazy-loading which doesn't work in static HTML files.

### Files Modified/Created

#### 1. `/web/modules/custom/usa_twig_vars/usa_twig_vars.module`

**Modified the `usa_twig_vars_preprocess_region()` function to:**

- Load config_pages entities of type 'site_banner'
- Check language matches the current site language (or is undefined/language-neutral)
- **Pre-render paragraph entities using the paragraph view builder** for static site compatibility
- Extract multiple referenced alert paragraphs from the config_pages field_alert field
- Add pre-rendered config_pages site banners to the `config_page_alerts` array for template rendering
- Support multiple paragraphs displaying as separate banners

**Language Handling:**
- Config_pages uses a **context field** (not entity langcode) to store language-specific data
- When language context is enabled, the context field contains serialized data like: `a:1:{i:0;a:1:{s:8:"language";s:2:"en";}}`
- The preprocess function extracts and checks the language from the context field
- Config_pages with `NULL` context language appear on all language variants
- Config_pages with `en` context appear only on English pages
- Config_pages with `es` context appear only on Spanish pages
- Each language version is a separate config_pages entity, managed independently through the context system

**Added `usa_twig_vars_theme_suggestions_paragraph_alter()` function to:**

- Detect when a uswds_alert paragraph is being used in a config_pages site banner
- Suggest the custom template for config_pages site banners

#### 2. `/web/themes/custom/usagov/templates/region--header-top.html.twig`

**Updated to:**

- Render pre-rendered config_pages site banners (already rendered in preprocess)
- Loop through multiple paragraphs when present
- Remove all references to block-based site banners (completely removed)
- No longer uses `drupal_entity()` - receives pre-rendered HTML instead

#### 3. `/web/themes/custom/usagov/templates/paragraph--uswds-alert--config-pages-site-banner.html.twig`

**Created custom template that:**

- Inherits all styling from the standard uswds_alert paragraph template
- Adds `data-analytics="site-banner"` attribute for analytics tracking
- Maintains all accessibility features and USWDS styling
- Supports all alert types (info, warning, error, success)
- Includes proper language support for English and Spanish

## How It Works

### Config Pages Setup

1. A config_pages entity of type 'site_banner' exists (managed via Config Pages module)
2. This config page has a `field_uswds_paragraphs` field that can reference multiple uswds_alert paragraphs
3. Each referenced paragraph displays as a separate banner on the site

### Rendering Process

1. **Region preprocessing**: `usa_twig_vars_preprocess_region()` loads the config_pages site_banner entity and extracts all referenced paragraphs
2. **Template rendering**: `region--header-top.html.twig` loops through each config_pages alert paragraph and renders them
3. **Template suggestion**: `usa_twig_vars_theme_suggestions_paragraph_alter()` suggests the custom template for config_pages site banners
4. **Custom styling**: The custom template applies analytics attributes and ensures consistent styling

### Benefits of This Approach

1. **Centralized Management**: All site banners managed in one place via Config Pages
2. **Multiple Banner Support**: Can display multiple alert banners simultaneously
3. **Analytics Tracking**: Each banner includes the `data-analytics="site-banner"` attribute
4. **Maintainability**: Uses the existing USWDS alert paragraph system
5. **Flexibility**: Supports all USWDS alert types and configurations
6. **Accessibility**: Maintains all accessibility features from the original implementation
7. **Language Support**: Works with both English and Spanish content

## Usage

### For Content Administrators

#### Creating a Site Banner

1. Navigate to the Site Banner config page at `/admin/content/site-banner`
2. Add or edit USWDS Alert paragraphs in the "Alert" field
3. Each paragraph will display as a separate banner in the header_top region
4. To add multiple banners, simply add multiple alert paragraphs to the field

#### Creating English and Spanish Versions

**Using Language Context (Current Implementation)**

Config Pages uses a "context" system to create language-specific versions. With language context enabled:

1. **For English banner:**
   - Go to `/admin/content/site-banner`
   - When viewing/editing, you'll see this is the English (en) context
   - Create your English alert banner(s)

2. **For Spanish banner:**
   - The system automatically creates a separate config_pages entity for Spanish
   - You can access it by switching to the Spanish context
   - Create your Spanish alert banner(s) separately

3. **How it works:**
   - The system loads the appropriate config_page based on the current site language
   - English pages (`www.usa.gov`) show the English context banner
   - Spanish pages (`www.usa.gov/es`) show the Spanish context banner
   - Each is a completely separate entity

**Note:** Config Pages v8.x-2.19 doesn't fully support Drupal's content translation system (it lacks `langcode` in entity_keys), so we use the language context feature instead. This provides the same functionality - separate English and Spanish banners that appear automatically on their respective language pages.

### For Developers

- The system exclusively uses Config Pages - no block-based banners remain
- Multiple paragraphs automatically render as separate banners
- The same CSS classes and analytics attributes are applied to all banners
- Template suggestions ensure the custom template is used for config_pages banners

## Technical Details

### Key Variables Added

- `config_page_alerts`: Array of alert paragraphs from config_pages, keyed by config_page ID
- Each config page can contain multiple paragraphs that display as separate banners

### Template Naming Convention

- Custom template: `paragraph--uswds-alert--config-pages-site-banner.html.twig`
- Template suggestion: `paragraph__uswds_alert__config_pages_site_banner`

### CSS Classes Applied

- All standard USWDS alert classes
- Additional classes based on alert status (info, warning, error, success)
- Slim and no-icon modifiers supported
- Analytics attribute: `data-analytics="site-banner"`

## Testing

To verify the implementation:

1. Check that config_pages site banners appear in the header_top region
2. Verify banners have the correct USWDS alert styling
3. Confirm the `data-analytics="site-banner"` attribute is present on each banner
4. Test multiple banner functionality by adding multiple alert paragraphs
5. Verify accessibility features are maintained (screen reader support, proper ARIA labels)
6. Confirm no block-based banner functionality remains
