<?php

// Request-validation
if (empty($_REQUEST['address'])) {
    exit('Missing required parameter: address');
}

// Build URL
$url = 'https://content.googleapis.com/civicinfo/v2/representatives';
$url .= '?address=' . urlencode($_REQUEST['address']);
$url .= '&key=' . getenv('GOOGLE_KEY');

// Curl the source server
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Referer: https://www.usa.gov"]);
print curl_exec($ch);
curl_close($ch);
