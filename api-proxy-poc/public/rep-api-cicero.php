<?php

// Request-validation
if (empty($_REQUEST['address'])) {
    exit('Missing required parameter: address');
}

// Build URL
$url = 'https://app.cicerodata.com/v3.1/official';
$url .= '?search_loc=' . urlencode($_REQUEST['address']);
$url .= '&short&format=json&pretty=1';
$url .= '&token=' . getenv('CICERO_TOKEN');

// Curl the source server
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Referer: https://www.usa.gov"]);
print curl_exec($ch);
// print $url;
curl_close($ch);

