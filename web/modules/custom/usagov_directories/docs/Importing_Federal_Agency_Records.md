# How to import Federal Agency records

There are several steps to this process:

* Export the records from "mothership"
* Prepare the files for import
* Import the language toggles
* Import the synonyms

## Exporting the records from "mothership"

First, you will need to export the Federal Directory records from
mothership. There are two exports:

* https://usagov.platform.gsa.gov/admin/reports-federal-directory-extended
  produces a CSV file with most of the relevant records
* https://usagov.platform.gsa.gov/admin/reports-federal-directory-extended_xml
  produces an XML file with all of the rich-text fields we care about,
  plus the entity UUIDs (for matching up records between the two
  files) and the record's Title (for reference)

These are both displays of the view defined at
https://usagov.platform.gsa.gov/admin/structure/views/view/drirectory_state_detail_report
-- "Federal Directory Export Extended" and "Federal Export With Rich
Text Fields". 

1. Export both files.
2. Remove the extraneous blank line from the beginning of each
file. (Just edit the files as plain text.)

## Preparing the files for import

(You must have php (version 8) installed where you're going to do
this.)

Use the script utility/agency_import_prep.php to read in both files
and produce CSV files for import. This script takes three arguments:

1. Path to the CSV file
2. Path to the XML file
3. Path to a directory where you want the output

For example (your paths will surely vary):

% php ~/dev/usagov-2021/web/modules/custom/usagov_directories/utility/agency_import_prep.php input/directory-report.csv input/views_data_export.xml outdir


A successful run will print a message like this:

```  
  667 records
  === DONE ===
```

... and will populate your output directory with a bunch of CSV files
with names like contact_1-website_1-office_1.csv,
contact_2-website_1-office_1.csv, and so on.

### Multiple import files: Why

This process uses Drupal Feeds to import the records. There is an
issue with importing Link fields that allow multiple entries (which we
use for contact links, websites, and offices): When the field is
mapped more than once, any empty URL field causes a record to fail
validation. In other words, if you set up mappings for five website
links, you must populate all five. (Conversely, if you only map one
website link, you can leave that blank!)

I reported this as a feature request:
https://www.drupal.org/project/feeds/issues/3302749

Fixing that seemed hard, so I'm working around it by creating a
separate CSV file for each permutation of number-of-links we need to
import. 

## Importing the data to beta (first time) 

The Drupal Admin area has a feed type for the import. The "Federal
Agencies" feed must be created manually, as it is a piece of content
in the CMS. It's only necessary to create the feed once, so may
already have both:

* Feed type: Admin -> Structure -> Feed types -> Federal Agencies
  Import
* Feed: Admin -> Content -> Feeds -> Federal Agencies

The saved configuration supports zero or one of each link type, and
will import your contact_1-website_1-office_1.csv file:

1. Go to Admin -> Content -> Feeds
2. If you see a "Federal Agencies" feed, click its "Edit"
   button. Otherwise, click "Add feed" and enter "Federal Agencies" in
   the Title field. Make sure the "Active" box is *not* checked under "Import Options."
   (If more than one Feed Type were defined, you
   would probably need to select the "Federal Agencies Import" type too!) 
3. In the File field, upload the contact_1-website_1-office_1.csv file. (You might need
to Remove a previously-uploaded file.)
4. Click "Save and import"

Drupal will chew on that for awhile, showing a status bar, and then
will report how many records were imported. It may also report records
that did not validate and why. The only expected error (for the
initial import) is this (the content team will craft a new USAGov entry):

```
  The content USAGov failed to validate with the following errors:
  field_website.0: The path 'internal:/explore' is invalid.
```

**Spot check**: Go to Admin -> Content. The newest records will be what
you just imported, so they'll be at the top. Just look at a couple of
records and see that they look right. 

Next, update the mappings for each remaining file and repeat the
import. The number of mappings for "Contact," "Website," and "Find an
Office Near You" must match the number of the corresponding fields in
the CSV file ("contact," "website," and "office," respectively, in the
CSV file's name):

1. Go to Admin -> Structure -> Feed types -> Federal Agencies Import
2. Click "Mapping"
3. Scroll to the bottom and click "Select a target" and choose the
appropriate link type.
4. You will then have two fields in the "Source" columns, one for hte
URI and one for the text. All of the possible fields are predefined;
they start with:
  * contactLinks_
  * websiteLinks_
  * officeLinks_
5. Save your changes, then go back to Admin -> Content -> Feeds and
repeat the steps you did for the first file.

**Spot check**: Inspect one of the records imported, and make sure all the
links imported correctly. The potential mistake here is putting a URL
in a text field, or getting the link entries out of order. (Consider
comparing to the corresponding page on mothership.)

When you are finished, go back to the Federal Agencies Feed *Type*,
Mappings, and remove any of the "extra" fields you added (step 4
above), so the field mappings are in a consistent state next time.

## Re-importing or Updating

Ideally, we'll export just the updated records now and then, but it's
safe to re-import everything. The process is the same as an initial
import, except that you will may get some validation error messages
complaining about most of the fields in a record and saying they
cannot have more than one value.

If this happens, go back to the Federal Agencies feed type and
explicitly select the language (English or Spanish) of the some of the
records you see a complaint about, and try the import again. If there
are complaints about records of both languages, you'll probably have
to do this twice, but eventually you should get a result with no
errors.

(I considered splitting the imports by language, but even that didn't
eliminate the problem.) 

## Importing the language toggles

(You must have php (version 8) installed where you're going to do
this.)

Use the script utility/make_toggle_map.php to read in the same CSV
file you used for the base import (probably called
directory-report.csv) and generate a CSV file mapping mothership UUIDs
to their corresponding "toggle" entities' UUIDs. The script takes two
arguments: the path to the input file, and the path to the output
file.

Log in to Drupal and navigate to admin -> Configuration -> USAGov
Directories -> Import language toggles. Upload the new file and submit
the form. 

## Importing the synonyms

When you ran agency_import_prep.php, it should have created a
"synonyms.csv" file. Navigate to admin -> Configuration -> USAGov
Directories -> Import synonyms, upload the synonyms.csv file, and
submit the form. 
