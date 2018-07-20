<?php
namespace wooo\stdlib\dbal;
use wooo\stdlib\dbal\interfaces as wooo;
use wooo\stdlib\dbal\interfaces\DbDriver;
use wooo\stdlib\dbal\interfaces\DbCursor;

class Cursor implements wooo\DbCursor {

	private $statement;

	private $current;

	private $counter = 0;

	public function __construct(\PDOStatement $stmt) {
		$this->statement = $stmt;
	}

	public function current () {
		return $this->current;
	}

	public function key () {
		return $counter;
	}

	public function next () {
		$this->current = $this->statement->fetchObject();
		if (!$this->current) {
			$this->statement->closeCursor();
		} else {
			$this->counter++;
		}
	}

	public function rewind () {
		return;
	}

	public function valid () {
		$result = $this->current != false;
		if (!$result) {
			$this->statement->closeCursor();
		}
		return $result;
	}
	
	public function close(): void {
		$this->statement->closeCursor();
	}
}


class PDODriver implements wooo\DbDriver {
	/**
	 * @var \PDO
	 */
	private $connection;
	
	/**
	 * @var string
	 */
	private $uri;
	
	/**
	 * @var string
	 */
	private $user;
	
	/**
	 * @var string
	 */
	private $pwd;
	
	private $prepared = [];
	
	public function __construct(string $uri, string $user, string $pwd) {
		$this->uri = $uri;
		$this->user = $user;
		$this->pwd = $pwd;
	}
	
	private function connect() {
		if (!$this->connection) {
			$this->connection = new \PDO($this->uri, $this->user, $this->pwd, [
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
					\PDO::MYSQL_ATTR_FOUND_ROWS => true
			]);
		}
	}
	
	private function valType($v) {
		if (is_int($v)) {
			return \PDO::PARAM_INT;
		}
		if (is_bool($v)) {
			return \PDO::PARAM_BOOL;
		}
		return \PDO::PARAM_STR;
	}
	
	/**
	 * @param string $q
	 * @param array $params
	 * @param array $output
	 * @return \PDOStatement
	 */
	private function statement($q, $params, &$output = null) {
		$this->connect();
		if (!isset($this->prepared[$q])) {
			$this->prepared[$q] = $this->connection->prepare($q);
		}
		if (is_array($params)) {
			foreach ($params as $nm => $value) {
				if ($value instanceof \DateTime) {
					$value = $value->format('Y-m-d H:i:s');
				}
				$this->prepared[$q]->bindValue($nm, $value, $this->valType($value));
			}
		}
		if (is_array($output)) {
			foreach ($output as $nm -> $value) {
				$this->prepared[$q]->bindParam($nm, $value, \PDO::PARAM_INPUT_OUTPUT);
			}
		}
		return $this->prepared[$q];
	}
	
	public function execute(string $q, array $params, ?array &$output = null): DbDriver {
		$out = $output ?? [];
		$stmt = $this->statement($q, $params, $out);
		$stmt->execute();
		if (is_array($output)) {
			$output["affected"] = $stmt->rowCount();
			if (preg_match('/^\\s*insert\\s.*$/m', $q)) {
				$output["rowid"] = $this->connection->lastInsertId();
			}
		}
		return $this;
	}
	
	public function query(string $q, array $params): array {
		$stmt = $this->statement($q, $params);
		$stmt->execute();
		$result = $stmt->fetchAll(\PDO::FETCH_OBJ);
		$stmt->closeCursor();
		return $result;
	}
	
	public function get(string $q, array $params): ?object {
		$stmt = $this->statement($q, $params);
		$stmt->execute();
		$result = $stmt->fetchObject();
		$stmt->closeCursor();
		return $result ? $result : null;
	}
	
	public function iterate(string $q, array $params): DbCursor {
		$stmt = $this->statement($q, $params);
		$stmt->execute();
		return new Cursor($stmt);
	}
	
	public function begin(): void {
		$this->connect();
		if ($this->connection && !$this->connection->inTransaction()) {
			$this->connection->beginTransaction();
		}
	}
	
	public function commit(): void {
		if ($this->connection && $this->connection->inTransaction()) {
			$this->connection->commit();
		}
	}
	
	public function rollback(): void {
		if ($this->connection && $this->connection->inTransaction()) {
			$this->connection->rollback();
		}
	}
	public function scalar(string $q, array $params) {
		$stmt = $this->statement($q, $params);
		$stmt->execute();
		$result = $stmt->fetchColumn();
		$stmt->closeCursor();
		return $result;
	}

}