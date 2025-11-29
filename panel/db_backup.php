<?php
// panel/db_backup.php - INSTANT SQL DOWNLOAD
require_once __DIR__ . '/_auth_check.php';
require_once __DIR__ . '/../includes/config.php';

// Connect
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli->select_db(DB_NAME);
$mysqli->query("SET NAMES 'utf8'");

// Get Tables
$queryTables = $mysqli->query('SHOW TABLES');
while($row = $queryTables->fetch_row()) { $target_tables[] = $row[0]; }

$content = "-- Beast8 Database Backup\n-- Date: ".date('Y-m-d H:i:s')."\n\n";

foreach($target_tables as $table) {
    $result = $mysqli->query('SELECT * FROM '.$table);
    $fields_amount = $result->field_count;
    $rows_num=$mysqli->affected_rows;
    
    $res = $mysqli->query('SHOW CREATE TABLE '.$table);
    $TableMLine = $res->fetch_row();
    $content .= "\n\n".$TableMLine[1].";\n\n";

    for ($i = 0, $st_counter = 0; $i < $fields_amount; $i++, $st_counter=0) {
        while($row = $result->fetch_row()) {
            if ($st_counter%100 == 0 || $st_counter == 0) {
                $content .= "\nINSERT INTO ".$table." VALUES";
            }
            $content .= "\n(";
            for($j=0; $j<$fields_amount; $j++) {
                $row[$j] = str_replace("\n","\\n", addslashes($row[$j]));
                if (isset($row[$j])) { $content .= '"'.$row[$j].'"' ; } else { $content .= '""'; }
                if ($j<($fields_amount-1)) { $content.= ','; }
            }
            $content .=")";
            if ( (($st_counter+1)%100==0 && $st_counter!=0) || $st_counter+1==$rows_num) { $content .= ";"; } else { $content .= ","; }
            $st_counter = $st_counter + 1;
        }
    } $content .= "\n\n\n";
}

// Force Download
$backup_name = "backup_".DB_NAME."_".date("Y-m-d_H-i-s").".sql";
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary"); 
header("Content-disposition: attachment; filename=\"".$backup_name."\""); 
echo $content; exit;
?>