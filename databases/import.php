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
$totalBytes = filesize($filename . ".tsv");

printf("Starting Import at %s\n", date("Y-m-d H:i:s"));

if($handle) {
    $header = fgets($handle);

    $cLine = 1;
    $cLineStored = 1;
    $bytesRead = $header === false ? 0 : strlen($header);

    while(($line = fgets($handle)) !== false) {
        $bytesRead += strlen($line);
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
                "\033[999D Importing: %s%% - %s (%s%%) Stored - %s lines processed",
                round(($bytesRead / $totalBytes) * 100, 2),
                number_format($cLineStored, 0, ',', ' '),
                round(($cLineStored / $cLine) * 100, 2),
                number_format($cLine, 0, ',', ' '),
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

// Indexes are created after the bulk insert (cheaper than maintaining them
// row-by-row) and cover the columns the frontend actually filters on.
printf("\nCreating indexes at %s\n", date("Y-m-d H:i:s"));
$db->exec('CREATE INDEX IF NOT EXISTS "idx_items_date" ON "items" ("date")');
$db->exec('CREATE INDEX IF NOT EXISTS "idx_items_region" ON "items" ("region")');
$db->exec('CREATE INDEX IF NOT EXISTS "idx_items_festival" ON "items" ("festival")');

printf("Ending Importing at %s", date("Y-m-d H:i:s"));

//11 190 080
?>
