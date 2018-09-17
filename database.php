<?php

class Database
{
	public $Settings = null;
	private $_connection = null;

	function __construct($DBSettings, $Fallback = [])
	{
		$this->Settings = $DBSettings ? $DBSettings : $Fallback;
		$this->_connection = new PDO("mysql:host={$this->Settings->Server};dbname={$this->Settings->Database}", $this->Settings->Username, $this->Settings->Password, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_DIRECT_QUERY => true, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
	}

	private function __order($OrderArr = null, $FieldArr = null)
	{
		if (is_array($OrderArr)) {
			foreach ($OrderArr as &$Order) {
				if (substr_count($Order, '-')) {
					$Order = str_replace('-', '', $Order) . " DESC";
				}
			}
			return implode(', ', $OrderArr);
		} elseif (is_string($OrderArr)) {
			if (substr_count($OrderArr, '-')) {
				$OrderArr = str_replace('-', '', $OrderArr) . " DESC";
			}
			return $OrderArr;
		} elseif ($FieldArr) {
			$retval = "LENGTH({$FieldArr[0]}) ASC, FIELD ({$FieldArr[0]}, ";
			$retval .= implode(",", $FieldArr[1]);
			$retval .= ")";
		} else {
			return Id;
		}
	}

	public function lastId()
	{
		return $this->_connection->lastInsertId();
	}

	//verilen sorguyu calistir (INSERT, UPDATE, DELETE)
	public function just_run($query, $para = [])
	{
		$statement = $this->_connection->prepare($query);
		$para2 = [];
		foreach ($para as $k => $v) {
			$para2[$k] = $v;
			if (is_array($v)) $para2[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if (is_object($v)) $para2[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		return $statement->execute($para2);
	}

	//verilen sorguyu calistir ve sonuclari cek (SELECT)
	public function run($query, $para = [])
	{
		$statement = $this->_connection->prepare($query);
		$statement->execute($para);
		return $statement->fetchAll();
	}

	public function where2str($where, $combiner = 'AND')
	{
		$wherestr = ' WHERE ';
		$values = array();
		if (array_key_exists(0, $where)) {
			if (is_string($where[0])) {
				if (strtolower($where[0]) == 'or') {
					$combiner = 'OR';
					unset($where[0]);
				}
			}
		}
		foreach ($where as $k => $v) {
			if (substr($k, -10) == '__contains') {
				$v = str_replace(array('"', "'"), '', $v);
				$v = "|$v|";
				$wherestr .= "(" . substr($k, 0, -10) . " like ?)";
				"(CONVERT(" . substr($k, 0, -10) . " USING latin1) LIKE CONVERT('%{$v}%' USING latin1))";
			} elseif (substr($k, -8) == '__starts') {
				$v = str_replace(array('"', "'"), '', $v);
				$v = str_replace(" ", "%", $v);
				$wherestr .= "(CONVERT(" . substr($k, 0, -8) . " USING latin1) LIKE CONVERT('{$v}%' USING latin1))";
			} elseif (substr($k, -8) == '__begins') {
				$v = str_replace(array('"', "'"), '', $v);
				$v = str_replace(" ", "%", $v);
				$wherestr .= "(CONVERT(" . substr($k, 0, -8) . " USING latin1) LIKE CONVERT('{$v}%' USING latin1))";
			} elseif (substr($k, -6) == '__ends') {
				$v = str_replace(array('"', "'"), '', $v);
				$v = str_replace(" ", "%", $v);
				$wherestr .= "(CONVERT(" . substr($k, 0, -6) . " USING latin1) LIKE CONVERT('%{$v}' USING latin1))";
			} elseif (substr($k, -6) == '__like') {
				$v = str_replace(array('"', "'"), '', $v);
				$v = str_replace(" ", "%", $v);
				$wherestr .= "(CONVERT(" . substr($k, 0, -6) . " USING latin1) LIKE CONVERT('%{$v}%' USING latin1))";
			} elseif (substr($k, -5) == '__lte') {
				$wherestr .= "(" . substr($k, 0, -5) . " <= ?)";
			} elseif (substr($k, -5) == '__gte') {
				$wherestr .= "(" . substr($k, 0, -5) . " >= ?)";
			} elseif (substr($k, -4) == '__lt') {
				$wherestr .= "(" . substr($k, 0, -4) . " < ?)";
			} elseif (substr($k, -4) == '__gt') {
				$wherestr .= "(" . substr($k, 0, -4) . " > ?)";
			} elseif (substr($k, -4) == '__nn') {
				$wherestr .= "(" . substr($k, 0, -4) . " IS NOT NULL)";
			} elseif (substr($k, -4) == '__ne') {
				$wherestr .= "(" . substr($k, 0, -4) . " != ?)";
			} elseif (substr($k, -4) == '__in') {
				if (!is_array($v)) {
					$v = [$v];
				}
				$v = '"' . implode('","', $v) . '"';
				$wherestr .= "(" . substr($k, 0, -4) . " IN ($v))";
			} elseif (substr($k, -5) == '__nin') {
				if (!is_array($v)) {
					$v = [$v];
				}
				$v = '"' . implode('","', $v) . '"';
				$wherestr .= "(" . substr($k, 0, -5) . " NOT IN ($v))";
			} elseif (is_null($v)) {
				$wherestr .= "($k IS ?)";
			} else {
				$wherestr .= "($k = ?)";
			}
			$wherestr .= " $combiner ";
			if (substr($k, -4) != '__in' && substr($k, -4) != '__nn' && substr($k, -5) != '__nin' && substr($k, -6) != '__like' && substr($k, -6) != '__ends' && substr($k, -8) != '__starts' && substr($k, -8) != '__begins') $values[] = $v;
		}
		$wherestr = substr($wherestr, 0, -strlen($combiner) - 2);
		return array($wherestr, $values);
	}

	//where2str fonksiyonunu WHERE metni olmadan dondurur
	public function nowhere2str($where, $combiner = 'AND')
	{
		$result = $this->where2str($where, $combiner);
		$result[0] = substr($result[0], 8, -1);
		return $result;
	}

	//o kosullara uyan kac kayit oldugunu getirir
	public function RowCount($para)
	{
		$para = array_merge([Conds => null, Table => null], $para);
		$query = "SELECT COUNT(Id) AS RowCount FROM {$para[Table]} ";
		$SorguParametreleri = [];
		if ($para[Conds]) {
			if (is_array($para[Conds])) {
				list($wherestr, $SorguParametreleri) = $this->where2str($para[Conds]);
				$query = $query . $wherestr;
			} else {
				$query .= " WHERE {$para[Conds]} ";
			}
		}
		return $this->run($query, $SorguParametreleri)[0][RowCount];
	}

	//parametrelere gore query calistirip sonuclarini ceker
	public function fetch($para)
	{
		global $a;
		$para = array_merge([Table => null, Fields => '*', Conds => [], Order => null, StartAt => 0, RowCount => 20000000000], $para);
		$query = "SELECT ";
		$SorguParametreleri = [];
		if (is_array($para[Fields])) $query .= implode(', ', $para[Fields]) . " FROM {$para[Table]} ";
		else $query .= "{$para[Fields]} FROM {$para[Table]} ";
		if ($para[Conds]) {
			if (is_array($para[Conds])) {
				list($wherestr, $SorguParametreleri) = $this->where2str($para[Conds]);
				$query = $query . $wherestr;
			} else {
				$query .= " WHERE {$para[Conds]} ";
			}
		}
		if ($para[Order]) {
			if (is_string($para[Order])) {
				if (substr($para[Order], -1) == '-') {
					$para[Order] = substr($para[Order], 0, -1);
					$query .= " ORDER BY {$para[Order]} DESC";
				} elseif (substr($para[Order], 0, 1) == '-') {
					$para[Order] = substr($para[Order], 1);
					$query .= " ORDER BY {$para[Order]} DESC";
				} else {
					$query .= " ORDER BY {$para[Order]} ";
				}
			} elseif (is_array($para[Order])) {
				$Gecici = "";
				foreach ($para[Order] as $eleman) {
					if (starts($eleman, '-')) $Gecici .= substr($eleman, 1) . " DESC, ";
					elseif (ends($eleman, '-')) $Gecici .= substr($eleman, 0, -1) . " DESC, ";
					else $Gecici .= "$eleman, ";
				}
				$Gecici = substr($Gecici, 0, -2);
				$query .= " ORDER BY {$Gecici} ";
			} elseif (hasKeys($para, OrderField)) {
				$retval = "FIELD ({$para[OrderField][0]}, ";
				$retval .= implode(",", $para[OrderField][1]);
				$query .= "ORDER BY {$retval})";
			}
		}
		if ($para[StartAt] && $para[RowCount]) {
			$query .= " LIMIT {$para[StartAt]}, {$para[RowCount]} ";
		} elseif ($para[RowCount]) {
			$query .= " LIMIT {$para[RowCount]} ";
		}
		$retval = $this->run($query, $SorguParametreleri);
		return $retval;
	}

}
