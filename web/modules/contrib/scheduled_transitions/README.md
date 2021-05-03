Scheduled Transitions

https://www.drupal.org/project/scheduled_transitions

# Tips

Scheduled Transitions are run in a two step process, first, Drupal queue items
are created after a transition is ready to be run (`drush sctr-jobs`). The 
scheduled update is also locked from being added to queues in the meantime.

Then, the queue should be run, it can be executed during regular cron, or you
can force it with a `drush queue:run` command.

```sh
# Create queue items.
drush scheduled-transitions:queue-jobs
# Process the queue.
drush queue:run scheduled_transition_job
```

Ideally, each of these commands should be run very often. Preferably every 1-5
minutes.

# License

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
