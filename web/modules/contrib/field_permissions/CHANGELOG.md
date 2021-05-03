## 8.x-1.0-beta1

* Remove old 7.x .info file.
* Convert README to markdown
* Move README to markdown.
* [#2768867](/node/2768867) by [jhedstrom](/u/jhedstrom): Utilize the UserCreationTrait
* [#2765049](/node/2765049) by [jhedstrom](/u/jhedstrom): Add some additional unit tests
* Add a composer.json file.
* Removing the LICENSE file

## 8.x-1.0-alpha1

* Coding standards fixes.
* [#2764479](/node/2764479) by [jhedstrom](/u/jhedstrom): Split admin form methods into separate interface from FieldPermissionTypeInterface
* [#2764425](/node/2764425) by [jhedstrom](/u/jhedstrom): Cleanup the report logic to make use of plugins
* [#2764405](/node/2764405) by [jhedstrom](/u/jhedstrom): Add explicit tests for views integration
* [#2757267](/node/2757267) by [jhedstrom](/u/jhedstrom): Convert the field permissions types to plugins
* [#2758785](/node/2758785) by [jhedstrom](/u/jhedstrom): Remove underscore in permission names
* [#2758783](/node/2758783) by [jhedstrom](/u/jhedstrom): Rename some service methods for clarity
* [#2758139](/node/2758139) by [jhedstrom](/u/jhedstrom): Test the field permission report controller
* [#2758757](/node/2758757) by [jhedstrom](/u/jhedstrom): Fix the field permissions report page
* [#2757433](/node/2757433) by [jhedstrom](/u/jhedstrom): Utilize 3rd party settings API instead of config for field permissions
* [#2758059](/node/2758059) by [jhedstrom](/u/jhedstrom): Move web tests to BrowserTestBase
* [#2757431](/node/2757431): Move away from static methods in field permission service
* [#2757299](/node/2757299) by [jhedstrom](/u/jhedstrom): The comment module is currently required
* [#2756685](/node/2756685) by [jhedstrom](/u/jhedstrom): Test cleanup
* [#2756593](/node/2756593) by [jhedstrom](/u/jhedstrom): Interface cleanup
* Some automated cleanup via phpcbf.
* Remove old admin files
* Remove 7.x tests
* Remove redundant constant define statements.
* Remove old install file
* Merge branch '8.x-1.x' of https://github.com/drugo32/field_permissions into 8.x-1.x-initial
* [#1321050](/node/1321050) by [fangel](/u/fangel), [david_rothstein](/u/david_rothstein), [rob-loach-|-thekevinday](/u/rob-loach-|-thekevinday): Additional safe-guard for entities other than nodes when it comes to entity ownership.
* [#1063960](/node/1063960): Prepare 7.x-1.0-beta1.
* [#1312596](/node/1312596): Clean up.
* [#1312596](/node/1312596): Merge settings.css into admin.css and access.inc into .module.
* [#1312596](/node/1312596) by [rob-loach](/u/rob-loach): Clean up the module structure.
* [#1307312](/node/1307312) by [rob-loach](/u/rob-loach): Remove troubleshooting interface.
* [#876550](/node/876550) by [rob-loach](/u/rob-loach): Ignore the Drupal core thrown exception with a TODO to fix the core bug.
* [#1230284](/node/1230284) by [zhgenti](/u/zhgenti), [david_rothstein-|-dboulet](/u/david_rothstein-|-dboulet): Use form_load_include() instead of module_load_include() to fix form submits.
* [#1298966](/node/1298966) by [david_rothstein](/u/david_rothstein): Passing tests.
* [#1308210](/node/1308210) by [david_rothstein](/u/david_rothstein): Update the README.txt for the new user interface.
* [#1114134](/node/1114134) by [geerlingguy](/u/geerlingguy), [joelstein](/u/joelstein): Added Remove dependency on Fields UI.
* [#876550](/node/876550) by [sebcorbin](/u/sebcorbin), [david_rothstein-|-abbasmousavi](/u/david_rothstein-|-abbasmousavi): Fix for objects other than nodes.
* [#1306780](/node/1306780) by [david_rothstein](/u/david_rothstein): Private fields should allow administrators view access.
* [#1298966](/node/1298966) by [gabor-hojtsy](/u/gabor-hojtsy): Initial tests.
* [#1279712](/node/1279712) and [#1141330](/node/1141330) by [david_rothstein](/u/david_rothstein), [jeff-noyes](/u/jeff-noyes), [stellina-mckinney](/u/stellina-mckinney), [gabor-hojtsy](/u/gabor-hojtsy): Revamp Field Permissions UI.
* [#1073284](/node/1073284) by [joelstein](/u/joelstein), [rob-loach](/u/rob-loach): Assign administrator role permissions when new field permissions are opened.
* [#1063960](/node/1063960): Prepare for 7.x-1.0-alpha1.
* [#1043522](/node/1043522) by [erikwebb](/u/erikwebb): Permissions administration link wrong on Edit Field page.
* [#1063162](/node/1063162) by [jide](/u/jide), [rob-loach](/u/rob-loach): Field Permissions not accessible for some fields.
* [#965110](/node/965110) by [danic](/u/danic): Move Field Permissions UI to Reports.
* [#965094](/node/965094) by [danic](/u/danic), [rob-loach](/u/rob-loach): Group Title and Description in modules page.
* Stripping CVS keywords
* Fix node revisions table name.
* Sync code base from 6.x-1.0 release.
* Added support for create field permission. - Every permission type can be enabled independently. - Warning: This branch does not work yet!
* Initial port to D7.
* Initial checkin for the Field Permissions module.
