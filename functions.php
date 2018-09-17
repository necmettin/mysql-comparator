<?php

//pretty print
function pp()
{
	foreach (func_get_args() as $arg) {
		print_r($arg);
		echo " ";
	}
	echo "\n";
}

//converts a key-value array to a standard class
function makeClass($var)
{
	return json_decode(json_encode($var), 0);
}

function extractTables($db) {
	$tables = $db->run("SHOW TABLES");
	$tables = array_map(function($element){
		return array_values($element)[0];
	}, array_values($tables));
	return $tables;
}

function showCreateTable($db, $tablename) {
	$statement = $db->run("SHOW CREATE TABLE $tablename");
	return $statement[0]["Create Table"];
}

function getTableStructure($db, $tablename) {
	$structure = explode("\n", showCreateTable($db, $tablename));
	$structure = array_slice($structure, 1, -1);
	$structure = array_filter($structure, function($row){
		if (substr_count($row, "PRIMARY KEY")) {
			return false;
		}
		if (substr_count($row, "  KEY `")) {
			return false;
		}
		return true;
	});
	sort($structure);
	return $structure;
}