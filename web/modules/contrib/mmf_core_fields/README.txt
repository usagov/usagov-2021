CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Limitations
 * Installation
 * Configuration


INTRODUCTION
------------

The 'Minimum Multiple Fields(MMF): Core Fields' module allows
the administrator to specify a minimum number of Drupal core-fields
to appear in the content add form.

This module specifically works for available default core-fields
which are configured 'Allowed number of values: UNLIMITED'.

Agenda is to initially show
a specific(say, 3) number of fields at content add form,
against the default behaviour that shows only one field.

Although, it has some limitations. 
Please check LIMITATIONS section for more details.

 
LIMITATIONS
------------

This module currently supports following Drupal core-fields' widget:
 * Textfield -[Text (plain)]
 * Email
 * Link
 * Date and time
 * Number (decimal)
 * Number (float)
 * Number (integer)
 * Text field -[Text(formatted)]
 * Text area (multiple rows) -[Text(formatted, long)]
 * Text area (multiple rows) -[Text(plain, long)]
 * Text area with a summary -[Text (formatted, long, with summary)]

MMF is not implemented for below fields as they have
different meaning to 'Allowed number of values: UNLIMITED'
in other words, widgets to these fields do not have ADD MORE BUTTON.
 * Select List
 * Check boxes/radio buttons
 * Autocomplete
 * Autocomplete (Tags style)
 * Single on/off checkbox

We are still working to implement MMF for below fields' widget:
 * File
 * Image


INSTALLATION
------------

Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/documentation/install/modules-themes/modules-8


CONFIGURATION
-------------

Core fields attached to a content type can be configured.

Make sure while adding field to content type,
the field is/was configured to 'Allowed number of values: UNLIMITED'.

Pictorial view of module's configuration
can be found as image(s) at module page in drupal.org.

Below are the basic steps to follow:
 * Attach a field to content type
 * While configuring this field, opt 'Allowed number of values: UNLIMITED'
 * Go to Manage Form Display page of the content type
 * Against the field choose suitable widget ending MMF, 'XXXXXX MMF'
 * Example, 'Date and time MMF' or 'Textfield MMF'
 * Against the same field, click on gear icon and set 'Minimum Fields' value
 * Check the result by creating a content of this content type
