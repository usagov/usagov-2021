# Site Banner Feature for Drupal CMS

## Overview
This feature allows Content Administrators to add a banner to the `header_top` region of the site. The following sections will explain the technical details of the feature, related files, and a step-by-step guide for adding a site banner using the CMS.

## Technical Details

The feature introduces a new block type called **Site Banner**. This block uses the [Alert component from USWDS](https://designsystem.digital.gov/components/alert/). Content administrators are granted permissions to create and edit only Site banner blocks, but not other block types.

To control the placement of Site Banners without giving full block placement permissions, a checkbox has been added to the Site Banner edit form. This checkbox allows content admins to decide if the banner should be placed in the `header_top` region or removed from it.

### Files Involved
- `web/modules/custom/usa_twig_vars/usa_twig_vars.module`
  - The `usa_twig_vars_preprocess_region` hook checks the value of the placement checkbox for each site banner in the site and builds an array called `site_banners`. The value of each element in this array can be the id of the site banner if the checkbox `place_above_header` is checked or `NULL` if the checkbox is not checked.
- `web/themes/custom/usagov/templates/field--block-content--field-place-above-header.html.twig`
  - For some reason the checkbox added text below the site banner so I added this empty file to prevent this text from appearing.
- `web/themes/custom/usagov/templates/region--header-top.html.twig`
  - The `site_banners` array created in the hook is used in this file to add or remove the banner from the `header_top` region. To do this we use Twig Tweak, specifically the `drupal_entity` function.

## Adding a Site Banner: Step-by-Step Guide

1. Log in to the Drupal CMS
2. Go to the Content blocks page `/admin/content/block`
3. Click `+ Add content block`.
4. Select `Site Banner` as the block type.
   Note: If you are in a content admin account this will be the default since you only have permissions to create this type of blocks.
5. Fill in the required fields for the Site Banner.
   - Make sure you select the correct language for the banner.
6. Find the checkbox labeled `Place above header`.
   - Check this box if you want the banner to appear in the `header_top` region.
   - Uncheck this box if you want to remove the banner from the `header_top` region.
7. Click `Save` to create or update the Site Banner block.
8. Visit the site to ensure the banner appears or is removed from the region.
