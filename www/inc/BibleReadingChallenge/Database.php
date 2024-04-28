<?php

namespace BibleReadingChallenge;

class Database {
  private static $instance;
  private \SQLite3 $db;

  private function __construct()
  {
    $this->db = new \SQLite3(DB_FILE);
    $this->db->busyTimeout(250);
  }
  
  private function __clone()
  {
      // Do nothing
  }

  private function __wakeup()
  {
      // Do nothing
  }

	private function format_db_vals ($db_vals, array $options = [])
  {
		$options = array_merge([
			"source" => $_POST
		], $options);
		return $this->map_assoc(function ($col, $val) use ($options) {

			// Was a value provided for this column ("col" => "val") or not ("col")?
			$no_value_provided = is_int($col);
			if ($no_value_provided)
				$col = $val;

			// The modifiers should not contain regex special characters. If they do, then we will have to use preg_quote().
			$modifiers = [
				"nullable" => "__",
				"literal" => "##"
			];

			// Check for column modifiers
			if (preg_match("/^(" . implode("|", $modifiers) . ")/", $col, $matches))
				$col = substr($col, 2);

			// Keep track of whether each modifier is present (true) or not
			$modifiers = $this->map_assoc(function ($name, $symbol) use ($matches) {
				return [$name => $matches && $matches[1] == $symbol];
			}, $modifiers);

			$val = $no_value_provided ? $options["source"][$col] : $val;
			// If it's not literal, then transform the value
			if (!$modifiers["literal"])
				$val = $modifiers["nullable"] && ($val === null || $val === false || $val === 0 || !strlen($val))
					? "NULL"
					: ("'" . $this->esc($val) . "'");

			return [ $col => $val ];
		}, $db_vals);
	}

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function get_db()
  {
    return $this->db;
  }

	public function query ($query, $return = "")
  {
		$result = $this->db->query($query);
		if (!$result) {
			echo "<p><b>Warning:</b> A sqlite3 error occurred: <b>" . $this->db->lastErrorMsg() . "</b></p>";
			debug($query);
		}
		if ($return == "insert_id")
			return $this->db->lastInsertRowID();
		if ($return == "num_rows")
			return $this->db->changes();
		return $result;
	}

	public function select ($query)
  {
		$rows = $this->query($query, null);
		for ($result = []; $row = $rows->fetchArray(); $result[] = $row) {
			foreach(array_keys($row) as $key)
				if (is_numeric($key))
					unset($row[$key]);
		}
		return $result;
	}

	public function col ($query)
  {
		$row = $this->query($query, null)->fetchArray();
		return $row ? $row[0] : null;
	}

	function cols ($query)
  {    
		$rows = $this->query($query, null);
		if ($rows) {
			$results = [];
			while ($row = $rows->fetchArray(SQLITE3_NUM))
				$results[] = $row[0];
			return $results;
		}
		return null;
	}

	public function row ($query)
  {
		$results = $this->select($query);
		return $results[0];
	}

	/**
	 * @param $table
	 * @param $vals	array	An associative array of columns and values to update.
	 * 						Each value will be converted to a string UNLESS its
	 * 						corresponding column name begins with "__", in which
	 *						case its literal value will be used.
	 * @param $where
	 */
	public function update ($table, $vals, $where)
  {
		$SET = array();
		foreach ($this->format_db_vals($vals) as $col => $val) {
			$col = preg_replace("/^__/", "", $col, 1, $use_literal);
			$SET[] = "$col = $val";
		}

		$this->query("
			UPDATE $table
			SET " . implode(",", $SET) . "
			WHERE $where
		", null);
	}

	public function insert ($table, array $db_vals, array $options = [])
  {
		$db_vals = $this->format_db_vals($db_vals, $options);
		return $this->query("
			INSERT INTO $table (" . implode(", ", array_keys($db_vals)) . ")
			VALUES (" . implode(", ", array_values($db_vals)) . ")
		", "insert_id");
	}

	public function num_rows ($query)
  {
		$i = 0;
		$res = $this->query($query, null);
		while ($res->fetchArray(SQLITE3_NUM))
			$i++;
		return $i;
	}

	public function esc ($string)
  {
		return $this->db->escapeString($string);
	}

	public function esc_like ($string)
  {
		return $this->esc(str_replace(
			["\\", "_", "%"],
			["\\\\", "\\_", "\\%"],
			$string
		));
	}

	private function map_assoc (callable $callback, array $arr) {
		$ret = [];
		foreach($arr as $k => $v) {
			$u =
				$this->get_num_params($callback) == 1
					? $callback($v)
					: $callback($k, $v);
			$ret[key($u)] = current($u);
		}
		return $ret;
	}

  private function get_num_params (callable $callback) {
		try {
			return (new \ReflectionFunction($callback))->getNumberOfParameters();
		}
		catch (\ReflectionException $e) {}
	}
}