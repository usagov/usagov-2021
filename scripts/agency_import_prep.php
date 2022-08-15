<?php

/**
 * Prep an import CSV for "basic" fields in Federal Directory records
 */
$infile = "/Users/amykfarrell/dev/data_to_import/basic_directory_records.csv";
$extended_infile = "/Users/amykfarrell/dev/data_to_import/extended.xml";
$outdir = "/Users/amykfarrell/dev/data_to_import/outdir";

function main($infile, $extended_infile, $outdir) {
    $fp_infile = fopen($infile, 'r');

    // Deal with the CSV file first.
    $basic_records_by_uuid = [];
    $headings_processed = FALSE;
    while (($data = fgetcsv($fp_infile)) !== FALSE) {
        if (!$headings_processed) {
            $data[] = 'langcode';
            $data[] = 'alias';
            $data[] = 'phonehints';
            $array_indexes = array_flip($data);
            $headings_processed = TRUE;
        }
        else {
            convert_fields($data, $array_indexes);
            $uuid = $data[$array_indexes['UUID']];
            $basic_records_by_uuid[$uuid] = $data;
        }
    }
    fclose($fp_infile);

    $basic_headings = array_flip($array_indexes);
    $extended_records_by_uuid = processXMLFile($extended_infile);
    $extended_headings = $extended_records_by_uuid['headings'];
    print("Basic Headings: \n\t" . implode("\n\t", $basic_headings) . "\n");

    $records = [];
    $headings = array_merge($extended_headings, $basic_headings);
    $out_files = [];
    $num_records = 0; // We'll count them on output, just so we can report.
    foreach ($basic_records_by_uuid as $uuid => $record) {
        $extended_record = $extended_records_by_uuid[$uuid];

        // Get the "hints" from both records and concatenate them to group records
        // by number of multi-value fields to map:
        $hint = $extended_record[0] ?: 'none';
        $phonehint_index = $array_indexes['phonehints'];
        $hint .= '-' .  $record[$phonehint_index];

        // Now merge the records.
        $record = array_merge($extended_record, $record);
        $num_records++;

        if (!array_key_exists($hint, $out_files)) {
            $out_files[$hint] = [];
            $out_files[$hint][] = $headings;
        }
        $out_files[$hint][] = $record;
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
    print( '=== DONE ===' );
}

/**
 * Map and convert selected fields. Modifies both the $data and $indexes inputs.
 *
 * @param Array $data
 * @param Array $indexes
 * @return void
 */
function convert_fields(&$data, &$indexes) {
    // Language -> langcode
    $lang_index = $indexes['Language'];
    $lc_index = $indexes['langcode'];
    $data[$lc_index] = $data[$lang_index] == 'Spanish' ? 'es' : 'en';

    // Path -> alias
    $path_index = $indexes['Path'];
    $alias_index = $indexes['alias'];
    $alias = $data[$path_index];
    if (!str_starts_with($alias, '/content/')) {
        // This is unexpected.
        print("Unusual alias being left alone: " . $alias . "\n");
    }
    else {
        // Trim off /content, then concatenate what remains to the correct parent path.
        // While it would be unusual for /content/ to appear elsewhere in the path, why risk a global replace?
        $alias = substr($alias, 9);
        if ($data[$lang_index] == 'Spanish') {
            $alias = '/agencia/' . $alias;
        }
        else {
            $alias = '/agency/' . $alias;
        }
        $data[$alias_index] = $alias;
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
        $numbers = explode('###', $data[$num_index]);
        $numcount = 0;
        foreach ($numbers as $num) {
            $fn = $shortname . '_' . ++$numcount;
            if (!array_key_exists($fn, $indexes)) {
                $indexes[$fn] = count($indexes);
            }
            $data[$indexes[$fn]] = $num;
        }
        $hints[$shortname] = $numcount;
    }

    $data[$indexes['phonehints']] = implode('-', ['phone_' . $hints['phone'], 'toll_' . $hints['toll'], 'tty_' . $hints['tty']]);
    return $data;
}

function processXMLFile($filename) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->load($filename);
    $nodes = $doc->getElementsByTagName('node');
    $results = []; // We will return this; it will include the header row.
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

    $results['headings'] = $fnames;
    foreach ($records as $uuid => $record) {
        $row = [];
        foreach ($fnames as $field) {
            $val = array_key_exists($field, $record) ? $record[$field] : '';
            $row[] = $val;
        }
        $results[$uuid] = $row;
    }
    return $results;
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