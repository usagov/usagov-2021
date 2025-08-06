<?php

/**
 * @file
 * Script to publish all blog_post nodes.
 *
 * This script finds all blog_post nodes and sets their status to published.
 *
 * Usage:
 * @code
 * drush scr scripts/drush/publish_blog_posts.php
 * @endcode
 */

use Drupal\node\Entity\Node;

// Get the entity type manager.
$entity_type_manager = \Drupal::entityTypeManager();
$node_storage = $entity_type_manager->getStorage('node');

// Query for all blog_post nodes.
$query = $node_storage->getQuery()
  ->condition('type', 'blog_post')
  ->accessCheck(FALSE);

$nids = $query->execute();

if (empty($nids)) {
  echo "No blog_post nodes found.\n";
  return;
}

$total_nodes = count($nids);
echo "Found {$total_nodes} blog_post nodes.\n";

$published_count = 0;
$already_published_count = 0;
$error_count = 0;

// Process nodes in batches to avoid memory issues.
$batch_size = 50;
$batches = array_chunk($nids, $batch_size);

foreach ($batches as $batch_index => $batch_nids) {
  echo "Processing batch " . ($batch_index + 1) . " of " . count($batches) . "...\n";

  foreach ($batch_nids as $nid) {
    try {
      /** @var \Drupal\node\Entity\Node $node */
      $node = Node::load($nid);

      if (!$node) {
        echo "  Warning: Could not load node {$nid}\n";
        $error_count++;
        continue;
      }

      if (!$node->isPublished()) {
        // Set the node status to published.
        $node->setPublished();
        $node->save();
        $published_count++;
        echo "  Published node {$node->id()}: {$node->getTitle()}\n";
      }
      else {
        $already_published_count++;
        echo "  Node {$node->id()} already published: {$node->getTitle()}\n";
      }
    }
    catch (Exception $e) {
      $error_count++;
      echo "  Error processing node {$nid}: " . $e->getMessage() . "\n";
    }
  }

  // Clear the entity cache to prevent memory issues.
  $node_storage->resetCache($batch_nids);
}

echo "\n=== SUMMARY ===\n";
echo "Total nodes processed: {$total_nodes}\n";
echo "Newly published: {$published_count}\n";
echo "Already published: {$already_published_count}\n";
echo "Errors: {$error_count}\n";
echo "Script completed.\n";