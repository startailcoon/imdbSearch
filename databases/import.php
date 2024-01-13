<?php
    
class DBSQLite extends SQLite3 {

    function __construct($dbFile) {
        if(!file_exists($dbFile)) {
            throw new Exception("Database file '{$dbFile}' not found");
        }
        $this->open($dbFile);
    }
}

// Take input name from input
if(!isset($argv[1])) {
    throw new Exception("No input file given");
}

$filename = str_replace(".tsv", "", $argv[1]);

// Remove old database
if(file_exists($filename . ".sqlite3")) {
    unlink($filename . ".sqlite3");    
}

// Create file
$handle = fopen($filename . ".sqlite3", "w");
fclose($handle);

// Create database
$db = new DBSQLite($filename . ".sqlite3");
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode = wal;');
$stmt = $db->prepare('CREATE TABLE IF NOT EXISTS "items" ("id" INTEGER, "ordering" INTEGER, date TEXT, region TEXT, premiere INTEGER, wide INTEGER, "premiere_type" TEXT, festival TEXT, attributes TEXT)');
$stmt->execute();

// Read source file
$handle = fopen($filename . ".tsv", "r");

printf("Starting Import at %s\n", date("Y-m-d H:i:s"));

if($handle) {
    fgets($handle);

    $cLines = 0;
    // Count Lines
    while(($line = fgets($handle)) !== false) {
        $cLines++;
    }

    // Reset file pointer
    fseek($handle, 1);

    $cLine = 1;
    $cLineStored = 1;

    while(($line = fgets($handle)) !== false) {
        $data = explode("\t", $line);

        $id = str_replace("tt", "", $data[0]);
        $ordering = $data[1];
        $date = $data[2];
        $region = $data[3];
        $premiere = $data[4];
        $wide = $data[5];
        $premiereType = $data[6];
        $festival = $data[7];
        $attributes = str_replace("\r\n","",$data[8]);

        // Overwrite previous outputline
        if($cLine % 1000 == 0) {
            printf(
                "\033[999D Importing: %s%% - %s (%s%%) Stored - %s of %s lines processed",
                round(($cLine / $cLines) * 100, 2),
                number_format($cLineStored, 0, ',', ' '),
                round(($cLineStored / $cLine) * 100, 2),
                number_format($cLine, 0, ',', ' '),
                number_format($cLines, 0, ',', ' '),
            );
        }

        $cLine++;

        // Skip if festival is null
        // if($festival == "\N") { continue; }
        if($region != "SE") { continue; }

        $cLineStored++;

        $stmt = $db->prepare("INSERT OR IGNORE INTO items (id, ordering, date, region, premiere, wide, premiere_type, festival, attributes) VALUES (:id, :ordering, :date, :region, :premiere, :wide, :premiere_type, :festival, :attributes)");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':ordering', $ordering, SQLITE3_INTEGER);
        $stmt->bindValue(':date', $date, SQLITE3_TEXT);
        $stmt->bindValue(':region', $region, SQLITE3_TEXT);
        $stmt->bindValue(':premiere', $premiere, SQLITE3_TEXT);
        $stmt->bindValue(':wide', $wide, SQLITE3_TEXT);
        $stmt->bindValue(':premiere_type', $premiereType, SQLITE3_TEXT);
        $stmt->bindValue(':festival', $festival, SQLITE3_TEXT);
        $stmt->bindValue(':attributes', $attributes, SQLITE3_TEXT);
        $stmt->execute();
    }
}

fclose($handle);

printf("Ending Importing at %s", date("Y-m-d H:i:s"));

//11 190 080
?>
