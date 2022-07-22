<?php

/**
 * @file
 * Call Drush in env.
 */
?>

<?php
global $argv;
// var_dump($argv);
$title = $argv[3];
$body = file_get_contents('../' . $argv[4]);
$ctype = $argv[5];

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\RevisionLogInterface;

// Expect title as $args[3] and body as $args[4] and content type as $args[5].
if (!isset($argv[3]) || trim($argv[3]) == '' || !isset($argv[4]) || trim($argv[4]) == '' || !isset($argv[5]) || trim($argv[5]) == '') {
  print "Expecting three arguments on the command line, title, body and content type, for the new node\n";
  exit;
}
$entity_type = 'node';
$query = \Drupal::entityTypeManager()->getStorage($entity_type)->getQuery();
$query->condition('title', $title);
$nids = $query->execute();
var_dump($nids);
$count = 0;

// Check for published node.
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if ($node->isPublished()) {
    $pnode = $nid;
    $count++;
  }
}

echo $count . PHP_EOL;
if ($count == 0) {
  if (empty($nids) == TRUE) {
    // Create node object with attached file.
    $node = Node::create(['type' => $ctype]);
    $node->set('title', $title);
    $node->body->value = $body;
    $node->body->format = 'html';
    $node->set('moderation_state', "published");
    $node->field_page_intro = '1045';
    $node->uid = 1;
    $node->save();

  }
  else {
    foreach ($nids as $nid) {
      dpm($nid);
      $node = Node::load($nid);
      if ($node->isLatestRevision()) {
        // Update latest revision to publish
        // $node->field_page_intro = '1128';.
        $node->body->value = $body;
        $node->body->format = 'html';
        $node->set('moderation_state', "published");
        $node->save();
        dpm($node->id());
      }
      else {


      }
    }
  }
}
else {
  // Update published node of the count id.
  foreach ($nids as $nid) {
    dpm($nid);
    $node = Node::load($nid);
    dpm(get_class_methods($node));
    // $node->field_page_intro = '1128';
    $node->uid = 1;
    $node->set('moderation_state', "published");
    $node->save();
    dpm($node->id());
  }
}
