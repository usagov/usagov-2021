<?php

/*
    The purpose of this script is to compare, and show, what files exist in S3 versus
    file built in Tome, and visa-versa.

    This scripts expects a list of all files in both locations to already exist.
    Those files should have been built by tome-sync.sh before running this script.
*/

// Settings
$filePathTome = '/var/www/web/modules/custom/usagov_ssg_postprocessing/files/tome-files.txt';
$filePathS3 = '/var/www/web/modules/custom/usagov_ssg_postprocessing/files/s3-files.txt';

// Validate expectations
if (!file_exists($filePathTome)) {
    exit("Error - Could not find the report of all files that exist in Tome.\n");
}
if (!file_exists($filePathS3)) {
    exit("Error - Could not find the report of all files that exist in S3.\n");
}

// Load all existing files into arrays - Tome files
$filesInTome = [];
$logLines = explode("\n", file_get_contents($filePathTome));
foreach ($logLines as $line) {
    $newItem = substr($line, 30);
    $newItem = trim($newItem);
    if (!empty($newItem)) {
        $filesInTome[] = $newItem;
    }
}

// Load all existing files into arrays - S3 files
$filesInS3 = [];
$logLines = explode("\n", file_get_contents($filePathS3));
foreach ($logLines as $line) {
    $newItem = substr($line, 35);
    $newItem = trim($newItem);
    if (!empty($newItem)) {
        $filesInS3[] = $newItem;
    }
}

// Find files that are in S3 but not in Tome
$missingFilesInTome = array_diff($filesInS3, $filesInTome);
$count = count($missingFilesInTome);
print "There are {$count} files that exist in S3 that are missing in Tome. They are: \n";
print_r(array_values($missingFilesInTome));

// Find files that are in Tome but not in S3
$missingFilesInS3 = array_diff($filesInTome, $filesInS3);
$count = count($missingFilesInS3);
print "There are {$count} files that exist in Tome that are missing in S3. They are: \n";
print_r(array_values($missingFilesInS3));
