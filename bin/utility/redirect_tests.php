<?php

/**
 * This script was used to validate redirects for blog.usa.gov, which use the
 * nginx 'map' directive. To validate a similar batch of redirects for something else,
 * modify these variables:
 */
$infile = '../../.docker/src-waf/etc/nginx/snippets/redirect-map-blog-usa.conf';
$old_host = 'https://blog.usa.gov';
$new_host = 'http://localhost';

/**
 * The script will attempt to retrieve the "from" and "to" pages from the map, extract
 * their titles, and compare them. In its current incarnation, titles match if the "new"
 * title contains the "old" title.
 * (Example: old was "My cool blog post" and new is "My cool blog post | USA.gov")
 * The script will create files named:
 *   redirect-report-good.csv -- Looks good, both URLs exist and the titles sorta match.
 *   redirect-report-bad.csv -- No title match. Could mean one or both of the URLs is 404.
 *   redirect-report-unprocessed -- lines that don't look like map entries.
 */

$fp_infile = fopen($infile, 'r');
$document = new \DOMDocument();

$outfile_prefix = "./redirect-report";
$fp_good_outfile = fopen($outfile_prefix . "-good.csv", 'w');
$fp_bad_outfile = fopen($outfile_prefix . "-bad.csv", 'w');
$fp_unprocessed_outfile = fopen($outfile_prefix . "-unprocessed", 'w');

fputcsv($fp_good_outfile, ["old_site url", "new_site url", "old_site title", "new_site title"]);
fputcsv($fp_bad_outfile, ["old_site url", "new_site url", "old_site title", "new_site title"]);

if ($fp_infile) {
  $good = [];
  $bad = [];
  $unprocessed = [];
  while ($line = fgets($fp_infile)) {
    $matches = [];
    if (preg_match('/([^ ]+) +([^ ]+) +;/', $line, $matches)) {
      $old_site_path = $old_host . $matches[1];
      $new_site_path = $new_host . $matches[2];

      // Retrieve the page contents from both servers:
      $old_site_page = file_get_contents($old_site_path);
      $new_site_page = file_get_contents($new_site_path);
      $old_site_title = 'Old site title not found';
      $new_site_title = 'New site title not found';

      // Use DOMDocument to get the titles (this is probably a bit heavy-handed)
      if ($old_site_page) {
        try {
          @$document->loadHTML($old_site_page, LIBXML_SCHEMA_CREATE);
          $xpath = new \DOMXPath($document);
          $old_site_title = $xpath->query('//title')[0]->textContent;
        }
        catch (Exception $e) {
          echo "$e->getMessage() for $old_site_page", "\n";
        }
      }

      if ($new_site_page) {
        try {
          @$document->loadHTML($new_site_page, LIBXML_SCHEMA_CREATE);
          $xpath = new \DOMXPath($document);
          $new_site_title = $xpath->query('//title')[0]->textContent;
        }
        catch (Exception $e) {
          echo "$e->getMessage() for $new_site_page", "\n";
        }
      }
      $row = [$old_site_path, $new_site_path, $old_site_title, $new_site_title];

      if (str_contains($new_site_title, $old_site_title)) {
        $good[] = $row;
        fputcsv($fp_good_outfile, $row);
      }
      else {
        $bad[] = $row;
        fputcsv($fp_bad_outfile, $row);
      }
    }
    else {
      $unprocessed[] = $line;
    }
  }
  if ($num = count($good)) {
    echo "$num GOOD records\n";
  }
  if ($num = count($bad)) {
    echo "$num BAD records\n";
  }
  if ($num = count($unprocessed)) {
    echo "$num UNPROCESSED lines\n";
    foreach ($unprocessed as $line) {
      fwrite($fp_unprocessed_outfile, $line . "\n");
    }
  }
  fclose($fp_good_outfile);
  fclose($fp_bad_outfile);
  fclose($fp_unprocessed_outfile);
  fclose($fp_infile);
}
