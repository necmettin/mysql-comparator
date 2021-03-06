<?php

require_once("./functions.php");
require_once("./database.php");

$sdb = makeClass(["Name"=>"", "Server"=>"", "Database"=>"", "Username"=>"", "Password"=>""]);
$tdb = makeClass(["Name"=>"", "Server"=>"", "Database"=>"", "Username"=>"", "Password"=>""]);

$sdb->conn = new Database($sdb);
$tdb->conn = new Database($tdb);

$sdbTables = extractTables($sdb->conn);
$tdbTables = extractTables($tdb->conn);

$diff1 = array_diff($sdbTables, $tdbTables);
if ($diff1) {
	pp("Creating tables that exist only in {$sdb->Name}");
	foreach ($diff1 as $TableName) {
		pp("\t$TableName created in {$tdb->Name}");
		$statement = showCreateTable($sdb->conn, $TableName);
		$tdb->conn->just_run($statement);
		$tdb->conn->just_run("TRUNCATE TABLE $TableName");
	}
}

pp("Comparing tables");
foreach ($sdbTables as $TableName) {
	$sdbCreate = getTableStructure($sdb->conn, $TableName);
	$tdbCreate = getTableStructure($tdb->conn, $TableName);
	$diff = array_diff($sdbCreate, $tdbCreate);
	if ($diff) {
		pp("\t$TableName is different.");
		foreach ($diff as $row) {
			$colname = explode("`", $row)[1];
			pp("\t\tAdding column $colname");
			$AlterStr = "ALTER TABLE $TableName";
			$AlterStr .= " ADD COLUMN $row";
			$AlterStr = substr($AlterStr, 0, -1) . ";";
			try {
				$tdb->conn->just_run($AlterStr);
			} catch (Exception $e) {
				if ($e->errorInfo[0] === "42S21") {
					pp("\t\t$colname exists, but is different, trying to modify.");
					$AlterStr = str_replace(" ADD COLUMN ", " MODIFY COLUMN ", $AlterStr);
					try {
						$tdb->conn->just_run($AlterStr);
					} catch (Exception $e) {
						pp($AlterStr);
						pp($e);
						die();
					}
				}
			}
		}
	}
}
pp("Done.\n");
