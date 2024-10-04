#!/usr/bin/env drush
<?php

use Drush\Drush;

/*
 * Can pass as args:
 * --sample <size> if set, samples up to the specified paths of each content type+language combo
 * --file <filename> Complete path to CSV file, or path relative to /var/www/web
 */
[$csv, $samplePaths] = parse_args($extra);

if (!$csv) {
  Drush::output()->writeln("<error>Can't read or find CSV file.</error>");
  exit(1);
}

Drush::output()->writeln("<info>Reading CSV file.</info>");
$counts = [];
foreach (read_csv($csv) as $line) {
  if ($line->pageID == 5) {
    // Not a node.
    continue;
  }

  $countKey = $line->language . '/' . $line->pageType;
  if (isset($counts[$countKey])) {
    $counts[$countKey] += 1;
  }
  else {
    $counts[$countKey] = 0;
  }

  if ($samplePaths > 0 && $counts[$line->language . '/' . $line->pageType] > $samplePaths) {
    continue;
  }

  Drush::output()->writeln("<info>Checking {$line->pageID}: {$line->fullURL}.</info>");
  try {
    $datalayer = fetch_datalayer($line->fullURL);
    compare_data($datalayer, $line);
  }
  catch (Exception $e) {
    Drush::output()->writeln('<error>' . $e->getMessage() . '</error>');
  }
}

/**
 * Parse command line arguments.
 */
function parse_args(array $extra): array {
  $samplePaths = 0;
  $csv = realpath(__DIR__ . '/../../web/modules/custom/usagov_ssg_postprocessing/files/published-pages.csv');

  while ($arg = array_shift($extra)) {
    switch ($arg) {
      case '--file':
        $csv = array_shift($extra);
        $csv = realpath($csv);

        if (!$csv || !is_readable($csv)) {
          throw new \InvalidArgumentException('Missing or unreadable CSV file. Remember to specify full path or relative to /var/www/web.');
        }
        break;

      case '--sample':
        $size = array_shift($extra);
        if (!ctype_digit($size)) {
          throw new \InvalidArgumentException("Sample size must be numeric.");
        }
        $size = (int) $size;
        if ($size <= 0) {
          throw new \InvalidArgumentException("Sample size must be a positive integer.");
        }

        $samplePaths = $size;
        break;

      default:
        throw new \InvalidArgumentException('Unknown options' . $arg);
    }
  }

  return [$csv, $samplePaths];
}

/**
 * Compares fetched data against CSV data and displays any differences.
 */
function compare_data(array $datalayer, CSVRow $row): void {
  $diff = FALSE;
  $map = [
    'nodeID' => 'pageID',
    'contentType' => 'contentType',
    'basicPagesubType' => 'pageSubType',
    'homepageTest' => 'homepage',
    'Page_Type' => 'pageType',
    // Will separately compare language.
    // 'Taxonomy_Text_1' => 'taxonomyLevel1',
    'Taxonomy_Text_2' => 'taxonomyLevel2',
    'Taxonomy_Text_3' => 'taxonomyLevel3',
    'Taxonomy_Text_4' => 'taxonomyLevel4',
    'Taxonomy_Text_5' => 'taxonomyLevel5',
    'Taxonomy_Text_6' => 'taxonomyLevel6',
    'Taxonomy_URL_1' => 'taxonomyURLLevel1',
    'Taxonomy_URL_2' => 'taxonomyURLLevel2',
    'Taxonomy_URL_3' => 'taxonomyURLLevel3',
    'Taxonomy_URL_4' => 'taxonomyURLLevel4',
    'Taxonomy_URL_5' => 'taxonomyURLLevel5',
    'Taxonomy_URL_6' => 'taxonomyURLLevel6',
  ];

  foreach ($map as $key => $prop) {
    if ($datalayer[$key] !== $row->$prop) {
      $diff = TRUE;
      Drush::output()->writeln(
        "<error>... Mismatch {$key} ({$datalayer[$key]}) and {$prop} ({$row->$prop}) </error>");
      var_dump($datalayer[$key], $row->$prop);
    }
  }

  if (isset($datalayer['Taxonomy_Text_7'])) {
    Drush::output()->writeln(
      "<error>... More than 6 levels returned</error>");
    var_dump($datalayer);
  }

  switch ($datalayer['language']) {
    case 'en':
      if ($datalayer['Taxonomy_Text_1'] !== 'Home') {
        $diff = TRUE;
        Drush::output()->writeln(
          "<error>... Wrong Taxonomy_Text 1 ({$datalayer['Taxonomy_Text_1']}) </error>");
      }
      break;

    case 'es':
      if ($datalayer['Taxonomy_Text_1'] !== 'Página principal') {
        $diff = TRUE;
        Drush::output()->writeln(
        "<error>... Wrong Taxonomy_Text 1 ({$datalayer['Taxonomy_Text_1']}) </error>");
      }
      break;

    default:
      if ($datalayer[$key] !== $row->$prop) {
        $diff = TRUE;
        Drush::output()->writeln(
          "<error>... Unknown or missing language ({$datalayer['language']}) </error>"
        );
      }
  }

  if (!$diff) {
    Drush::output()->writeln(
      "<info>... No differences found. </info>"
    );
  }
}
/**
 * Reads and return CSV data, one row at a time.
 *
 * @return Generator<CSVRow>
 */
function read_csv(string $filename) {
  $file = new \SplFileObject($filename);
  $file->setFlags(\SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
  $header = $file->fgetcsv();
  while ($row = $file->fgetcsv()) {
    yield new CSVRow(
      hierarchyLevel: $row[0],
      pageType: $row[1],
      pageSubType: $row[2],
      contentType: $row[3],
      friendlyURL: $row[4],
      pageID: $row[5],
      pageTitle: $row[6],
      fullURL: $row[7],
      language: $row[8],
      taxonomyLevel2: $row[9],
      taxonomyLevel3: $row[10],
      taxonomyLevel4: $row[11],
      taxonomyLevel5: $row[12],
      taxonomyLevel6: $row[13],
      taxonomyURLLevel1: $row[14],
      taxonomyURLLevel2: $row[15],
      taxonomyURLLevel3: $row[16],
      taxonomyURLLevel4: $row[17],
      taxonomyURLLevel5: $row[18],
      taxonomyURLLevel6: $row[19],
      homepage: $row[20],
      toggleURL: $row[21],
    );
  }
}

/**
 * Parse datalayer from the HTML for given web page.
 *
 * @return array|RuntimeException
 */
function fetch_datalayer(string $fullURL): array {

  $html = file_get_contents($fullURL);

  if (!$html) {
    throw new \RuntimeException("Could not open page $fullURL");
  }

  if (!preg_match('|<script id=\"taxonomy-data\">([^<]+)</script>|', $html, $match)) {
    throw new \RuntimeException("Datalayer not found in page $fullURL");
  }

  if (preg_match('/{[^}]+}/', $match[1], $props)) {
    return json_decode($props[0], TRUE, JSON_THROW_ON_ERROR);
  };

  return new \RuntimeException('Could not parse datalayer');
}


class CSVRow {

  public function __construct(
    public string $hierarchyLevel,
    public string $pageType,
    public ?string $pageSubType,
    public string $contentType,
    public string $friendlyURL,
    public string $pageID,
    public string $pageTitle,
    public string $fullURL,
    public string $language,
    public string $taxonomyLevel2,
    public string $taxonomyLevel3,
    public string $taxonomyLevel4,
    public string $taxonomyLevel5,
    public string $taxonomyLevel6,
    public string $taxonomyURLLevel1,
    public string $taxonomyURLLevel2,
    public string $taxonomyURLLevel3,
    public string $taxonomyURLLevel4,
    public string $taxonomyURLLevel5,
    public string $taxonomyURLLevel6,
    public string $homepage,
    public string $toggleURL,
  ) {
    if ($this->pageSubType === "") {
      $this->pageSubType = NULL;
    }
  }

}
