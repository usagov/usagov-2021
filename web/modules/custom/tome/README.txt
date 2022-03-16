ABOUT TOME
==========

Note: For the most up to date documentation, visit https://tome.fyi/

Tome is a static site generator, and a static storage system for content.

When you use Tome, everything about your Drupal site is stored as files. There
is no persistent SQL database or file system, and the only time Drupal is
running is when you’re using it locally. As you locally edit content, your
changes are automatically exported to the filesystem and ready for commit. Once
committed and pushed, others can pull your changes down and build a fresh site
that looks exactly as it did on your local machine. When the repository is
looking good, you can generate the static site and ship it to production.

INSTALLATION
============

When you use Tome on a new or existing Drupal site for the first time, you'll
want to do an initial export of your config, content, and files. To do this,
run "drush tome:export". You should probably commit this as well.

When you need to re-install Drupal, run "drush si <profile> -y", then run
"drush tome:import -y".

CONFIGURATION
=============

Tome uses settings to determine where to export. The settings you can configure
in settings.php are:

 - tome_files_directory: Where files are exported. Defaults to ../files
 - tome_content_directory: Where content is exported. Defaults to ../content
 - tome_static_directory: Where HTML is exported. Defaults to ../html
 - tome_book_outline_directory: Where books are exported. Defaults to ../extra
 - tome_static_cache_exclude: An array of paths to always exclude from cache.
 - tome_static_path_exclude: An array of paths to exclude from static site
   generation. Useful for system paths.

Config is exported to your config sync directory. It's recommended that you set
this to "../config" with a settings.php line like:

```
$config_directories['sync'] = '../config';
```

USE
===

In general, you should not need to run tome:export after the initial install.
Any edits to config or content will be automatically synced to your output
directory.

There is a special Tome command, "drush tome:clean-files" that will delete any
files that appear unused. This is a temporary workaround to a systemic Drupal
issue with file usage and automatic deletion.

When your site is looking good and ready for production, you can export static
HTML. To do this, run "drush tome:static". From there you can upload your HTML
files to any web hosting provider.

USING SUB-MODULES
=================

Tome is split into two sub-modules that are automatically installed when you
install Tome. Depending on your use case, you may want to install and use them
separately:

 - Tome Static: The Tome Static sub-module handles static site (HTML)
 generation, and is useful if you are comfortable with your existing Drupal
 stack, but still want to run a static site on production.
 - Tome Sync: The Tome Sync sub-module handles static content export and
 import, and is useful if you want to build your site from scratch, or prefer
 to work with your content as JSON.

See https://tome.fyi/docs/sub-modules/ for more details.

IMPROVING TOME STATIC PERFORMANCE
=================================

For Tome Static users who have run into performance problems like:

 - Clearing all cache in the UI or with "drush cr" completely wipes Tome Static
 cache.
 - Views uses list cache tags (i.e. node_list) which means that any node save
 results in all Views having their cache cleared.

There is a sub-module available, "Tome Static Super Cache", which changes core
caching behavior to improve Tome Static performance.

Enabling the sub-module will generally improve Tome Static performance, as it
will start ignoring certain cache tag invalidation and prevent Tome Static
cache from being wiped when rebuilding cache. To fully rebuild cache with the
module enabled, you can click the "Fully clear caches" button at
`/admin/config/development/performance`, or run "drush tscr".

Tome Static Super Cache also provides a Views cache plugin ("Smart tag based")
that does not use list cache tags, which should be used on all Views that it
works on. This plugin partially executes all Views on entity create/update, and
determines if that entity would show up in the View. If so, a custom View
specific cache tag is cleared.

RUNNING TOME STATIC ON CRON
===========================

If you're running a persistent Drupal site and want to generate a static site
with cron, you can enable the tome_static_cron sub-module, which queues all
uncached paths when cron runs and works through them until the cache is full.

To use tome_static_cron, install the module then visit
/admin/config/services/tome_static_cron/settings to enter the base URL to use
for static cron generations.

MODIFYING TOME SYNC FILE HANDLING
=================================

By default, when file entities are exported, imported, or deleted, Tome Sync
will keep the file export directory and your public file directory in sync by
performing copies or deletes.

This may not be desirable if you prefer to just symlink your files directory
to a directory tracked by Git, or do not want to track files in Git at all and
prefer to use persistent storage.

In those cases, you can override the file handling service to use an alternate
class that does nothing on file sync operations. To do this, add this block of
code to your per-site services.yml file:

```
services:
  tome_sync.file_sync:
    class: Drupal\tome_sync\NullFileSync
```
