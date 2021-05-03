# Scheduler

[![Build Status](https://travis-ci.org/jonathan1055/scheduler.svg?branch=8.x-1.x)](https://travis-ci.org/jonathan1055/scheduler)

Scheduler gives content editors the ability to schedule nodes to be published
and unpublished at specified dates and times in the future.

Scheduler provides hooks and events for third-party modules to interact with
the processing during node edit and during cron publishing and unpublishing.

For a fuller description of the module, visit the [project page on Drupal.org](https://drupal.org/project/scheduler)

## Requirements

 * Scheduler uses the following Drupal 8 Core components:
     Actions, Datetime, Field, Node, Text, Filter, User, System, Views.

 * There are no special requirements outside core.

## Integration with other modules

 * [Rules](https://www.drupal.org/project/rules):
     Scheduler provides actions, conditions and events which can be used in
     Rules to build additional functionality.

 * [Token](https://www.drupal.org/project/token):
     Scheduler provides tokens for the two scheduling dates.

 * [Devel](https://www.drupal.org/project/devel):
     When generating new test content via Devel Generate, Scheduler can add
     publishing dates automatically.

 * [Scheduler Content Moderation Integration](https://www.drupal.org/project/scheduler_content_moderation_integration)
     If you use core Content Moderation then you should also install this
     sub-module, contributed by the folks at [Thunder](https://www.drupal.org/thunder)

## Installation

 * Install as you would normally install a contributed Drupal module. See:
     https://drupal.org/documentation/install/modules-themes/modules-8
     for further information.

## Configuration

 * Configure user permissions via url /admin/people/permissions#module-scheduler
   or Administration » People » Permissions

   - View scheduled content list

     Users can always see their own scheduled content, via a tab on their user
     page. This permissions grants additional authority to see the full list of
     scheduled content by any author, providing the user also has the core
     permission 'access content overview'.

   - Schedule content publication

     Users with this permission can enter dates and times for publishing and/or
     unpublishing, when editing nodes of types which are Scheduler-enabled.

   - Administer scheduler

     This permission allows the user to alter all Scheduler settings. It should
     therefore only be given to trusted admin roles.

 * Configure the Scheduler global options via /admin/config/content/scheduler
   or Administration » Configuration » Content Authoring

   - Basic settings for date format, allowing date only, setting default time.

   - Lightweight Cron, which gives sites admins the granularity to run
     Scheduler's functions only, on more frequent crontab jobs.

 * Configure the Scheduler settings per content type via /admin/structure/types
     or Administration » Structure » Content Types » Edit

## Troubleshooting

 * To submit bug reports and feature requests use
     https://drupal.org/project/issues/scheduler

 * To get help with crontab jobs, see https://drupal.org/cron

## Maintainers

Current maintainers:
- [Pieter Frenssen](https://www.drupal.org/u/pfrenssen) 2014(6.x)-current
- [Jonathan Smith](https://www.drupal.org/u/jonathan1055) 2013(6.x)-current

Previous maintainers:
- [Rick Manelius](https://www.drupal.org/u/rickmanelius) 2013(6.x)-2014(7.x)
- [Eric Schaefer](https://www.drupal.org/u/eric-schaefer) 2008(5.x)-2013(7.x)
- [Sami Kimini](https://www.drupal.org/u/skiminki) 2008(5.x)
- [Ted Serbinski](https://www.drupal.org/u/m3avrck) 2007(4.7)
- [Andy Kirkham](https://www.drupal.org/u/ajk) 2006(4.7)-2008(6.x)
- [David Norman](https://www.drupal.org/u/deekayen) 2006(4.x)
- [Tom Dobes](https://www.drupal.org/user/4179) 2004(4.x)
- [Gábor Hojtsy](https://www.drupal.org/u/gábor-hojtsy) 2003(4.3)-2005(5.x)
- [Moshe Weitzman](https://www.drupal.org/u/moshe-weitzman) 2003(4.2)-2006(4.6)
