# Siteimprove Plugin

## Introduction
The Siteimprove plugin bridges the gap between Drupal and the Siteimprove
Intelligence Platform. You are now able to put your Siteimprove results to use
where they are most valuable – during your content creationand editing
process. With analytics and content insights always at hand, contributors
can test, fix, and optimize their work continuously. Once the detected issues
have been assessed, you can directly re-recheck the relevant page and see if
further actions are needed. Delivering a superior digital experience has never
been more efficient and convenient.

## Installation and configuration
Siteimprove Plugin can be installed like any other Drupal module.
Place it in the modules directory for your site and enable it
on the `admin/modules` page.

Visit the Siteimprove Plugin settings page and regenerate auth token
if you need it.

## Frontend Domain Plugin ##
If a Drupal site has a domain for the frontend site that is different from the
backend domain, this can be configured with existing plugins, or you can make
your own.

Included with the module are two plugins for (1) the default case where the
frontend domain is the same as the backend domain, and (2) the frontend domain
is a single domain and doesn't need advanced configuration.

If you need Domain Access support, you can install the module
siteimprove_domain_access. This module doesn't need to be configured, just
enable it and it will make sure Siteimprove is notified with the correct
url(s) for a given entity.

### Adding your own plugin ###
Use the annotation discovery method with the annotation `@SiteimproveDomain`.
This annotation has the variables `id`, `label` (translatable), and
`description` (translatable). See the plugin Simple
(src/Plugin/SiteimproveDomain/Simple.php) for a plugin with no additional
configuration, and the plugin Single (src/Plugin/SiteimproveDomain/Single.php)
for a plugin with configuration.

The plugin uses the ConfigFormBase trait, so if you want to save configuration
for your plugin, you have to add the function `getEditableConfigNames()`, that
should return a list of setting names. You can see the Single plugin for how to
save configuration in the `submitForm()` function, or - if you need a more
advanced example - see the module siteimprove_domain_access.

The following methods are required in a custom plugin: `buildForm()` (will be
displayed on the Siteimprove settings page) and `getUrls()` (returns the urls
for the current entity including http scheme "http://" or "https://").

The following methods are optional: `validateForm()` (validates the form, if
your plugin requires configuration), `submitForm()` (saves any changes to the
plugin's configuration), `getEditableConfigNames()` (returns a list of setting
names).

## Frequently Asked Questions
Who can use this plugin?
The plugin requires a Siteimprove subscription to be used.
Signup for a [FreeTrial](https://siteimprove.com/account/create "Free trial")
to test it out.

Where can I see the overlay?
The overlay is visible when editing a page, viewing a page and on latest
revision when content moderation is enabled and configured.

I don't see the overlay, whats wrong?
- Did you grant your users permissions to access the siteimprove plugin?
- Did you remember to turn off your adblocker? Some adblockers does not like
our iframe overlay.
