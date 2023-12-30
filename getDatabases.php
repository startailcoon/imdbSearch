<?php

// Return all databases from the current directory
$files = scandir(__DIR__ . "/databases");
$databases = array();

foreach($files as $file) {
    if(strpos($file, ".sqlite3") !== false) {
        $databases[] = $file;
    }
}

rsort($databases);

echo json_encode($databases);

?>