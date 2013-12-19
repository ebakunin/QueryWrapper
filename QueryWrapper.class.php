<?php
/**
 * QueryWrapper class
 *
 * @author  Eric Christenson (EricChristenson.com)
 *
 * A singleton class. No constructors.
 *
 * @method  fetchAll    Runs a query and returns a multi-level array.
 * @method  fetchAssoc  Runs a query and returns an associative array if
 *                          two columns, otherwise a multi-level array.
 * @method  fetchRow    Runs a query and returns the first row.
 * @method  fetchCol    Runs a query and returns the designated column.
 * @method  fetchOne    Runs a query and returns the first column of the first row.
 * @method  query       Executes a query and returns the last row inserted.
 */
class QueryWrapper
{
	# simplified fetchmodes
	const FETCH_ASSOC = PDO::FETCH_ASSOC;
	const FETCH_NUM   = PDO::FETCH_NUM;

	# simplified parameter types
	const PARAM_INT = PDO::PARAM_INT;
	const PARAM_STR = PDO::PARAM_STR;

	
	# static singleton
	private function __construct() { return void; }
	private function __clone() { return void; }
	private function __wakeup() { return void; }


	/**
	 * Runs a query and returns the data as a multi-level array.
	 *
	 * @param   string  $query
	 * @param   array   $placeholders (optional)
	 * @param   int     $fetchmode (optional. Defaults to QueryWrapper::ASSOC constant.)
	 * @return  array
	 */
	public static function fetchAll($query, $placeholders = array(), $fetchmode = self::FETCH_ASSOC) {
		if ($fetchmode !== self::FETCH_ASSOC && $fetchmode !== self::FETCH_NUM) {
			exit("$fetchmode is not a valid fetchmode.");
		}

		$result = self::getRawResults($query, $placeholders);

		return $result->fetchAll($fetchmode);
	}

	/**
	 * Runs a query and returns the data as an associative array if there 
	 *  are two columns, otherwise as a multi-level array.
	 *
	 * @param   string  $query
	 * @param   array   $placeholders (optional)
	 * @param   int     $fetchmode (optional. Defaults to QueryWrapper::ASSOC constant)
	 * @return  array (enumerated or associative)
	 */
	public static function fetchAssoc($query, $placeholders = array(), $fetchmode = self::FETCH_ASSOC) {
		if ($fetchmode !== self::FETCH_ASSOC && $fetchmode !== self::FETCH_NUM) {
			exit("$fetchmode is not a valid fetchmode.");
		}
	
		$result = self::getRawResults($query, $placeholders);

		if ($result->columnCount() === 2) {
			$answer = array();
			
			while ($row = $result->fetch(self::NUM)) {
				$answer[$row[0]] = $row[1];
			}
		} else {
			$answer = $result->fetchAll($fetchmode);
		}
		
		return $answer;
	}

	/**
	 * Runs a query and returns the first column.
	 *
	 * @param   string  $query
	 * @param   array   $placeholders (optional)
	 * @param   int     $column (optional. Default is 0-indexed)
	 * @return  array[int|mixed]
	 */
	public static function fetchCol($query, $placeholders = array(), $column = 0) {
		$result = self::getRawResults($query, $placeholders);

		$answer = array();

		while ($row = $result->fetchColumn(intval($column))) {
			$answer[] = $row;
		}
		
		return $answer;
	}

	/**
	 * Runs a query and returns the first column of the first row.
	 *
	 * @param   string  $query
	 * @param   array   $placeholders  (optional)
	 * @return  mixed (empty string on failure)
	 */
	public static function fetchOne($query, $placeholders = array()) {
		if (strpos(strtolower($query), ' limit') < 1) {
			$query .= ' LIMIT 1'; # adding LIMIT 1 speeds up the query
		}

		$result = self::getRawResults($query, $placeholders);
		$answer = $result->fetch();
		
		return (empty($answer)) ? '' : array_shift($answer);
	}

	/**
	 * Runs a query and returns the first row of the result.
	 *
	 * @param   string  $query
	 * @param   array   $placeholders  (optional)
	 * @param   int     $fetchmode     (optional. QueryWrapper constant. Defaults to ASSOC)
	 * @return  array
	 */
	public static function fetchRow($query, $placeholders = array(), $fetchmode = self::FETCH_ASSOC) {
		if (!is_int($fetchmode)) {
			exit("$fetchmode is not a valid fetchmode.");
		}

		$result = self::getRawResults($query, $placeholders);
		$answer = $result->fetch($fetchmode);

		return (empty($answer)) ? array() : $answer;
	}

	/**
	 * @param   string  $query       
	 * @param   array   $placeholders (optional)
	 * @return  int (number of the last inserted row)
	 */
	public static function query($query, $placeholders = array()) {
		self::getRawResults($query, $placeholders);

		return self::getConnection()->lastInsertId();
	}



	//------------------------------ HELPERS ------------------------------//
	/**
	 * @return  DBConnect
	 */
	private static function getConnection() {
		return DBConnect::connect('READ_ONLY');
	}

	/**
	 * @param   string  $query
	 * @param   array   $placeholders
	 * @return  PDO
	 */
	private static function getRawResults($query, array $placeholders) {
		$connection = self::getConnection();
		$result = $connection->prepare($query);

		foreach ($placeholders as $num=>$set) {
			if (is_array($set)) {
				self::bindPlaceholder($result, $set);
			} else {
				self::bindPlaceholder($result, $placeholders);
			}
		}

		try {
			$result->execute();
		}
		catch (PDOException $e) {
			exit($e->getMessage());
		}
		
		return $result;
	}

	/**
	 * @param   PDO     $query  
	 * @param   array   $placeholders
	 * @return  void
	 */
	private static function bindPlaceholder($query, array $placeholders) {
		if (isset($placeholders[2])) {
			if ($placeholders[2] !== self::PARAM_STR && $placeholders[2] !== self::PARAM_INT) {
				exit("{$placeholders[2]} is not an accepted data type.");
			} else {
				$data_type = $placeholders[2];
			}
		} else {
			$data_type = self::PARAM_STR;
		}

		$query->bindParam(":$placeholders[0]", $placeholders[1], $data_type);
	}
}