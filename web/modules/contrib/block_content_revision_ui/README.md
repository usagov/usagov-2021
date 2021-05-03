Block Content Revision UI

Copyright (C) 2018 Daniel Phin (@dpi)

This module takes advantage of work in progress Drupal core patches to add
generic revision UI to block content.

# License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

# Instructions

 1. Requires core patches. See *Patches* section below
 2. Assign appropriate user permissions. See 'Block Content Revision UI' section
    in _/admin/people/permissions_.

# Patches

## Adds generic revision UI

https://www.drupal.org/node/2350939

Adds the ability for entities other than node to use a revision UI. Including
revert form, and history list.

## Add revision parameter to relevant routes

https://www.drupal.org/project/drupal/issues/2927077
   
The revision history page uses $entity->toUrl('revision-revert-form'), but
toUrl does not yet add block_content_revision parameter automatically.

The patch adds the appropriate parameter.  