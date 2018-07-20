<?php
namespace wooo\stdlib\session;
use wooo\core\ISessionHandler;
use wooo\stdlib\dbal\interfaces\DbDriver;

class DbSession implements ISessionHandler {
	
	/**
	 * @var DbDriver
	 */
	private $db;
	
	/**
	 * @var string
	 */
	private $tableName = 'sessions';
	
	public function __construct(DbDriver $db) {
		$this->db = $db;
	}
	
	public function setTableName($name) {
		$this->tableName = $name;
	}
	
	/**
	 * @param string $sess_path
	 * @param string $sess_name
	 */
	function open($sess_path, $sess_name) {
		return true;
	}
	
	function close() {
		return true;
	}
	
	/**
	 * @param string $id
	 * @return string
	 */
	function read($id) {
		$s = $this->db->get("select * from $this->tableName where id=:id", ["id"=>$id]);
		return $s ? $s->data : '';
	}
	
	/**
	 * @param string $id
	 * @param string $sess_data
	 * @return boolean
	 */
	function write($id, $sess_data) {
		$out = [];
		$this->db->execute("update $this->tableName set data = :data where id = :id", ["data"=>$sess_data, "id"=>$id], $out);
		if (!isset($out["affected"]) || !$out["affected"]) {
			$this->db->execute("insert $this->tableName values (:id, now(), :data)", ["id"=>$id, "data"=>$sess_data]);
		}
		return true;
	}
	
	/**
	 * @param string $id
	 */
	function destroy($id) {
		$this->db->execute("delete from $this->tableName where id=?", [1=>$id]);
		return true;
	}
	
	/**
	 * @param int $maxlifetime
	 */
	function gc($maxlifetime) {
		$this->db->execute("delete from $this->tableName where created < ?", [1=>$maxlifetime]);
		return true;
	}	
}