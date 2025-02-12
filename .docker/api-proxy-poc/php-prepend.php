<?php

// Logic for checking Referer goes here
// We can check for $_SERVER[''] variables/headers
// if (strpos($_SERVER['HTTP_REFERER'], 'usa.gov') === FALSE) {
//   exit("Access Denied - Bad HTTP_REFERER \n");
// }

// Default Varnish-cache time
header("Cache-Control: public, max-age=500");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 500) . " GMT");

