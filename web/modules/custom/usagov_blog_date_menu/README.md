# USAGov Blog Date Menu

A Drupal module that automatically generates and maintains a hierarchical navigation menu for blog posts organized by publication date.

## Functionality Overview

The USAGov Blog Date Menu module provides automated blog navigation for Drupal sites by creating a dynamically updated menu structure that organizes blog posts chronologically.

### Key Features

- **Automatic Menu Generation**: Creates a hierarchical menu structure (Our Blog → Year → Month → Blog Post) without manual intervention
- **Real-time Updates**: Automatically rebuilds the menu whenever blog posts are created, updated, or deleted
- **Smart Breadcrumb Navigation**: Provides contextual breadcrumbs for blog posts and archive pages that reflect the hierarchical structure
- **SEO-Friendly URLs**: Supports clean URL patterns like `/blog/2025/07` for monthly archives
- **Chronological Ordering**:
  - "Our Blog" links to `/blog` as the top-level entry point
  - Years are ordered newest-first under "Our Blog"
  - Months within years are ordered newest-first
  - Blog posts within months are ordered by publication date (newest-first)

### How It Works in Drupal

The module integrates seamlessly with Drupal's content management workflow:

1. **Content Creation**: When editors create new blog posts, the menu automatically updates to include the new content in the appropriate date hierarchy
2. **Menu Structure**: The module creates a menu called "USAGov Blog Menu" that can be placed in any menu block or navigation region
3. **Breadcrumb Integration**: Works with the Menu Breadcrumb module to provide consistent navigation breadcrumbs across blog pages
4. **Cache Management**: Intelligently invalidates relevant caches to ensure menu updates are immediately visible (on CMS; static site will still have to wait on Tome rebuild)

### User Experience

- **Blog Visitors** can easily browse content by year and month, making it simple to find posts from specific time periods
- **Content Editors** don't need to manually maintain menu structures - the system handles all organization automatically
- **Site Administrators** benefit from reduced maintenance overhead while providing better content discoverability

## Technical Implementation

### Architecture

The module uses Drupal's entity and menu systems to create a self-maintaining navigation structure. It leverages several core Drupal APIs:

- **Entity API**: Hooks into node CRUD operations to trigger menu rebuilds
- **Menu API**: Programmatically creates and manages menu link content entities
- **Cache API**: Implements intelligent cache invalidation for performance
- **Breadcrumb API**: Overrides default breadcrumb behavior for blog-related pages

#### Menu Management

- `usagov_blog_date_menu_rebuild_menu()`: The primary function that reconstructs the entire menu structure
- Queries all blog post nodes and groups them by year and month using the node's `created` timestamp
- Creates a four-level hierarchy: Our Blog → Year → Month → Individual Posts
- Implements proper weight-based sorting for consistent chronological ordering

#### Entity Hooks

The module implements three entity hooks to maintain menu synchronization:

- `usagov_blog_date_menu_entity_insert()`: Triggers when new blog posts are created
- `usagov_blog_date_menu_entity_update()`: Triggers when blog posts are modified
- `usagov_blog_date_menu_entity_delete()`: Triggers when blog posts are removed

Each hook invalidates the `node_list:blog_post` cache tag and calls the menu rebuild function.

#### Breadcrumb System

- `usagov_blog_date_menu_system_breadcrumb_alter()`: Main breadcrumb handler that routes to specific breadcrumb builders
- `usagov_blog_date_menu_build_blog_post_breadcrumb()`: Creates breadcrumbs for individual blog posts
- `usagov_blog_date_menu_build_breadcrumb()`: Handles year and month archive page breadcrumbs
- `usagov_blog_date_menu_build_blog_listing_breadcrumb()`: Manages the main blog listing page breadcrumb

### Data Flow

1. **Content Operation**: A blog post is created, updated, or deleted
2. **Hook Execution**: Appropriate entity hook fires
3. **Cache Invalidation**: Relevant cache tags are invalidated
4. **Menu Rebuild**: Complete menu structure is regenerated from current blog post data
5. **Menu Link Creation**: New MenuLinkContent entities are created with proper hierarchy and weights
6. **Breadcrumb Updates**: Custom breadcrumb logic provides navigation context based on current page

### Performance Considerations

- **Cache Strategy**: Uses targeted cache tag invalidation (`node_list:blog_post`) rather than clearing all caches
- **Batch Operations**: Menu rebuilding happens synchronously but could be moved to a queue for sites with very large numbers of blog posts
- **Query Optimization**: Loads all blog posts in a single query rather than multiple database calls

### Dependencies

- **Core Requirement**: Drupal 8, 9, or 10
- **Module Dependency**: Menu Breadcrumb module for enhanced breadcrumb functionality
- **Content Type**: Assumes a "blog_post" content type exists

### URL Structure Support

The module's breadcrumb system recognizes and handles these URL patterns:

- `/blog` - Main blog listing
- `/blog/YYYY` - Year archive (e.g., `/blog/2025`)
- `/blog/YYYY/MM` - Month archive (e.g., `/blog/2025/07`)
- `/node/[nid]` - Individual blog post pages