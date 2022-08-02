<?php

// $file = $argv[1];r
$infile = "/Users/amykfarrell/dev/data_to_import/extended.xml";
$outfile = "/Users/amykfarrell/dev/data_to_import/output.csv";

$fp = fopen($outfile, 'w');

$records = processFile($infile);

foreach ($records as $record) {
    fputcsv($fp, $record);
}

fclose($fp);

function processFile($filename) {
    $doc = new DOMDocument();
    $doc->load($filename);
    $nodes = $doc->getElementsByTagName('node');
    $results = []; // We will return this; it will include the header row.
    $records = []; // One array per node, keyed by header
    $fieldnames = ['title' => 1, 'uuid' => 1, 'infoForContactCenter'];
    foreach ($nodes as $node) {
        $title = getPlainText($node, 'title');
        $uuid = getPlainText($node, 'uuid');
        $record = ['title' => $title, 'uuid' => $uuid];
        $contactLinks = getLinksFromCData($node, 'contactLinks');
        foreach ($contactLinks as $link) {
            foreach ($link as $key => $value) {
                $fieldnames[$key] = 1;
                $record[$key] = $value;
            }
        }
        $websiteLinks = getLinksFromCData($node, 'websiteLinks');
        foreach ($websiteLinks as $link) {
            foreach ($link as $key => $value) {
                $fieldnames[$key] = 1;
                $record[$key] = $value;
            }
        }
        $officeLinks = getLinksFromCData($node, 'in-personLinks');
        foreach ($officeLinks as $link) {
            foreach ($link as $key => $value) {
                $fieldnames[$key] = 1;
                $record[$key] = $value;
            }
        }
        $infoForContactCenter = getPlainText($node, 'moreInfo-forContactCenterOnly-');
        $record['infoForContactCenter'] = $infoForContactCenter;
        $records[] = $record;
    }
    $fnames = array_keys($fieldnames);
    $results[] = $fnames;
    foreach ($records as $record) {
        $row = [];
        foreach ($fnames as $field) {
            $val = array_key_exists($field, $record) ? $record[$field] : '';
            $row[] = $val;
        }
        $results[] = $row;
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
function getLinksFromCData($node, $nodename) {
    $nodes = $node->getElementsByTagName($nodename);
    $content = '';
    $results = [];
    $idx = 1;
    foreach ($nodes as $node) {
        // if ($node->nodeType == XML_CDATA_SECTION_NODE) {
        if ($content = $node->textContent) {
            $snippet = new DOMDocument;
            $snippet->loadHTML($content);
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
                $results[] = [$nodename . "_" . $idx . "_url" => $url,
                              $nodename . "_" . $idx . "_text" => $text];
                $idx++;
            }
        }
        // }
    }
    return $results;
}
?>