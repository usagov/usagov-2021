# How to import State records

There are several steps to this process:

* Export the records from "mothership"
* Prepare the files for import
* Import the language toggles
* Import the synonyms

## Exporting the records from "mothership"

First, you will need to export the State Directory and State Details
records from mothership. There are two exports:

* https://usagov.platform.gsa.gov/admin/reports-state-directory-extended
  produces a CSV file with most of the relevant records from state
  directory records. 
* https://usagov.platform.gsa.gov/admin/reports-state-directory-extended_xml
  produces an XML file with all of
  the rich-text fields we care about from state directory records,
  plus the entity UUIDs (for matching up records between the two
  files) and the record's Title (for reference)
* https://usagov.platform.gsa.gov/admin/reports-states-extended
  produces an export of State Details records. These contain a lot of
  links in Title/URL fields. The first state directory records export
  includes a "State Details Nid" column that can be matched to the
  "Nid" column in the State Details export. 

These are all displays of the view defined at
https://usagov.platform.gsa.gov/admin/structure/views/view/drirectory_state_detail_report
-- "State Directory Export Extended," "State Export With Rich Text
Fields," and "Data Export States EXTENDED."

1. Export all files.
2. Remove the extraneous blank line from the beginning of each
file. (Just edit the files as plain text.)

## Preparing the files for import

Use the script utility/states_import_prep.php to read in both files
and produce CSV files for import. This script takes four arguments:

1. Path to the State Directory CSV file
2. Path to the State Directory XML file
3. Path to the State Details CSV file
4. Path to a directory where you want the output

Use the bin/php script to execute php within your docker CMS
container. Paths will be relative to your usagov-2021 repo root (so
you need to temporarily put your input and output files there):

For example, if I have my files in "tmpdir": 

% bin/php web/modules/custom/usagov_directories/utility/states_import_prep.php tmpdir/state-directory-report-2023-02-15T19-41-27.csv tmpdir/state-richtext-2023-02-15T19-36-04.xml tmpdir/StateDetails.csv tmpdir/outdir



A successful run will print a message like this:

```  
  118 records
  === DONE ===
```

... and will populate your output directory with CSV files
with names like contact_1-website_1-office_1.csv,
contact_2-website_1-office_1.csv, and so on. (Perhaps there will just
be one; the state directories may not have made use of multiple
links.) 

### Multiple import files: Why

See [Importing Federal Agency
Records](Importing_Federal_Agency_Records.md) for an explanation. 


## Importing the data to beta (first time) 

The Drupal Admin area has a feed type for the import. The "State
Directories import" feed must be created manually, as it is a piece of
content in the CMS. It's only necessary to create the feed once, so
may already have both:

* Feed type: Admin -> Structure -> Feed types -> State Directories import
* Feed: Admin -> Content -> Feeds -> State Directories

The saved configuration supports zero or one of each link type, and
will import your contact_1-website_1-office_1.csv file:

1. Go to Admin -> Content -> Feeds
2. If you see a "State Directories" feed, click its "Edit"
   button. Otherwise, click "Add feed" and enter "State Directories" in
   the Title field. Make sure the "Active" box is *not* checked under "Import Options."
   (If more than one Feed Type were defined, you
   would probably need to select the "Federal Agencies Import" type
   too!)
3. In the "Authoring information" field, type "shoshana" and select
   shoshana_mayden. We're going to assign all the imported records to
   her. 
4. In the File field, upload the contact_1-website_1-office_1.csv file. (You might need
   to Remove a previously-uploaded file.)
5. Click "Save and import"

Drupal will chew on that for awhile, showing a status bar, and then
will report how many records were imported. It may also report records
that did not validate and why.

**Spot check**: Go to Admin -> Content. The newest records will be what
you just imported, so they'll be at the top. Just look at a couple of
records and see that they look right. 

## Re-importing or Updating

Ideally, we'll export just the updated records now and then, but it's
safe to re-import everything. The process is the same as an initial
import, except that you will may get some validation error messages
complaining about most of the fields in a record and saying they
cannot have more than one value.

If this happens, go back to the "State Directories import" feed type and
explicitly select the language (English or Spanish) of the some of the
records you see a complaint about, and try the import again. If there
are complaints about records of both languages, you'll probably have
to do this twice, but eventually you should get a result with no
errors.

(I considered splitting the imports by language, but even that didn't
eliminate the problem.) 

