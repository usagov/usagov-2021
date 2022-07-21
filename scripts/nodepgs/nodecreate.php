#!/usr/bin/env drush
<?php
global $argv;
// var_dump($argv);
$title = $argv[3];
$body = file_get_contents('../' . $argv[4]);
$ctype = $argv[5];

use \Drupal\Core\Entity\RevisionLogInterface;

// Expect title as $args[3] and body as $args[4] and content type as $args[5]
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

//check for published node
foreach ($nids as $nid) {
  $node = \Drupal\node\Entity\Node::load($nid);
  if($node->isPublished()) {
    $pnode = $nid;
    $count++;
  }
}

echo $count . PHP_EOL;
if ($count == 0) {
  echo '-----------' . PHP_EOL;
  // check for the isLatestRevision
  var_dump($nids);
  foreach ($nids as $nid) {
    dpm($nid);
    $node = \Drupal\node\Entity\Node::load($nid);
    if($node->isLatestRevision()) {
      var_dump($node->isLatestRevision());
      var_dump(get_class_methods($node));
      $node->setTitle($title);
    $node->setPublished();
    $node->save();
    echo 'update title' . PHP_EOL;
    }
  }
}
  
//   $node->setTitle('The new Title');
//   $node->setPublished();
//   $node->save();



// $new_state = 'published';
// $entity->set('moderation_state', $new_state);
// if ($entity instanceof RevisionLogInterface) {
//   $entity->setRevisionLogMessage('Changed moderation state to Published.');
//   $entity->setRevisionUserId($this->currentUser()->id());
// }
// $entity->save();

//----------
// create new node
// $node = new stdClass();  // Create a new node object
// $node->type = 'article';  // Content type
// $node->language = LANGUAGE_NONE;  // Or e.g. 'en' if locale is enabled
// node_object_prepare($node);  //Set some default values

// $node->title = $args[1];
// $node->body[$node->language][0]['value'] = $args[2];

// $node->status = 1;   // (1 or 0): published or unpublished
// $node->promote = 0;  // (1 or 0): promoted to front page or not
// $node->sticky = 0;  // (1 or 0): sticky at top of lists or not
// $node->comment = 1;  // 2 = comments open, 1 = comments closed, 0 = comments hidden
// // Add author of the node
// $node->uid = 1;

// // Save the node
// node_save($node);