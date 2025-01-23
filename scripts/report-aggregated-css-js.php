<?php

global $scanProduction;
$scanProduction = true;
_main_UsaGov2180report();
exit();

function _main_UsaGov2180report() {

    // Print welcome message
    print "==============================\n";
    print "=                            =\n";
    print "= Hello,                     =\n";
    print "= I am a generator that will =\n";
    print "= report on what CSS/JS      =\n";
    print "= assets are being used on   =\n";
    print "= each page of the static    =\n";
    print "= site.                      =\n";
    print "=                            =\n";
    print "= [ℹ] Jira:                  =\n";
    print "= This was created for       =\n";
    print "= ticket USAGOV-2180.        =\n";
    print "=                            =\n";
    print "= [⚠] REMEMBER:              =\n";
    print "= This script expects you to =\n";
    print "= run bin/static-site first. =\n";
    print "=                            =\n";
    print "==============================\n";
    print "\n";
    sleep(2);

    // Change directory to the repository's root
    chdir(__DIR__ . '/../');

    // If there was a previous report, clear it out
    if (file_exists('report.csv')) {
        print "WARNING: A previous report is in the way. This file will be deleted before starting a new report!\n";
        print "Ctrl+C this process now if you dont want this to happen ...\n";
        print "(waiting 8 seconds)\n";
        print "\n";
        sleep(8);
    }

    // Put headers into the CSV report
    file_put_contents('report.csv', "\"Page Path\",\"Asset Type\",\"Asset URL\",\"Scope\",\"Delta\",\"Language\",\"Theme\",\"Include\",\"Query\"\n", FILE_APPEND);

    // Start scanning the html directory structure from <RepoRoot>/html
    scanDirectory('./html');

    // Say that we are done
    print "\ndone.\n";
    print "Report has been written to: report.csv\n\n";

    // Give the total breakdown on duplicate "include" URL-queries
    global $reportUniqCounts;
    foreach ($reportUniqCounts as $assetPath => $countsByLang) {
        $count = count($countsByLang['en']);
        if ($count > 1) {
            print "\n[!] WARNING [!] - Duplicate(s) detected!\n";
            print "Language: es\n";
            print "Asset Path: {$assetPath}\n";
            print "Found {$count} unique include-url-queries for this asset: \n";
            foreach ($countsByLang['en'] as $index => $includeQuery) {
                print "    " . ($index+1) . ": {$includeQuery}\n";
            }
            print "\n";
        }
        $count = count($countsByLang['es']);
        if ($count > 1) {
            print "\n[!] WARNING [!] - Duplicate(s) detected!\n";
            print "Language: es\n";
            print "Asset Path: {$assetPath}\n";
            print "Found {$count} unique include-url-queries for this asset: \n";
            foreach ($countsByLang['en'] as $index => $includeQuery) {
                print "    " . ($index+1) . ": {$includeQuery}\n";
            }
            print "\n";
        }
    }
}

function scanDirectory($dir) {

    global $scanProduction;

    $path = $dir . '/index.html';
    if (is_file($path)) {
        if (!$scanProduction) {
            print "Scanning: {$path} \n";
            scanFile($path);
        } else {
            $path = realpath($path);
            $path = str_replace(realpath(getcwd()), '', $path);
            $path = ltrim($path, '/');
            $url = 'https://www.usa.gov/' . str_replace('/index.html', '', substr($path, 5));
            print "Scanning: {$url} \n";
            scanFile($url);
        }
    }
    
    foreach (scandir($dir) as $item) {

        // Skip self and parent
        if ($item === '.' || $item === '..') {
            continue;
        }

        // If this is a directory, search another level deeper
        $nextDir = $dir . '/' . $item;
        if (is_dir($nextDir)) {
            scanDirectory($nextDir);
        }
    }
}

function scanFile($filePath) {

    // Load the content of this HTML file
    $html = @file_get_contents($filePath);
    if (empty($html)) {
        print "    Error - failed to load HTML. Skipping ...\n";
        return;
    }

    // Extract assets used
    list($css, $js) = extractAssets($html);

    // Report CSS usage
    foreach ($css as $item) {
        appendToReport($filePath, 'css', $item);
    }

    // Report JS usage
    foreach ($js as $item) {
        appendToReport($filePath, 'js', $item);
    } 
}

function appendToReport($filePath, $type, $url) {

    // Parse the URL
    $parsedUrl = parse_url($url);

    // Parse the URL query-string(s)
    $queryParts = [];
    $parsedUrl['query'] = empty($parsedUrl['query']) ? '' : $parsedUrl['query'];
    $assetPath = $parsedUrl['path'];
    parse_str($parsedUrl['query'], $queryParts);

    // Get known common query-string
    $scope = empty($queryParts['scope']) ? '-' : $queryParts['scope'];
    $delta = empty($queryParts['delta']) ? '-' : $queryParts['delta'];
    $language = empty($queryParts['language']) ? '-' : $queryParts['language'];
    $theme = empty($queryParts['theme']) ? '-' : $queryParts['theme'];
    $include = empty($queryParts['include']) ? '-' : $queryParts['include'];

    // Append to report
    file_put_contents('report.csv', "\"{$filePath}\",\"{$type}\",\"{$assetPath}\",\"{$scope}\",\"{$delta}\",\"{$language}\",\"{$theme}\",\"{$include}\",\"{$parsedUrl['query']}\"\n", FILE_APPEND);

    // Count number of unique "include"-queries for each JSS/JS path by language
    if (strlen($include) > 2) {

        global $reportUniqCounts;
        if (empty($reportUniqCounts)) {
            $reportUniqCounts = [];
        }
        if (empty($reportUniqCounts[$assetPath])) {
            $reportUniqCounts[$assetPath] = [
                'en' => [],
                'es' => [],
            ];
        }
        if (!in_array($include, $reportUniqCounts[$assetPath][$language])) {
            $reportUniqCounts[$assetPath][$language][] = $include;
        }
    }
}

function extractAssets($html) {

    // Initialize arrays to store CSS and JS file links
    $cssFiles = [];
    $jsFiles = [];

    // Use DOMDocument to parse the HTML markup
    $dom = new DOMDocument();

    // Suppress warnings due to malformed HTML
    libxml_use_internal_errors(true);

    // Load the HTML markup
    $dom->loadHTML($html);

    // Clear any parsing errors
    libxml_clear_errors();

    // Find all <link> elements
    foreach ($dom->getElementsByTagName('link') as $link) {
        // Check if it is a CSS file
        if ($link->getAttribute('rel') === 'stylesheet' && $link->hasAttribute('href')) {
            $cssFiles[] = $link->getAttribute('href');
        }
    }

    // Find all <script> elements
    foreach ($dom->getElementsByTagName('script') as $script) {
        // Check if it has a "src" attribute (external JS file)
        if ($script->hasAttribute('src')) {
            $jsFiles[] = $script->getAttribute('src');
        }
    }

    // Return the results as an associative array
    return [$cssFiles, $jsFiles];
}
