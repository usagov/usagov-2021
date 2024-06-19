#!/usr/bin/env drush
<?php

// Loads json data from benefits-category-export and updates nodes with categories.

// find which argument is this script so that we can use the argument that follows it
// for the path to the input file.
$fileArg = null;
foreach ($_SERVER['argv'] as $index => $value) {
  if (str_ends_with($value, basename(__FILE__))) {
    $fileArg = $index + 1;
    break;
  }

}

if (!$fileArg || !isset($_SERVER['argv'][$fileArg])) {
  \Drush\Drush::output()->writeln("<error>Argument for import file is missing.</error>");
  exit;
}

$file = $_SERVER['argv'][$fileArg];

$cwd = getcwd();
\Drush\Drush::output()->writeln("<info>Current Working Dir: {$cwd}</info>");
if (!str_starts_with($file, '/')) {
  $fullPath = $cwd . '/' . $file;

  if (!realpath($fullPath)) {
    \Drush\Drush::output()->writeln("<error>Can not find relative file at {$fullPath}</error>");
    exit;
  }
}

$file = realpath($file);

if (!$file) {
  \Drush\Drush::output()->writeln("<error>File does not exist.</error>");
  exit;
}

if (!is_readable($file)) {
  \Drush\Drush::output()->writeln("<error>File is not readable.</error>");
  exit;
}

\Drush\Drush::output()->writeln("<info>Loading data file: {$file}</info>");

$benefitsTerms = getTermMap('benefits_category');

$handle = new SplFileObject($file);
$handle->setFlags(SplFileObject::SKIP_EMPTY);
$i = 0;
while (!$handle->eof()) {
  $line = trim($handle->getCurrentLine());
  if (empty($line)) {
    continue;
  }
  try {
    $data = json_decode($line, false, 512, JSON_THROW_ON_ERROR);
    $termIds = array_map(fn($term) => array_search($term, $benefitsTerms), $data->terms);
    $termIds = array_filter($termIds);
    if ($node = \Drupal\node\Entity\Node::load($data->nid)) {
      //\Drush\Drush::output()->writeln("<info>Updating node nid={$data->nid}</info>");
      \Drush\Drush::output()->write("<info>.</info>");
      $node->set('field_benefits_category', $termIds);
      $node->save();
    } else {
      \Drush\Drush::output()->writeln("<error>Could not find node nid={$$data->nid}</error>");
    }
    if (++$i % 25 === 0) {
      \Drush\Drush::output()->writeln("<info> - {$i} </info>");
    }
  } catch (JsonException $e) {
    var_dump($line);
    trigger_error($e->getMessage(), E_USER_WARNING);
  }
}

if ($i % 25 <> 0) {
  \Drush\Drush::output()->writeln("<info> - {$i} </info>");
}

function getTermMap(string $vid) {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vid])
  ;
  $map = [];
  foreach ($terms as $term) {
    $map[$term->id()] = $term->name->value;
  }
  return $map;
}
