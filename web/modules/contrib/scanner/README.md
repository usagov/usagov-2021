# Search & Replace Scanner

The Search and Replace Scanner can do regular expression matches against a
configurable list of text fields. This is useful for finding html strings that
Drupal's normal search will ignore. It is then possible to replace the matched
text. This can be useful, for example, to change the name of a company, or are
changing the URL of a link included multiple times in multiple nodes.

## Warning!

This is a very powerful tool, and as such is very dangerous. It is possible to
destroy an entire site with it. Be sure to backup the database before using it.
No, really.

## Features

* Plain text search and replace.

* Regular expression search and replace.

* Case sensitive search option.

* Plain text search allows 'whole word' matching option. For example,
  searching for "run" with the whole word option selected will filter out
  "running" and "runs", but will match "run!".

* Text can be specified that should precede or follow the search text in order
  for a match to be valid.

* Out of the box the modules works with nodes and paragrahps, but any entity
  (e.g. taxonomy terms) could be handled by writting a plugin.

* Can limit search and replace to published nodes only.

* Can search and replace on `string` (titles), `long_text` (formatted, no
  summary), and `text_with_summary` (formatted with summary) fields.

* Searches can be limited to specific fields in specific entity types.

* Will save a new revision when a replacement is made, in case it is
  necessary to revert a change.

* Provides an undo option that allows reverting all entities that were
  updated in a specific replacement action.

* Updates summary part of text fields that use 'Text area with a summary'
  widget.

* Will dynamically expand PHP's maximum execution time for scripts on servers
  that support it. This allows complex queries on large bodies of content.

* Search results for searches and replacements can be themed.

## Requirements

This module has no dependencies, besides Drupal core.

## Installation

Install the same as any other module; see https://www.drupal.org/node/1897420
for further details.

1. Place the entire scanner folder in the modules/contrib directory.

2. Go to Manage -> Extend and enable the scanner module.

3. Go to Manage -> People -> Permissions and adjust the permissions as
  necessary. It is possible to control which roles can administer the module --
  e.g., determine which fields can be scanned and modify defaults -- and which
  roles can use the module. This is is useful if, for example, only people with
  a "site admininistrator" role to control the module's settings, but allow
  people with the "content manager" role to be able to perform search and
  replace actions.

4. Go to the Scanner administration panel and select which fields to include in
  search and replace actions. More information on that is available below in
  the Administration Options section.

## Configuration

The Scanner admin settings may be managed in two ways:

- Go to "Administration" -> "Configuration" -> "Search and Replace Scanner".

- Go to "Administration" -> "Content" -> "Search and Replace Scanner" and
  select the "Settings" tab.

A. Default Options:

In this section it is possible to control the defaults for several search
options that Scanner users will see when they use the search and replace form.
Users will still be able to change the options on their own, but the defaults
can make things easier for them if they're likely to only perform one kind of
search most of the time.

It is also possible to select whether teasers for nodes should be rebuilt after
a replacement has been made to the body or other fields for that node. Most
admins will select this option, because it ensures that teasers are in synch
with node content. But see section II.A above for more info on why leaving
this option unselected might be helpful.

If ther site has vocabularies and terms set up in the Taxonomy module, they can
be used to restrict search and replace actions to nodes that contain terms in a
given category (a.k.a "vocabulary"). Select the vocabularies that are to be used
to restrict access. When users go to the search and replace form, they will have
the option of selecting one or more terms from those vocabularies for limiting
their searches.

B. Fields that can be searched:

The most important part of administering Scanner is making sure to select one
or more options in the "Fields that can be searched" section. Fields are listed
in [nodetype: fieldname] format. To only allow people with access to Search and
Replace on the Body field of the Basic Page content type, select [page: body].

## Troubleshooting

* A user can only have one instance of Search and Replace Scanner running at a
  time. Attempting to open Scanner in two separate windows to perform
  replacements at the same time can lead to unknown errors if a timeout is
  encountered.

* Only works on sites using a MySQL database.

* Large search and replace actions may not be completed on sites that are hosted
  in environments where PHP's `max_execution_time` variable can't be dynamically
  expanded. The module automatically attempts to expand the maximum execution
  time of a script to 10 minutes (it's often set at 2 minutes). If the
  website's hosting service doesn't allow adjusting this variable dynamically,
  it may be possible to ask the hosting provider to make the change for the
  account.

## Credits / contact

The best way to contact the authors is to submit an issue, be it a support request, a feature request or a bug report, in the [project issue queue](https://www.drupal.org/project/issues/metatag).

Currently maintained by [Damien McKenna](https://www.drupal.org/u/damienmckenna), [codebymikey](https://www.drupal.org/u/codebymikey), and [Shreya Shetty](https://www.drupal.org/u/shreya-shetty). Drupal 8/9 port by Shreya Shetty, [Christian Crawford](https://www.drupal.org/u/ccrawford91), [Ana Colautti](https://www.drupal.org/u/anacolautti), codebymikey and Damien McKenna.

Version 7.x-1.x was written and maintained by [Brett Birschbach](https://www.drupal.org/u/HitmanInWis),
[Eric Rasmussen](https://www.drupal.org/u/ericras), [Michael Rossetti](https://www.drupal.org/u/MikeyR), [Yonas Yanfa](https://www.drupal.org/u/fizk) and Damien McKenna.

Version 6.x-1.x was written and maintained by [Amit Asaravala](https://www.drupal.org/u/aasarava).

Version 5.x-2.x was written and maintained by Amit Asaravala, [Jason Salter](https://www.drupal.org/u/jpsalter); Sponsored by [Five Paths Consulting](http://www.fivepaths.com).

Version 5.x-1.x was written by [Tao Starbow](https://www.drupal.org/u/starbow).
