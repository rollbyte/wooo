<?php
namespace wooo\core;

final class Scope {
	/**
	 * @var Config
	 */
	private $config;
	
	private $di;
	
	private $registry = [];
	
	public function __construct(Config $config, array $di) {
		$this->config = $config;
		$this->di = $di;
	}
	
	private function parseValue($v) {
		if (is_string($v)) {
			if (preg_match('/^\\$\\{([a-z0-9_]+)\\}$/i', $v, $m)) {
				return $this->config->get($m[1]);
			}
		}
		return $v;
	}
	
	private function isOrdinal($arr) {
		return key($arr) === 0;
	}
	
	private function parseArgs(\ReflectionClass $c, $args) {
		$result = [];
		if (is_array($args)) {
			$params = $c->getConstructor()->getParameters();
			$n1 = count($args);
			$n2 = count($params);
			if ($n2 < $n1) $n1 = $n2;
			for ($i = 0; $i < $n1; $i++) {
				$param = $params[$i];
				$type = $param->getType();
				if ($type && !$type->isBuiltin()) {
					$tmp = $this->get($args[$i]);
					if ($tmp === "loading") {
						throw new \Exception("Circular dependency injection in constructor!");
					}
					$result[$i] = $tmp;
				} else {
					$result[$i] = $this->parseValue($args[$i]);
				}
			}
		}
		return $result;
	}
	
	private function parseOptions(\ReflectionClass $c, $options) {
		$result = [];
		if (is_array($options)) {
			foreach ($options as $nm => $v) {
				$m = "set".ucwords($nm);
				if ($c->hasMethod($m)) {
					$m1 = $c->getMethod($m);
					if ($m1->getNumberOfParameters()) {
						$param = $m1->getParameters()[0];
						$type = $param->getType();
						$tmp = (is_array($v) && $this->isOrdinal($v)) ? $v : [$v];
						$tmp2 = [];
						$n = count($tmp);
						for ($i = 0; $i < $n; $i++) {
							if (!isset($tmp[$i])) {
								throw new \Exception("Failed to read DI configuration for $nm option.");
							}
							$tmp2[] = ($type && !$type->isBuiltin()) ? $this->get($tmp[$i]) : $this->parseValue($tmp[$i]);
						}
						$result[$m] = $tmp2;
					}
					
				}
			}
		}
		return $result;
	}
	
	public function get($name) {
		if (isset($this->registry[$name])) {
			return $this->registry[$name];
		}
		if ($this->di[$name]) {
			$cn = $this->di[$name]["module"];
			$this->registry[$name] = "loading";
			$c = new \ReflectionClass($cn);
			if (isset($this->di[$name]["args"])) {
				$this->registry[$name] = $c->newInstanceArgs($this->parseArgs($c, $this->di[$name]["args"]));
			} else {
				$this->registry[$name] = $c->newInstance();
			}
			if (isset($this->di[$name]["options"])) {
				try {
					$opts = $this->parseOptions($c, $this->di[$name]["options"]);
				} catch (\Exception $e) {
					throw new \Exception("Failed to read DI configuration for component $name.", null, $e);
				}
				foreach ($opts as $m => $v) {
					if (!is_array($v)) {
						$v = [$v];
					}
					foreach ($v as $v1) {
						$c->getMethod($m)->invoke($this->registry[$name], $v1);
					}
				}
			}
			return $this->registry[$name];
		}
		return null;
	}
	
	public function __get($nm) {
		return $this->get($nm);
	}
}