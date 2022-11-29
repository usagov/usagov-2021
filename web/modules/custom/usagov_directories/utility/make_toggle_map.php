<?php

/**
 * This script reads Federal Directory Record data exported from the
 * previous USA.gov site and generates a mapping from one "mothership UUID" to
 * another for all nodes that specify a language toggle. We will then use this
 * mapping to set the "Language Toggle" (field_language_toggle) on directory records
 * on beta.
 *
 * We will have previously imported data to beta.USA.gov and populated
 * the mothership_uuid field. In the Mothership feed, the "English
 * Toggle" and "Spanish Toggle" fields specify a Mothership
 * node id.
 *
 * - akf, 2022-08-31
 */

$infile = $argv[1];
$outfile = $argv[2];

function main($infile, $outfile) {
  $fp_infile = fopen($infile, 'r');
  $fp_outfile = fopen($outfile, 'w');
  $uuid_map = [];  // Maps node_id to mothership_uuid
  $toggle_node_map = []; // Maps mothership_uuid to node_id of the toggle
  $data = [];  // We will generate this: array of array[ mothership_uuid => toggle_mothership_uuid]
  $array_indexes = NULL;

  // First, read the file and gather uuid -> node_id and node_id -> toggle node_id mappings
  while (($row = fgetcsv($fp_infile)) !== FALSE) {
    if (!$array_indexes) {
      $array_indexes = array_flip($row);
    }
    else {
      $mothership_uuid = $row[$array_indexes['UUID']];
      $node_id = $row[$array_indexes['Nid']];
      $uuid_map[$node_id] = $mothership_uuid;
      $toggle_node_id = $row[$array_indexes['English Toggle Node']] ?: $row[$array_indexes['Spanish Toggle Node']];
      $toggle_node_map[$mothership_uuid] = $toggle_node_id;
    }
  }
  fclose($fp_infile);

  // Next, generate a map of mothership_uuid => toggle mothership_uuid
  $num_uuids = 0;
  $num_want_toggle = 0;
  $num_missing = 0;
  foreach ($uuid_map as $node_id => $mothership_uuid) {
    $num_uuids++;
    if (array_key_exists($mothership_uuid, $toggle_node_map)) {
      if ($toggle_node_id = $toggle_node_map[$mothership_uuid]) {
        $num_want_toggle++;
        if (array_key_exists($toggle_node_id, $uuid_map)) {
          if ($toggle_mothership_uuid = $uuid_map[$toggle_node_id]) {
            $data[$mothership_uuid] = $toggle_mothership_uuid;
          }
        }
        else {
          $num_missing++;
          print("\tNo Mapping for toggle: " . $toggle_node_id . "\n");
        }
      }
    }
    else {
      print("OOPS: UUID not in toggle_node_map: " . $mothership_uuid . "\n");
    }
  }

  // Write the output.
  fputcsv($fp_outfile, ['mothership_uuid', 'toggle_mothership_uuid']);
  foreach ($data as $uuid => $toggle_uuid) {
    fputcsv($fp_outfile, [$uuid, $toggle_uuid]);
  }
  fclose($fp_outfile);
  print("\nUUIDS: $num_uuids\nWant Toggle: $num_want_toggle\nMissing: $num_missing\n");
  print("=== DONE ====");
}

main($infile, $outfile);
