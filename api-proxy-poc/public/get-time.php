<?php

// Override the default cache-time
header("Cache-Control: public, max-age=500");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 500) . " GMT");

// Say what the current time is
print "The curent Unix Epoch Time is: \n" . time() . "\n";

