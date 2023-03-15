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
  $original = $str;
  $expected = sanitize_for_url($str);
  $str = trim($str);
  $str = urldecode($str);

  // We want to replace ' with - first, because later we'll want to eliminate ' from transliteration
  $str = str_replace(['\''], ['-'], $str);

  $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str); // musl iconv does not support //TRANSLIT, but we need it for these

  // conversion of some characters may have introduced ' and ~
  $str = str_replace(['\'', '~'], [''], strtolower($str));

  // Any non-alphanumeric characters, replace with -:
  $str = preg_replace('/[^\w\d]/i', '-', $str);
  $str = preg_replace('/-+/', '-', $str);
  $str = preg_replace('/^-+/', '', $str);
  $str = preg_replace('/-+$/', '', $str);


  $str = urlencode($str);
  if ($str != $expected) {
    print "\nm: $expected\nb: $str\n";
  }
  return $str;
}

function sanitize_for_url($string = '') {
  $string = remove_accents($string);

  $string = trim($string);
  $string = strtolower($string);
  $string = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $string);

  $string = preg_replace('/[^a-zA-Z0-9\/]+/', '-', $string);
  $string = preg_replace('/-+/', '-', $string);
  $string = preg_replace('/^-+/', '', $string);
  $string = preg_replace('/-+$/', '', $string);

  return $string;
}

function remove_accents($string)
    {
        if ( !preg_match('/[\x80-\xff]/', $string) )
            return $string;

        if (seems_utf8($string)) {
            $chars = array(
                // Decompositions for Latin-1 Supplement
                chr(194).chr(170) => 'a', chr(194).chr(186) => 'o',
                chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
                chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
                chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
                chr(195).chr(134) => 'AE',chr(195).chr(135) => 'C',
                chr(195).chr(136) => 'E', chr(195).chr(137) => 'E',
                chr(195).chr(138) => 'E', chr(195).chr(139) => 'E',
                chr(195).chr(140) => 'I', chr(195).chr(141) => 'I',
                chr(195).chr(142) => 'I', chr(195).chr(143) => 'I',
                chr(195).chr(144) => 'D', chr(195).chr(145) => 'N',
                chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
                chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
                chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
                chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
                chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
                chr(195).chr(158) => 'TH',chr(195).chr(159) => 's',
                chr(195).chr(160) => 'a', chr(195).chr(161) => 'a',
                chr(195).chr(162) => 'a', chr(195).chr(163) => 'a',
                chr(195).chr(164) => 'a', chr(195).chr(165) => 'a',
                chr(195).chr(166) => 'ae',chr(195).chr(167) => 'c',
                chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
                chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
                chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
                chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
                chr(195).chr(176) => 'd', chr(195).chr(177) => 'n',
                chr(195).chr(178) => 'o', chr(195).chr(179) => 'o',
                chr(195).chr(180) => 'o', chr(195).chr(181) => 'o',
                chr(195).chr(182) => 'o', chr(195).chr(184) => 'o',
                chr(195).chr(185) => 'u', chr(195).chr(186) => 'u',
                chr(195).chr(187) => 'u', chr(195).chr(188) => 'u',
                chr(195).chr(189) => 'y', chr(195).chr(190) => 'th',
                chr(195).chr(191) => 'y', chr(195).chr(152) => 'O',
                // Decompositions for Latin Extended-A
                chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
                chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
                chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
                chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
                chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
                chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
                chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
                chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
                chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
                chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
                chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
                chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
                chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
                chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
                chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
                chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
                chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
                chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
                chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
                chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
                chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
                chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
                chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
                chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
                chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
                chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
                chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
                chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
                chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
                chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
                chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
                chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
                chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
                chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
                chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
                chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
                chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
                chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
                chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
                chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
                chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
                chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
                chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
                chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
                chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
                chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
                chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
                chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
                chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
                chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
                chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
                chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
                chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
                chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
                chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
                chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
                chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
                chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
                chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
                chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
                chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
                chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
                chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
                chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
                // Decompositions for Latin Extended-B
                chr(200).chr(152) => 'S', chr(200).chr(153) => 's',
                chr(200).chr(154) => 'T', chr(200).chr(155) => 't',
                // Euro Sign
                chr(226).chr(130).chr(172) => 'E',
                // GBP (Pound) Sign
                chr(194).chr(163) => '',
                // Vowels with diacritic (Vietnamese)
                // unmarked
                chr(198).chr(160) => 'O', chr(198).chr(161) => 'o',
                chr(198).chr(175) => 'U', chr(198).chr(176) => 'u',
                // grave accent
                chr(225).chr(186).chr(166) => 'A', chr(225).chr(186).chr(167) => 'a',
                chr(225).chr(186).chr(176) => 'A', chr(225).chr(186).chr(177) => 'a',
                chr(225).chr(187).chr(128) => 'E', chr(225).chr(187).chr(129) => 'e',
                chr(225).chr(187).chr(146) => 'O', chr(225).chr(187).chr(147) => 'o',
                chr(225).chr(187).chr(156) => 'O', chr(225).chr(187).chr(157) => 'o',
                chr(225).chr(187).chr(170) => 'U', chr(225).chr(187).chr(171) => 'u',
                chr(225).chr(187).chr(178) => 'Y', chr(225).chr(187).chr(179) => 'y',
                // hook
                chr(225).chr(186).chr(162) => 'A', chr(225).chr(186).chr(163) => 'a',
                chr(225).chr(186).chr(168) => 'A', chr(225).chr(186).chr(169) => 'a',
                chr(225).chr(186).chr(178) => 'A', chr(225).chr(186).chr(179) => 'a',
                chr(225).chr(186).chr(186) => 'E', chr(225).chr(186).chr(187) => 'e',
                chr(225).chr(187).chr(130) => 'E', chr(225).chr(187).chr(131) => 'e',
                chr(225).chr(187).chr(136) => 'I', chr(225).chr(187).chr(137) => 'i',
                chr(225).chr(187).chr(142) => 'O', chr(225).chr(187).chr(143) => 'o',
                chr(225).chr(187).chr(148) => 'O', chr(225).chr(187).chr(149) => 'o',
                chr(225).chr(187).chr(158) => 'O', chr(225).chr(187).chr(159) => 'o',
                chr(225).chr(187).chr(166) => 'U', chr(225).chr(187).chr(167) => 'u',
                chr(225).chr(187).chr(172) => 'U', chr(225).chr(187).chr(173) => 'u',
                chr(225).chr(187).chr(182) => 'Y', chr(225).chr(187).chr(183) => 'y',
                // tilde
                chr(225).chr(186).chr(170) => 'A', chr(225).chr(186).chr(171) => 'a',
                chr(225).chr(186).chr(180) => 'A', chr(225).chr(186).chr(181) => 'a',
                chr(225).chr(186).chr(188) => 'E', chr(225).chr(186).chr(189) => 'e',
                chr(225).chr(187).chr(132) => 'E', chr(225).chr(187).chr(133) => 'e',
                chr(225).chr(187).chr(150) => 'O', chr(225).chr(187).chr(151) => 'o',
                chr(225).chr(187).chr(160) => 'O', chr(225).chr(187).chr(161) => 'o',
                chr(225).chr(187).chr(174) => 'U', chr(225).chr(187).chr(175) => 'u',
                chr(225).chr(187).chr(184) => 'Y', chr(225).chr(187).chr(185) => 'y',
                // acute accent
                chr(225).chr(186).chr(164) => 'A', chr(225).chr(186).chr(165) => 'a',
                chr(225).chr(186).chr(174) => 'A', chr(225).chr(186).chr(175) => 'a',
                chr(225).chr(186).chr(190) => 'E', chr(225).chr(186).chr(191) => 'e',
                chr(225).chr(187).chr(144) => 'O', chr(225).chr(187).chr(145) => 'o',
                chr(225).chr(187).chr(154) => 'O', chr(225).chr(187).chr(155) => 'o',
                chr(225).chr(187).chr(168) => 'U', chr(225).chr(187).chr(169) => 'u',
                // dot below
                chr(225).chr(186).chr(160) => 'A', chr(225).chr(186).chr(161) => 'a',
                chr(225).chr(186).chr(172) => 'A', chr(225).chr(186).chr(173) => 'a',
                chr(225).chr(186).chr(182) => 'A', chr(225).chr(186).chr(183) => 'a',
                chr(225).chr(186).chr(184) => 'E', chr(225).chr(186).chr(185) => 'e',
                chr(225).chr(187).chr(134) => 'E', chr(225).chr(187).chr(135) => 'e',
                chr(225).chr(187).chr(138) => 'I', chr(225).chr(187).chr(139) => 'i',
                chr(225).chr(187).chr(140) => 'O', chr(225).chr(187).chr(141) => 'o',
                chr(225).chr(187).chr(152) => 'O', chr(225).chr(187).chr(153) => 'o',
                chr(225).chr(187).chr(162) => 'O', chr(225).chr(187).chr(163) => 'o',
                chr(225).chr(187).chr(164) => 'U', chr(225).chr(187).chr(165) => 'u',
                chr(225).chr(187).chr(176) => 'U', chr(225).chr(187).chr(177) => 'u',
                chr(225).chr(187).chr(180) => 'Y', chr(225).chr(187).chr(181) => 'y',
                // Vowels with diacritic (Chinese, Hanyu Pinyin)
                chr(201).chr(145) => 'a',
                // macron
                chr(199).chr(149) => 'U', chr(199).chr(150) => 'u',
                // acute accent
                chr(199).chr(151) => 'U', chr(199).chr(152) => 'u',
                // caron
                chr(199).chr(141) => 'A', chr(199).chr(142) => 'a',
                chr(199).chr(143) => 'I', chr(199).chr(144) => 'i',
                chr(199).chr(145) => 'O', chr(199).chr(146) => 'o',
                chr(199).chr(147) => 'U', chr(199).chr(148) => 'u',
                chr(199).chr(153) => 'U', chr(199).chr(154) => 'u',
                // grave accent
                chr(199).chr(155) => 'U', chr(199).chr(156) => 'u',
            );

            $string = strtr($string, $chars);
        } else {
            // Assume ISO-8859-1 if not UTF-8
            $chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
                .chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
                .chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
                .chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
                .chr(211).chr(212).chr(213).chr(214).chr(216).chr(217).chr(218)
                .chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
                .chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
                .chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
                .chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
                .chr(252).chr(253).chr(255);

            $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

            $string = strtr($string, $chars['in'], $chars['out']);
            $double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
            $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
            $string = str_replace($double_chars['in'], $double_chars['out'], $string);
        }

        return $string;
    }

    function seems_utf8($str)
    {
        $length = strlen($str);
        for ($i=0; $i < $length; $i++) {
            $c = ord($str[$i]);
            if ($c < 0x80) $n = 0; # 0bbbbbbb
            elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
            elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
            elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
            elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
            elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
            else return false; # Does not match any model
            for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
                if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
                    return false;
            }
        }
        return true;
    }

main($infile, $extended_infile, $outdir);
