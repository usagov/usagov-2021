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
    $extended_records_by_uuid = processXMLFile($extended_infile);
    $extended_headings = $extended_records_by_uuid['headings'];

    $records = [];
    $headings = array_merge($extended_headings, $basic_headings);
    $out_files = [];
    $num_records = 0; // We'll count them on output, just so we can report.
    foreach ($basic_records_by_uuid as $uuid => $basic_record) {
        $extended_record = $extended_records_by_uuid[$uuid];

        // Get the "hints" from both records and concatenate them to group records
        // by number of multi-value fields to map:
        $hint = $extended_record['multivalue_hint'] ?: 'none';
        $hint .= '-' .  $basic_record['phonehint'];

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
    }
    print("$num_records records\n");

    foreach ($out_files as $hint => $data) {
        $outfile = join(DIRECTORY_SEPARATOR, [$outdir, $hint . ".csv"]);
        $fp_out = fopen($outfile, 'w');
        foreach ($data as $row) {
            fputcsv($fp_out, $row);
        }
        fclose($fp_out);
    }
    print( "=== DONE ===\n" );
}

/**
 * Map and convert selected fields. Returns an array indexed by field name. Modifies the $indexes inputs.
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

    // Path -> alias
    $path_index = $indexes['Path'];
    $alias_index = $indexes['alias'];
    $alias = $row[$path_index];
    if (!str_starts_with($alias, '/content/')) {
        // This is unexpected.
        print("Unusual alias being left alone: " . $alias . "\n");
    }
    else {
        // Trim off /content, then concatenate what remains to the correct parent path.
        // While it would be unusual for /content/ to appear elsewhere in the path, why risk a global replace?
        $alias = substr($alias, 9);
        if ($row[$lang_index] == 'Spanish') {
            $alias = '/agencia/' . $alias;
        }
        else {
            $alias = '/agency/' . $alias;
        }
        $row[$alias_index] = $alias;
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

    $data['phonehint'] = implode('-', ['phone_' . $hints['phone'], 'toll_' . $hints['toll'], 'tty_' . $hints['tty']]);
    return $data;
}

function processXMLFile($filename) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->load($filename);
    $nodes = $doc->getElementsByTagName('node');
    $records = []; // One array per node, keyed by header
    $fieldnames = ['infoForContactCenter' => 1];
    foreach ($nodes as $node) {
        $uuid = getPlainText($node, 'uuid');
        $record = [];
        $multivalue_hints = [];
        $contactLinks = getLinksFromCData($node, 'contactLinks');
        $mapcount = count($contactLinks) ?: 1;
        $multivalue_hints[] = 'contact_' . $mapcount;
        foreach ($contactLinks as $link) {
            foreach ($link as $key => $value) {
                $fieldnames[$key] = 1;
                $record[$key] = $value;
            }
        }
        $websiteLinks = getLinksFromCData($node, 'websiteLinks');
        $mapcount = count($websiteLinks) ?: 1;
        $multivalue_hints[] = 'website_' . $mapcount;
        foreach ($websiteLinks as $link) {
            foreach ($link as $key => $value) {
                $fieldnames[$key] = 1;
                $record[$key] = $value;
            }
        }
        $officeLinks = getLinksFromCData($node, 'in-personLinks', 'officeLinks');
        $mapcount = count($officeLinks) ?: 1;
        $multivalue_hints[] = 'office_' . $mapcount;
        foreach ($officeLinks as $link) {
            foreach ($link as $key => $value) {
                $fieldnames[$key] = 1;
                $record[$key] = $value;
            }
        }
        $infoForContactCenter = getPlainText($node, 'moreInfo-forContactCenterOnly-');
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
function getPlainText($node, $nodename) {
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
function getLinksFromCData($node, $nodename, $columnname=NULL) {
    $columnname = $columnname ?: $nodename;
    $nodes = $node->getElementsByTagName($nodename);
    $content = '';
    $results = [];
    $idx = 1;
    foreach ($nodes as $node) {
        // if ($node->nodeType == XML_CDATA_SECTION_NODE) {
        if ($content = $node->textContent) {
            $snippet = new DOMDocument();
            // Without the UTF-8 hint, HTML snippets default to the wrong charset (ISO-8859-1, I think)
            $snippet->loadHTML('<?xml encoding="UTF-8">' . $content);
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
                $results[] = [$columnname . "_" . $idx . "_url" => $url,
                              $columnname . "_" . $idx . "_text" => $text];
                $idx++;
            }
        }
        // }
    }
    return $results;
}

main($infile, $extended_infile, $outdir);
?>