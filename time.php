<?php

include("include/functions.inc.php");
authenticate();



// General Time Logging section - for everone
$body .= timelogger();






showContent($body);
