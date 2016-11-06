<?php
namespace Pragma\ORM;

use Pragma\DB\DB;
use \PDO;

class Model extends QueryBuilder implements SerializableInterface{
	static protected $table_desc = array();

	protected $fields = array();
	protected $new = true;
	protected $desc = array();

	public function __construct($tb_name){
		parent::__construct($tb_name);

		$this->fields = $this->describe();
	}

	public function __get($attr){
		if(array_key_exists($attr, $this->describe())){
			return $this->fields[$attr];
		}
		return null;
	}

	public function __set($attr, $value){
		if(array_key_exists($attr, $this->describe())){
			$this->fields[$attr] = $value;
		}
	}

	public function __isset($attr) {
		if(array_key_exists($attr, $this->describe())){
			return (false === empty($this->fields[$attr]));
		}
		return null;
	}

	public function is_new(){
		return $this->new;
	}

	public function get_table(){
		return $this->table;
	}

	public function open($id){
		$db = DB::getDB();
		$res = $db->query("SELECT * FROM ".$this->table." WHERE id = :id", array(
							':id' => array($id, PDO::PARAM_INT)
							));
		if($db->numrows($res)){
			//it must return only one row
			$data = $db->fetchrow($res);
			$this->fields = $data;
			$this->new = false;
			return $this;
		}
		else return null;
	}

	public static function find($id){
		return self::forge()->where('id', '=', $id)->first();
	}

	public function openWithFields($data, $whitelist = null){
		if( ! empty($data) && isset($data['id']) ){

			//whitelist allows to get the description on an object and check if data is correct
			//the idea is to optimize by doing the describe only once
			if( ! is_null($whitelist) ){
				foreach($data as $f => $val){
					if( ! array_key_exists($f, $whitelist) ){
						unset($data[$f]);
					}
				}
			}

			$this->fields = $data;
			$this->new = false;

			return $this;
		}

		return null;
	}

	public function delete(){
		if( ! $this->new && ! is_null($this->id) && $this->id > 0){
			$db = DB::getDB();
			$db->query('DELETE FROM '.$this->table.' WHERE id = :id',
				array(':id' => array($this->id, PDO::PARAM_INT)));
		}
	}

	public static function all($idkey = true){
		return self::forge()->get_objects($idkey);
	}

	public static function build($data = array()){
		$classname = get_called_class();//get the name of the called class even in an extent context
		$obj = new $classname();
		$obj->fields = $obj->describe();

		$obj->fields = array_merge($obj->fields, $data);

		return $obj;
	}

	public function merge($data){
		$this->fields = array_merge($this->fields, $data);
	}


	public function save(){
		$db = DB::getDB();

		if($this->new){//INSERT
			$sql = 'INSERT INTO `'.$this->table.'` (';
			$first = true;
			foreach($this->describe() as $col => $default){
				if(!$first) $sql .= ', ';
				else $first = false;
				$sql .= '`'.$col.'`';
			}
			$sql .= ') VALUES (';

			$values = array();
			$first = true;
			foreach($this->describe() as $col => $default){
				if(!$first) $sql .= ', ';
				else $first = false;
				$sql .= ':'.$col;
				$values[':'.$col] = array_key_exists($col, $this->fields) ? $this->$col : '';
			}

			$sql .= ")";

			$res = $db->query($sql, $values);
			$this->id = $db->getLastId();
			$this->new = false;
		}
		else{//UPDATE
			$sql = 'UPDATE `'.$this->table.'` SET ';
			$first = true;
			$values = array();
			foreach($this->describe() as $col => $default){
				if($col != 'id'){//the id is not updatable
					if(!$first) $sql .= ', ';
					else $first = false;
					$sql .= '`'.$col.'` = :'.$col;
					$values[':'.$col] = array_key_exists($col, $this->fields) ? $this->$col : '';
				}
			}

			$sql .= ' WHERE id = :id';
			$values[':id'] = $this->id;

			$db->query($sql, $values);
		}
	}

	public function toJSON(){
		return json_encode($this->fields);
	}

	public function as_array(){
		return $this->fields;
	}


	protected function describe() {
		$db = DB::getDB();

		if (empty(self::$table_desc[$this->table])) {
			$res = $db->query('DESC '.$this->table);

			if ($db->numrows($res) > 0) {
				while ($data = $db->fetchrow($res)) {
					if (empty($data['Default']) && $data['Null'] == 'NO') {
						self::$table_desc[$this->table][$data['Field']] = '';
					} else {
						self::$table_desc[$this->table][$data['Field']] = $data['Default'];
					}
				}
			}
		}

		return self::$table_desc[$this->table];
	}
}