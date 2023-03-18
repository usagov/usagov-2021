<?php

/**
 * This script converts Federal Directory Record data exported from the
 * previous USA.gov site into CSV files for import into the new site.
 * The import will be done using the Drupal Feeds module. Once we've
 * finished transferring data, there will be no further need for this script.
 *
 * This script takes paths to two input files and an output directory:
 *  - A CSV file with most of the fields in plain-text form
 *  - An XML file with fields that are not plain text (generally, HTML snippets)
 *  - A directory where you want your output
 *
 * Both files include create and update timestamps on all records, for the
 * purpose of doing incremental updates (to be implemented).
 *
 * Due to a problem importing records with multiple-value "link" mappings, in
 * which some of the mappings are blank, this script splits its output into
 * multiple files, based on the combination of "link" fields present in each
 * record. That means multiple map-and-import steps for the user.
 * See https://www.drupal.org/project/feeds/issues/3302749 for details on
 * the underlying issue.
 *
 * - akf, 2022-08-17
 */

$infile = $argv[1];
$extended_infile = $argv[2];
$outdir = $argv[3];

error_reporting(-1); // Will let us know if iconv fails.

function main($infile, $extended_infile, $outdir) {
  $fp_infile = fopen($infile, 'r');

  // Deal with the CSV file first.
  $basic_records_by_uuid = [];
  $headings_processed = FALSE;
  while (($row = fgetcsv($fp_infile)) !== FALSE) {
    if (!$headings_processed) {
      $row[] = 'langcode';
      $row[] = 'alias';
      $row[] = 'phonehint';
      $row[] = 'Street 3';
      $array_indexes = array_flip($row);
      $headings_processed = TRUE;
    }
    else {
      $data = convert_fields($row, $array_indexes);
      $uuid = $data['UUID'];
      $basic_records_by_uuid[$uuid] = $data;
    }
  }
  fclose($fp_infile);

  $basic_headings = array_flip($array_indexes);
  $extended_records_by_uuid = process_xml_file($extended_infile);
  $extended_headings = $extended_records_by_uuid['headings'];

  $headings = array_merge($extended_headings, $basic_headings);
  $out_files = [];
  $out_files['synonyms'] = []; // for a separate file mapping mothership_uuid to langcode, synonyms.
  $num_records = 0; // We'll count them on output, just so we can report.

  // Define headings for a "reviewer's" CSV file. This will include a subset of the
  // set of fields, with an entry for every federal agency record.
  $reviewer_csv = 'federal_agencies_from_mothership';
  $reviewer_headings = [
    'Title', 'Language', 'Show on AZ Index', 'Summary', 'Government branch',
    'Synonym', 'Email',
    'Street 1', 'Street 2', 'Street 3', 'City', 'State', 'ZIP',
  ];

  $paths_csv = 'path_updates';
  $paths_headings = [
    'Title', 'UUID', 'alias', 'Show on AZ Index',
  ];
  $out_files[$paths_csv] = [$paths_headings];

  // Add the variable number of phone columns from basic headings:
  foreach (['phone_', 'toll_', 'tty_'] as $phonetype) {
    $reviewer_headings = array_merge($reviewer_headings, array_filter($basic_headings, fn($e) => str_contains($e, $phonetype)));
  }
  // Add the variable link columns from extended headings:
  foreach (['contactLinks_', 'websiteLinks_', 'officeLinks_'] as $linktype) {
    $reviewer_headings = array_merge($reviewer_headings, array_filter($extended_headings, fn($e) => str_contains($e, $linktype)));
  }
  $reviewer_headings = array_merge($reviewer_headings, [
    'English Toggle', 'Spanish Toggle', 'alias', 'UUID', 'UNUSED --->',
    'Acronym', 'Agency', 'Agency Tags', 'Alpha-order-name', 'Archive Date', 'CAH Description',
    'CFO Agency', 'Child Records', 'Comments', 'English Translation Name', 'Owner',
    'Parent Record', 'Date Last Reviewed', 'Post date', 'Updated date',
  ]);
  $out_files[$reviewer_csv] = [$reviewer_headings];

  foreach ($basic_records_by_uuid as $uuid => $basic_record) {
    $extended_record = $extended_records_by_uuid[$uuid];

    // Get the "hints" from both records and concatenate them to group records
    // by number of multi-value Link fields to map:
    // $hint = $basic_record['langcode'] . '-' . $extended_record['multivalue_hint'];
    $hint = $extended_record['multivalue_hint'] ?: 'none';

    // Now combine the records into a flat array, in the same order as $headings above.
    $flat_record = [];
    foreach ($extended_headings as $eh) {
      $flat_record[] = array_key_exists($eh, $extended_record) ? $extended_record[$eh] : '';
    }
    foreach ($basic_headings as $bh) {
      $flat_record[] = array_key_exists($bh, $basic_record) ? $basic_record[$bh] : '';
    }
    $num_records++;

    if (!array_key_exists($hint, $out_files)) {
      $out_files[$hint] = [];
      $out_files[$hint][] = $headings;
    }
    $out_files[$hint][] = $flat_record;

    // Add to the Synonym file, if appropriate:
    if ($basic_record['Synonym']) {
      $out_files['synonyms'][] = [
        $basic_record['UUID'],
        $basic_record['langcode'],
        $basic_record['Synonym'],
      ];
    }

    // Combine the records into a different flat array for the reviewer's csv:
    $review_record = [];
    foreach ($reviewer_headings as $col) {
      if (array_key_exists($col, $basic_record)) {
        $review_record[] = $basic_record[$col] ?: '';
      }
      elseif (array_key_exists($col, $extended_record)) {
        $review_record[] = $extended_record[$col] ?: '';
      }
      else {
        $review_record[] = '';
      }
    }
    $out_files[$reviewer_csv][] = $review_record;

    // Make a version with just what's needed for path alias updates:
    $paths_record = [];
    foreach ($paths_headings as $col) {
      if (array_key_exists($col, $basic_record)) {
        $paths_record[] = $basic_record[$col] ?: '';
      }
      elseif (array_key_exists($col, $extended_record)) {
        $paths_record[] = $extended_record[$col] ?: '';
      }
      else {
        $paths_record[] = '';
      }
    }
    $out_files[$paths_csv][] = $paths_record;
  }

  print("$num_records records\n");

  foreach ($out_files as $hint => $data) {
    $outfile = implode(DIRECTORY_SEPARATOR, [$outdir, $hint . ".csv"]);
    $fp_out = fopen($outfile, 'w');
    foreach ($data as $row) {
      // Before writing out the row, trim whitespace from each cell.
      $trimmed_row = array_map('trim', $row);
      fputcsv($fp_out, $trimmed_row);
    }
    fclose($fp_out);
  }
  print("=== DONE ===\n");
}

/**
 * Map and convert selected fields. Returns an array indexed by field name. Modifies $indexes.
 *
 * @param Array $row
 * @param Array $indexes
 * @return Array
 */
function convert_fields($row, &$indexes) {
  // Language -> langcode
  $lang_index = $indexes['Language'];
  $lc_index = $indexes['langcode'];
  $row[$lc_index] = $row[$lang_index] == 'Spanish' ? 'es' : 'en';

  // Convert "Show on AZ Index" from Yes/No to 1/0:
  $az_index = $indexes['Show on AZ Index'];
  $row[$az_index] = $row[$az_index] == 'Yes' ? 1 : 0;

  // Make a path alias based on the Agency's title:
  $alias_index = $indexes['alias'];
  $alias = $row[$indexes['Title']];
  $alias = make_clean_alias($alias);

  if ($row[$lang_index] == 'Spanish') {
    $alias = '/agencias/' . $alias;
  }
  else {
    $alias = '/agencies/' . $alias;
  }
  $row[$alias_index] = $alias;

  // Streets: If there is a "Subdivision", it becomes "Street 1", "Street 1" becomes "Street 2",
  // and "Street 2" becomes "Street 3".
  if ($subdivision = $row[$indexes['Subdivision']]) {
    $row[$indexes['Street 3']] = $row[$indexes['Street 2']];
    $row[$indexes['Street 2']] = $row[$indexes['Street 1']];
    $row[$indexes['Street 1']] = $subdivision;
  }

  // Phone number fields are lists of plain text strings, joined by '###'.
  $phone_map = [
    'Phone number' => 'phone',
    'Toll free number' => 'toll',
    'TTY number' => 'tty',
  ];
  // We want to know how many numbers to map, but we always map at least 1.
  $hints = ['phone' => 1, 'toll' => 1, 'tty' => 1];
  foreach ($phone_map as $fieldname => $shortname) {
    $num_index = $indexes[$fieldname];
    $numbers = explode('###', $row[$num_index]);
    $numcount = 0;
    foreach ($numbers as $num) {
      $fn = $shortname . '_' . ++$numcount;
      if (!array_key_exists($fn, $indexes)) {
        $indexes[$fn] = count($indexes);
      }
      $row[$indexes[$fn]] = $num;
    }
    $hints[$shortname] = $numcount;
  }
  $data = [];
  $index_flip = array_flip($indexes);
  foreach ($row as $idx => $val) {
    $key = $index_flip[$idx];
    $data[$key] = $val;
  }

  $data['phonehint'] = implode('-', [
    'phone_' . $hints['phone'],
    'toll_' . $hints['toll'],
    'tty_' . $hints['tty'],
  ]);
  return $data;
}

function process_xml_file($filename) {
  $doc = new DOMDocument("1.0", "UTF-8");
  $doc->load($filename);
  $nodes = $doc->getElementsByTagName('node');
  $records = []; // One array per node, keyed by header
  $fieldnames = ['infoForContactCenter' => 1];
  foreach ($nodes as $node) {
    $uuid = get_plain_text($node, 'uuid');
    $record = [];
    $multivalue_hints = [];
    $contactLinks = get_links_from_cdata($node, 'contactLinks');
    $mapcount = count($contactLinks) ?: 1;
    $multivalue_hints[] = 'contact_' . $mapcount;
    foreach ($contactLinks as $link) {
      foreach ($link as $key => $value) {
        $fieldnames[$key] = 1;
        $record[$key] = $value;
      }
    }
    $websiteLinks = get_links_from_cdata($node, 'websiteLinks');
    $mapcount = count($websiteLinks) ?: 1;
    $multivalue_hints[] = 'website_' . $mapcount;
    foreach ($websiteLinks as $link) {
      foreach ($link as $key => $value) {
        $fieldnames[$key] = 1;
        $record[$key] = $value;
      }
    }
    $officeLinks = get_links_from_cdata($node, 'in-personLinks', 'officeLinks');
    $mapcount = count($officeLinks) ?: 1;
    $multivalue_hints[] = 'office_' . $mapcount;
    foreach ($officeLinks as $link) {
      foreach ($link as $key => $value) {
        $fieldnames[$key] = 1;
        $record[$key] = $value;
      }
    }
    $infoForContactCenter = get_plain_text($node, 'moreInfo-forContactCenterOnly-');
    $record['infoForContactCenter'] = $infoForContactCenter;
    $multivalue_hint = implode('-', $multivalue_hints);
    $record['multivalue_hint'] = $multivalue_hint;
    $records[$uuid] = $record;

  }
  // Sort the field names, and move "multivalue_hint" to the first column. It helps me.
  $fnames = array_keys($fieldnames);
  $fnames = array_diff($fnames, ['multivalue_hint']);
  sort($fnames);
  $fnames = array_merge(['multivalue_hint'], $fnames);

  $records['headings'] = $fnames;
  return $records;
}

/**
 * Get the plain-text string from the named element. Assumes there is just one node
 * matching the element name $nodename, and that it is a plain-text node.
 *
 * @param DomNode $node
 * @param string $nodename
 * @return void
 */
function get_plain_text($node, $nodename) {
  $nodes = $node->getElementsByTagName($nodename);
  foreach ($nodes as $node) {
    return $node->textContent;
  }
  return '';
}

/**
 * Get Links (as URL and Text parts) from the named element. Assumes there is just one node
 * matching the element name $nodename, and that it contains CDATA.
 *
 * @param DomNode $node
 * @param string $nodename
 * @return void
 */
function get_links_from_cdata($node, $nodename, $columnname = NULL) {
  $columnname = $columnname ?: $nodename;
  $nodes = $node->getElementsByTagName($nodename);
  $content = '';
  $results = [];
  $idx = 1;
  foreach ($nodes as $node) {
    if ($content = $node->textContent) {
      $libxml_error_setting = libxml_use_internal_errors(TRUE);
      $snippet = new DOMDocument();
      // Without the UTF-8 hint, HTML snippets default to the wrong charset (ISO-8859-1, I think)
      $snippet->loadHTML('<?xml encoding="UTF-8">' . $content);
      $errs = libxml_get_errors();
      if (count($errs)) {
        foreach ($errs as $error) {
          if (!str_contains($error->message, "htmlParseEntityRef: expecting ';'")) {
            print("WARNING: " . $error->message);
          }
        }
      }
      libxml_use_internal_errors($libxml_error_setting);
      $links = $snippet->getElementsByTagName('a');
      foreach ($links as $link) {
        $a = $link->attributes['a'];
        if ($a) {
          $url = $link->attributes['a']->textContent;
        }
        else {
          $url = '';
        }
        $text = $link->textContent;
        $results[] = [
          $columnname . "_" . $idx . "_url" => $url,
          $columnname . "_" . $idx . "_text" => $text,
        ];
        $idx++;
      }
    }
  }
  return $results;
}

/**
 * Ensure our path aliases don't have diacriticals (or urlencoded diacriticals)
 * or other non-ascii characters. Path alias may be from the path provided
 * in the import, or it may be the node's Title.
 *
 * $str might have url-encoded characters, so clean it up:
 *  - urldecode it
 *  - ascii-fy it (with transliteration)
 *  - remove any single quotes
 *  - urlencode it (just in case there are spaces or chars I didn't think of)
 */
function make_clean_alias($str) {
  $str = trim($str);

  // We want to replace ' and ~ with - first, because later we'll want to eliminate ' from transliteration
  $str = str_replace(['\'', '~'], ['-'], $str);

  $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str); // musl iconv does not support //TRANSLIT, but we need it for these

  // conversion of some characters may have introduced ' and ~
  $str = str_replace(['\'', '~'], [''], strtolower($str));

  // Any non-alphanumeric characters, replace with -:
  $str = preg_replace('/[^\w\d\/]/i', '-', $str);
  $str = preg_replace('/-+/', '-', $str);
  $str = preg_replace('/^-+/', '', $str);
  $str = preg_replace('/-+$/', '', $str);

  return $str;
}

main($infile, $extended_infile, $outdir);
