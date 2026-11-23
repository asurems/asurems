<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

if (!verifyCsrfToken($_GET['token'] ?? '')) {
    http_response_code(403);
    exit('Invalid backup request. Return to Backup & Maintenance and try again.');
}

$dbName=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$filename='asu_rems_backup_'.date('Ymd_His').'.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('X-Content-Type-Options: nosniff');

echo "-- ASU-REMS Database Backup\n";
echo "-- Database: `".str_replace('`','',$dbName)."`\n";
echo "-- Generated: ".date('Y-m-d H:i:s')."\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSTART TRANSACTION;\n\n";

$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $table){
    $safeTable=str_replace('`','',$table);
    $create=$pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_NUM);
    echo "-- --------------------------------------------------------\n";
    echo "-- Table `{$safeTable}`\n\n";
    echo "DROP TABLE IF EXISTS `{$safeTable}`;\n";
    echo ($create[1]??'').";\n\n";
    $stmt=$pdo->query("SELECT * FROM `{$safeTable}`");
    $columns=null;
    while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
        if($columns===null){$columns=array_keys($row);}
        $colSql=implode(',',array_map(fn($c)=>'`'.str_replace('`','',$c).'`',$columns));
        $values=[];
        foreach($columns as $col){$value=$row[$col];$values[]=$value===null?'NULL':$pdo->quote((string)$value);}
        echo "INSERT INTO `{$safeTable}` ({$colSql}) VALUES (".implode(',',$values).");\n";
    }
    echo "\n";
}

echo "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n";
exit;
