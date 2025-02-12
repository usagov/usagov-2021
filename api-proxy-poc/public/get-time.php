<?php

header("Cache-Control: public, max-age=5");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 5) . " GMT");

print "The curent Unix Epoch Time is: ";
print time();
