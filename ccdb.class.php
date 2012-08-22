<?php
class ccdb {
	public $error;
	
	/*
	 * new_table(string $name, array $args)
	 * Creates a new table. Returns false on error.
	 * $args will contain the structure. Build like so: array('COLUMN NAME', 'COLUMN NAME', etc etc)
	 * Column names cannot be the same.
	 * Characters not A-Z, a-z, or 0-9 will be removed, including those in $args and those in $name
	*/
	public function new_table($name, $args){
		$name = $this->clean_string($name);
		
		if(!$this->table_exists($name)){
			$tableStruct = '+ ';
			foreach($args as $key => $col){
				$col = $this->clean_string($col);
				
				$keys = array_keys($args, $col, true);
				foreach($keys as $fkey){
					if($fkey != $key){
						$this->error = "new_table: Duplicated column names detected for '" . $col . "'";
						return false;
					}
				}
				
				$colCharCount = strlen($col);
				$charNeeded = 13 - $colCharCount;
				$whitespace = 0;
				while($charNeeded > 0){
					$whitespace++;
					$charNeeded--;
				}
				
				$tableStruct .= $col;
				$tableStruct .= str_repeat(' ', $whitespace);
				
				if($col == end($args)){
					$tableStruct .= ' +';
				} else {
					$tableStruct .= ' - ';
				}
			}
			
			$structLen = strlen($tableStruct);
			$newline = str_repeat('-', $structLen-2);
			$newline .= '|';
			$newline = '|' . $newline;
			$result = $tableStruct . "\r\n" . $newline . "\r\n";
			
			if(!mkdir('data/db/data/' . $name . '/')){
				$this->error = "new_table: Error making data directory, your table was not created";
				return false;
			}
			
			if(file_put_contents('data/db/' . $name . '.txt', $result)){
				return true;
			} else {
				$this->error = "new_table: Error creating table";
				return false;
			}
		} else {
			$this->error = "new_table: Table '" . $name . "' already exists";
			return false;
		}
	}
	
	/*
	 * delete_data(string $table, array $rows)
	 * Deletes the rows that have columns matching the values inside of $rows
	 * $rows example: array("username" => "phil")
	 * The row containing phil as the username will be deleted
	 * returns true on success, false on failure
	*/
	public function delete_data($table, $rows){
		if($this->table_exists($table)){
			$delrows = array();
			$data = $this->get_data($table);
			foreach($rows as $del_key => $del_val){
				foreach($data as $row_num => $row){
					foreach($row as $row_key => $row_val){
						if($row_key == $del_key && $row_val == $del_val){
							$delrows[] = $row_num;
						}
					}
				}
			}
			$newdata = file_get_contents('data/db/' . $table . '.txt');
			$newdata = explode("\r\n", $newdata);
			foreach($delrows as $delrow){
				unset($newdata[$delrow+2]);
			}
			$newarr = array();
			foreach($newdata as $line){
				if(!empty($line)){
					$newarr[] = $line;
				}
			}
			$newarr[] = '';
			$newdata = implode("\r\n", $newarr);
			if(file_put_contents('data/db/' . $table . '.txt', $newdata)){
				return true;
			}
		} else {
			$this->error = "put_data: Table ('" . $table . "') does not exist";
		}
		return false;
	}
	
	/*
	 * put_data(string $table, array $data)
	 * Put's a new row (or multiple rows) into the table ($table)
	 * $data should either contain a set of strings or a set of arrays (containing sets of strings)
	 * Assign the column as the key, and the value in the value. Ex: array("Col1" => "Data to be in Col1", "username" => "user's username");
	 * Multiple columns can be stored using multiple arrays inside of $data - those arrays can be used like a single column insert.
	 * If a column does not exist, it's value will be ignored.
	 * Returns true on success, false on failure
	*/
	public function put_data($table, $data){
		if($this->table_exists($table)){
			$fp = fopen('data/db/' . $table . '.txt', 'r');
			$struct = stream_get_line($fp, 1024, "\r\n");
			fclose($fp);
			
			$struct = explode(' - ', $struct);
			$cols = array();
			foreach($struct as $key => $col){
				$cols[] = $this->clean_string($col);
			}
			
			$newline = '| ';
			foreach($cols as $pos => $col){
				$uniq = uniqid();
				if(file_put_contents('data/db/data/' . $table . '/' . $uniq . '.txt', $data[$col]) === false){
					$this->error = "put_data: Error storing content";
					return false;
				} else {
					if($pos == count($cols)-1){
						$newline .= $uniq . " |\r\n";
					} else {
						$newline .= $uniq . ' | ';
					}
				}
			}
			
			if(file_put_contents('data/db/' . $table . '.txt', $newline, FILE_APPEND) === false){
				$this->error = "put_data: Error storing content IDs";
				return false;
			}
		} else {
			$this->error = "put_data: Table ('" . $table . "') does not exist";
			return false;
		}
		return true;
	}
	
	/*
	 * get_data(string $table, array $cols=array(), array $where=array())
	 * Returns data from the table $table.
	 * $cols can contain the names of columns you want to specificly get.
	*/
	public function get_data($table, $cols=array(), $where=array()){
		if($this->table_exists($table)){
			$result = array();
			$rows = 0;
			$data = file_get_contents('data/db/' . $table . '.txt');
			$lines = explode("\r\n", $data);
			$columns = explode(' - ', $lines[0]);
			foreach($columns as $key => $col){
				$columns[$key] = $this->clean_string($col);
			}
			foreach($lines as $key => $line){
				if($key > 1 && !empty($line)){ // Only go through the actual data
					if(empty($where)){
						$thisrow = true;
					} else {
						$thisrow = false;
					}
					$data = explode(" | ", $line, count($columns));
					foreach($data as $key => $dat){
						$dat = $this->clean_string($dat);
						$data[$key] = $dat;
						if(isset($where[$columns[$key]])){
							if($where[$columns[$key]] == file_get_contents('data/db/data/' . $table . '/' . $dat . '.txt')){
								$thisrow = true;
							}
						}
					}
					if($thisrow){
						foreach($data as $key => $dat){
							if(empty($cols)){
								$result[$rows][$columns[$key]] = file_get_contents('data/db/data/' . $table . '/' . $dat . '.txt');
							} else {
								if(in_array($columns[$key], $cols)){
									$result[$rows][$columns[$key]] = file_get_contents('data/db/data/' . $table . '/' . $dat . '.txt');
								}
							}
						}
						$rows++;
					}
				}
			}
			return $result;
		} else {
			$this->error = 'get_data: Table does not exist';
			return false;
		}
	}
	
	/*
	 * clean_string(string $string)
	 * Clean's the $string so it may be used in columns and such
	*/
	public function clean_string($string){
		return preg_replace("[^A-Za-z0-9]", "", str_replace('|', '', str_replace(" ", '', str_replace('+', '', $string))));
	}
	
	/*
	 * table_exists(string $table)
	 * Returns true if the table exists, false if it doesn't
	*/
	public function table_exists($table){
		if(file_exists('data/db/' . $table . '.txt') && is_dir('data/db/data/' . $table . '/')){
			return true;
		} else {
			return false;
		}
	}
}
?>