INTRODUCTION
------------
This module integrates the CKEditor templates plugin.

It provides a dialog to offer predefined content templates - with page layout,
text formatting and styles. Thus, end users can easily insert pre-defined
snippets of html in CKEditor fields.

* For a full description of the module, visit the project page:
   https://www.drupal.org/project/ckeditor_templates

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/ckeditor_templates?categories=All

AUDIENCE
---------
This module is intended for themers who can manage custom ckeditor templates
from their theme. As is, it doesn't provide any fonctionnality.

REQUIREMENTS
------------
This module requires to install the CKEditor "Templates" plugin.
http://ckeditor.com/addon/templates

INSTALLATION
------------
Download the CKEditor "Templates" plugin on the project page :
http://ckeditor.com/addon/templates
Create a libraries folder in your drupal root if it doesn't exist
Extract the plugin archive in the librairies folder

Then install this Drupal module as you would normally install a contributed
Drupal module. Visit:
https://www.drupal.org/documentation/install/modules-themes/modules-8 for
further information.

CONFIGURATION
------------
- First, you need to add the plugin button in your editor toolbar.
Go to the format and editor config page and click configure on the format your
want to edit :
http://drupalvm.dev/admin/config/content/formats
- Add the templates button to the toolbar
- copy the file ckeditor_templates.js.example from the module templates folder
to your theme templates folder, rename it without .example and customize it :
    x edit the image_path variable to link to your thumbnail folder
    x copy the standard images from the libraries/templates/templates/images
    folder and place them in the folder created previously (image_path).
    x change the templates array in your custom ckeditor_templates.js to include
    any custom templates you want your users to have access to.

If you want to place your template file in a different folder, you can set the
path on the Editor config page.

If you have a particular setup with non standard path and your template file is
not found, you can always specify any custom path in the Editor Config Page,
found at "Configuration", "Text formats and editors". Choose the editor type you
want to expose the templates to and set path parameter at "Templates",
"Templates Definition File", in the "CKEditor plugin settings" section.

WARNING
--------
Depending on the configuration of your formats, CKEditor can be restrictive
about authorized HTML tags. Make sure to use compatible HTML tags in your
templates.

ROAD MAP
---------
Two features could be added :
- Allowing to add multiple template files so that you don't have to write all
your templates in one big file
- Allow to restrict for one editor the template displayed. Thus you could have
10 templates in a file and display only 3 of them on a specific format.


MAINTAINERS
-----------

Current maintainers:
 * Lucas Le Goff (lucaslg) - https://www.drupal.org/user/3128975

This project has been sponsored by:
 * Micropole
   Visit https://www.micropole.com for more information.
