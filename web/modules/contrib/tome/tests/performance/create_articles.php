<?php

/**
 * @file
 * Script for bulk creating articles and users.
 */

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Component\Utility\Random;

$random = new Random();

for ($i = 0; $i < 10; ++$i) {
  $user = User::create([
    'roles' => ['administrator'],
  ]);
  $user->name->generateSampleItems();
  $user->save();
}

for ($i = 0; $i < 100; ++$i) {
  $node = Node::create([
    'type' => 'article',
  ]);
  $node->title->generateSampleItems();
  $node->field_image->generateSampleItems();
  $node->body->generateSampleItems();
  $node->uid->generateSampleItems();
  $node->save();
}
