<?php
namespace wooo\stdlib\dbal;

use wooo\stdlib\dbal\interfaces\SequenceProvider;

class MySQLSequenceProvider implements SequenceProvider {
	/**
	 * @var PDODriver
	 */
	private $driver;
	
	public function __construct(PDODriver $driver) {
		$this->driver = $driver;
	}
	
	public function next(string $name) {
		$this->driver->begin();
		try {
			$cur = $this->driver->query('select value from sequences where name = :nm for update', ['nm' => $name]);
			$result = null;
			if (empty($cur)) {
				$result = 1;
				$this->driver->execute('insert sequences (name, value) values (:nm, 1)', ['nm' => $name]);
			} else {
				$result = $cur[0]->value + 1;
				$this->driver->execute('update sequences set value = :v where name = :nm', ['v' => $result, 'nm' => $name]);
			}
			$this->driver->commit();
			return $result;
		} catch (\Exception $e) {
			$this->driver->rollback();
			throw $e;
		}
	}

	public function create(string $name) {
		$this->driver->execute('insert sequences (name, value) values (:nm, 0)', ['nm' => $name]);
	}
}
