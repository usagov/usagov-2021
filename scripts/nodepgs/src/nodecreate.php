<?php

/**
 * @file
 * Call Drush in env.
 */
?>

<?php
global $argv;
$title = $argv[3];
$body = file_get_contents('../' . $argv[4]);
$ctype = $argv[5];
$lang = $argv[6] ?? 'en';

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
$count = 0;

// Check for published node.
foreach ($nids as $nid) {
  $node = Node::load($nid);
  if ($node->isPublished()) {
    $pnode = $nid;
    $count++;
  }
}

// echo $count . PHP_EOL;
if ($count == 0) {
  if (empty($nids) == TRUE) {
    echo 'Create node object with attached file.' . PHP_EOL;
    $node = Node::create(['type' => $ctype]);
    $node->set('title', $title);
    $node->body->value = $body;
    $node->body->format = 'html';
    $node->set('moderation_state', "published");
    $node->set('langcode', $lang);
    $node->uid = 1;
    $node->save();
    $message = 'create new ' . $node->id() . ' node from create code script';
    \Drupal::logger('create_code_script')->notice($message);
  }
}
else {
  echo 'Update published node of the count id.' . PHP_EOL;
  foreach ($nids as $nid) {
    $node = Node::load($nid);
    $node->uid = 1;
    $node->set('moderation_state', "published");
    $node->body->value = $body;
    $node->body->format = 'html';
    $node->set('langcode', $lang);
    $node->save();
    $message = 'Update published ' . $node->id() . ' node from create code script';
    \Drupal::logger('create_code_script')->notice($message);
  }
  $nid = $node->id() ?? 'no nid';
  echo 'this "' . $nid . ' - ' . $lang . '" node was created or updated' . PHP_EOL;
}
