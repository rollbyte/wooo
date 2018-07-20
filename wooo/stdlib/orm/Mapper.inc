<?php
namespace wooo\stdlib\orm;
use wooo\stdlib\dbal\interfaces\DbDriver;
use wooo\stdlib\dbal\interfaces\DbCursor;
use wooo\core\DateTime;

class SortOrder {
	const ASC = 0;
	const DESC = 1;
}

class AGGREG {
	const SUM = 'sum';
	const AVG = 'avg';
	const COUNT = 'count';
	const MIN = 'min';
	const MAX = 'max';
}

class FO {
	const EQ = 'eq'; 
	const NE = 'ne'; 
	const LT = 'lt'; 
	const GT = 'gt'; 
	const LE = 'le'; 
	const GE = 'ge'; 
	const BETWEEN = 'bw';
	const IN = 'in';
	const AND = 'and'; 
	const OR = 'or'; 
	const NOT = 'not'; 
	const ISNULL = 'isnull';
	
	const ADD = 'add';
	const SUB = 'sub';
	const MUL = 'mul';
	const DIV = 'div';
	const MOD = 'mod';
	const BW_AND = 'band';
	const BW_OR = 'bor';
	const BW_XOR = 'bxor';
	
	const NVL = 'nvl';
	const LIKE = 'like';
	
	public static function l() {
		return [
				self::EQ, 
				self::NE, 
				self::LT, 
				self::GT, 
				self::LE, 
				self::GE, 
				self::BETWEEN,
				self::IN,
				self::AND, 
				self::OR, 
				self::NOT, 
				self::ISNULL,
				self::ADD,
				self::SUB,
				self::MUL,
				self::DIV,
				self::MOD,
				self::BW_AND,
				self::BW_OR,
				self::BW_XOR,
				self::NVL,
				self::LIKE
		];
	}
}

class CursorWrapper implements \Iterator {
	/**
	 * @var DbCursor $cursor
	 */
	private $cursor;

	public function __construct(DbCursor $cursor, callable $wrapper) {
		$this->wrapper = $wrapper;
		$this->cursor = $cursor;
	}

	public function current () {
		$result = $wrapper($this->cursor->current());
	}

	public function key () {
		return $this->cursor->key();
	}

	public function next () {
		return $this->cursor->next();
	}

	public function rewind () {
		return;
	}

	public function valid () {
		return $this->cursor->valid();
	}
}

class Mapper {
	
	private $namespace = '';
	
	/**
	 * @var DbDriver $ds
	 */
	private $ds;
	
	/**
	 * @var array $ormConfig
	 */
	private $ormConfig = [];
	
	private $ormCounter = 0;
	
	/**
	 * @var string $multiFieldIdSeparator
	 */
	private $multiFieldIdSeparator = '|';
	
	private static $operTypes = [
			FO::EQ => "binar",
			FO::NE => "binar",
			FO::LT => "binar",
			FO::GT => "binar",
			FO::LE => "binar",
			FO::GE => "binar",
			FO::BETWEEN => "custom",
			FO::IN => "custom",
			FO::AND => "multi",
			FO::OR => "multi",
			FO::NOT => "unar",
			FO::ISNULL => "postpred",
			FO::ADD => "binar",
			FO::SUB => "binar",
			FO::MUL => "binar",
			FO::DIV => "binar",
			FO::MOD => "binar",
			FO::BW_AND => "binar",
			FO::BW_OR => "binar",
			FO::BW_XOR => "binar",
			FO::NVL => "custom",
			FO::LIKE => "like"
	];
	
	private static $opers = [
			FO::EQ => "=",
			FO::NE => "<>",
			FO::LT => "<",
			FO::GT => ">",
			FO::LE => "<=",
			FO::GE => ">=",
			FO::BETWEEN => "between",
			FO::IN => "in",
			FO::AND => "and",
			FO::OR => "or",
			FO::NOT => "not",
			FO::ISNULL => "is null",
			FO::ADD => "+",
			FO::SUB => "-",
			FO::MUL => "*",
			FO::DIV => "/",
			FO::MOD => "%",
			FO::BW_AND => "&",
			FO::BW_OR => "|",
			FO::BW_XOR => "^",
			FO::NVL => "NVL",
			FO::LIKE => "like"
	];
	
	public function __construct(DbDriver $ds, $ns = '', $mfis = null) {
		$this->ds = $ds;
		$this->namespace = $ns;
		if ($this->namespace) {
			if ($this->namespace[0] != '\\') {
				$this->namespace = '\\'.$this->namespace;
			}
			if ($this->namespace[strlen($this->namespace) - 1] != '\\') {
				$this->namespace = $this->namespace.'\\';
			}
		}
		if ($mfis) {
			$this->multiFieldIdSeparator = $mfis;
		}
	}
	
	public function multiFieldIdSeparator(): string {
		return $this->multiFieldIdSeparator;
	}
	
	public function begin() {
		$this->ds->begin();
	}
	
	public function commit() {
		$this->ds->commit();
	}
	
	public function rollback() {
		$this->ds->rollback();
	}
	
	/**
	 * @param string $cn
	 * @param array $descendants
	 */
	private function getOrmParams($cn, $descendants = []) {
		if (strpos($cn, '\\') === false) {
			$cn = $this->namespace.$cn;
		}
		if (!isset($this->ormConfig[$cn])) {
			$this->ormConfig[$cn] = [];
			$this->ormCounter++;
			$this->ormConfig[$cn]['cn'] = $cn;
			$this->ormConfig[$cn]['index'] = $this->ormCounter;
			$this->ormConfig[$cn]['descendants'] = [];
			$this->ormConfig[$cn]['discriminator'] = false;
			$rc = new \ReflectionClass($cn);
			if ($rc->getParentClass()) {
				$this->ormConfig[$cn]['parent'] = $this->getOrmParams($rc->getParentClass()->getName());
				$this->ormConfig[$cn]['key'] = $this->ormConfig[$cn]['parent']['key'];
				$this->ormConfig[$cn]['discriminator'] = $this->ormConfig[$cn]['parent']['discriminator'];
			}
			$this->ormConfig[$cn]['tn'] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $rc->getShortName()));
			$doc = $rc->getDocComment();
			if ($doc) {
				$lineend = '.*$/m';
				preg_match_all('/@orm.table\s+([_\w]+)'.$lineend, $doc, $matches);
				$n = count($matches[1]);
				if ($n > 0) {
					$this->ormConfig[$cn]['tn'] = $matches[1][0];
				}
				
				preg_match_all('/@orm.discriminator\s+([_\w]+)'.$lineend, $doc, $matches);
				$n = count($matches[1]);
				if ($n > 0) {
					$this->ormConfig[$cn]['discriminator'] = $matches[1][0];
				}
				
				preg_match_all('/@orm.key\s+([_\w]+)'.$lineend, $doc, $matches);
				if (!empty($matches[1])){
					$this->ormConfig[$cn]['key'] = $matches[1];
				}
			}
			
			$props = $rc->getProperties(\ReflectionProperty::IS_PUBLIC & ~\ReflectionProperty::IS_STATIC);
			
			if (!isset($this->ormConfig[$cn]['key'])) {
				if (count($props) > 0) {
					$this->ormConfig[$cn]['key'] = [$props[0]->getName()];
				} else {
					throw new \Exception("Class $cn has no key!!!");
				}
			}
			
			$this->ormConfig[$cn]['map'] = [];
			$ind = 1;
			foreach ($props as $p) {
				if ($p->getDeclaringClass()->getName() == $rc->getName() ||
						in_array($p->getName(), $this->ormConfig[$cn]['key'])) {
					$this->ormConfig[$cn]['map'][$p->getName()] = [];
					$pdoc = $p->getDocComment();
					$datatype = 'string';
					$is_ref = false;
					$is_array = false;
					
					if (preg_match('/@var\s+([_\w\\\]+)(\[\])?/', $pdoc, $matches)) {
						$datatype = $matches[1];
						if (
								$datatype !== 'string' &&
								$datatype !== 'int' &&
								$datatype !== 'bool' &&
								$datatype !== 'float' &&
								$datatype !== '\DateTime'
						) {
							if (strpos($datatype, '\\') === false) {
								$datatype = $this->namespace.$datatype;
							}
							$is_ref = class_exists($datatype, true);
							if (count($matches) > 2 && $matches[2] == '[]') {
								$is_array = true;
							}
						}
					}
					$this->ormConfig[$cn]['map'][$p->getName()]['index'] = $ind;
					$this->ormConfig[$cn]['map'][$p->getName()]['fields'] =
						[strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $p->getName()))];
					
					$this->ormConfig[$cn]['map'][$p->getName()]['type'] = $datatype;
					$this->ormConfig[$cn]['map'][$p->getName()]['is_ref'] = $is_ref;
					$this->ormConfig[$cn]['map'][$p->getName()]['is_array'] = $is_array;
					$this->ormConfig[$cn]['map'][$p->getName()]['is_key'] = in_array($p->getName(), $this->ormConfig[$cn]['key']);
						
					if (preg_match_all('/@orm.field\s+(\w[_\w]*)\s*$/m', $pdoc, $matches)) {
						if (count($matches) > 1) { 
							$this->ormConfig[$cn]['map'][$p->getName()]['fields'] = $matches[1];
						}
					}
					
					if (preg_match_all('/@orm.backRef\s+(\w[_\w]*)\s*$/m', $pdoc, $matches)) {
						if (count($matches) > 1) {
							$this->ormConfig[$cn]['map'][$p->getName()]['backRef'] = $matches[1][0];
						}
					}
					
					$ind++;
				}		
			}
			
		}
		
		if (is_array($descendants)) {
			$d = [];
			foreach ($descendants as $desc) {
				$dcn = $desc;
				if (strpos($desc, '\\') === false) {
					$dcn = $this->namespace.$desc;
				}
				if (is_subclass_of($dcn, $cn)) {
					$d[] = $dcn;
				}
			}
			$this->ormConfig[$cn]['descendants'] = array_unique(array_merge($this->ormConfig[$cn]['descendants'], $d));
		}
		
		return $this->ormConfig[$cn];
	}
	
	private function dataToKey($obj, $cn) {
		$result = null;
		$orm = $this->getOrmParams($cn);
		if ($orm) {
			$result = [];
			foreach ($orm['key'] as $k) {
				$v = $obj->$k;
				if (is_object($v) && $orm['map'][$k]['is_ref']) {
					$kvs = $this->getObjectKey($v);
					foreach ($kvs as $kv) {
						$result[] = $kv;
					}
				} else {
					$result[] = $v;
				}
			}
		}
		return $result;
	}
	
	
	private function getObjectKey($obj) {
		return $this->dataToKey($obj, get_class($obj));
	}
	
	private function propOrm($orm, $prop) {
		if (isset($orm['map'][$prop])) {
			return $orm;
		}
		if (isset($orm['parent'])) {
			return $this->propOrm($orm['parent'], $prop);
		}
		throw new \Exception('Attribute '.$prop.' not found in class '.$orm['cn']);
	}
	
	private function createAlias($context, $borm, $ref_prop, $orm, $type, $jointype, &$aliases, &$joins, &$count, $eagerPrefix = null) {
		$prefix = $context;
		if ($ref_prop) {
			$prefix = $prefix.'_'.$ref_prop;
		}
		if (!isset($aliases[$prefix.'::'.$orm['tn']])) {
			$count++;
			$alias = 't'.$count;
			$aliases[$prefix.'::'.$orm['tn']] = ['alias'=>$alias, 'tn'=>$orm['tn'], 'cn' => $orm['cn'], 'type'=> $type, 'context'=>$prefix];
			$join = $jointype.' join '.$orm['tn'].' as '.$alias.' on ';
			$conds = [];
			if ($ref_prop) {
				if (isset($borm['map'][$ref_prop])) {
					$i = 0;
					foreach ($borm['map'][$ref_prop]['fields'] as $key) {
						$conds[] = '('.$alias.'.'.$orm['map'][$orm['key'][$i]]['fields'][0].' = '.$context.'.'.$key.')';
						$i++;
					}
				} else if (isset($orm['map'][$ref_prop])) {
					$i = 0;
					foreach ($orm['map'][$ref_prop]['fields'] as $key) {
						$conds[] = '('.$alias.'.'.$key.' = '.$context.'.'.$borm['map'][$borm['key'][$i]]['fields'][0].')';
						$i++;
					}
				} else throw new \Exception('Query syntax error!');
			} else {
				$i = 0;
				foreach ($borm['key'] as $key) {
					$conds[] = '('.$alias.'.'.$orm['map'][$orm['key'][$i]]['fields'][0].' = '.$context.'.'.$borm['map'][$key]['fields'][0].')';
					$i++;
				}
			}
			$joins[] = $join.implode(' and ', $conds);
		}
		if ($eagerPrefix) {
			$aliases[$prefix.'::'.$orm['tn']]['eagerPrefix'] = $eagerPrefix;
		}
		return $aliases[$prefix.'::'.$orm['tn']];
	}
	
	private function processAncestors($context, $orm, $type, $jointype, &$aliases, &$joins, &$count) {
		if (isset($orm['parent'])) {
			$porm = $orm['parent'];
			$this->createAlias($context, $orm, false, $porm, $type, $jointype, $aliases, $joins, $count);
			$this->processAncestors($context, $orm['parent'], $type, $jointype, $aliases, $joins, $count);
		}
	}
	
	private function processDescendants($context, $orm, &$aliases, &$joins, &$count, $eagerPref = null) {
		if (isset($orm['descendants']) && is_array($orm['descendants']) && !empty($orm['descendants'])) {
			foreach ($orm['descendants'] as $desc) {
				$dorm = $this->getOrmParams($desc);
				$this->createAlias($context, $orm, false, $dorm, 'desc', 'left', $aliases, $joins, $count, $eagerPref);
			} 
		}
	}	
		
	private function parseFilterAttr($context, $attr, $orm, &$aliases, &$joins, &$count, $jointype = null) {
		$a = $attr;
		$drill = '';
		if (($dotpos = strpos($attr, '.')) > 0) {
			$a = substr($attr, 0, $dotpos);
			$drill = substr($attr, $dotpos + 1);
		}
		$porm = $this->propOrm($orm, $a);
		$this->processAncestors($context, $porm, 'filter', $jointype ? $jointype : 'inner', $aliases, $joins, $counter);	
		$alias = $aliases[$context.'::'.$porm['tn']]['alias'];
		
		$prop = $porm['map'][$a];
		
		if ($drill) {
			if ($prop['is_ref']) {
				$rorm = $this->getOrmParams($prop['type']);
				$ra = $this->createAlias(
						$alias, $porm, $prop['is_array'] ? $prop['backRef'] : $a,
						$rorm, 'filter', 'left', $aliases, $joins, $count
				);
				return $this->parseFilterAttr($ra['context'], $drill, $rorm, $aliases, $joins, $count, 'left');
			} else {
				throw new \Exception('Query syntax error!');
			}
		}
		
		if (count($prop['fields']) > 1) {
			$result = [];
			foreach ($prop['fields'] as $fld) {
				$result[] = $alias.'.'.$fld;
			}
			return $result;
		}

		return $alias.'.'.$prop['fields'][0];
	}
	
	private function parseEagerAttr($prefix, $context, $attr, $orm, &$aliases, &$joins, $descendants, &$count) {
		$a = $attr;
		$drill = '';
		if (($dotpos = strpos($attr, '.')) > 0) {
			$a = substr($attr, 0, $dotpos);
			$drill = substr($attr, $dotpos + 1);
		}
		$porm = $this->propOrm($orm, $a);	
		$prop = $porm['map'][$a];
	
		if ($prop['is_ref']) {
			if (!$prop['is_array']) {
				$this->processAncestors($context, $porm, 'eager', 'left', $aliases, $joins, $counter);
				$alias = $aliases[$context.'::'.$porm['tn']]['alias'];
				$rorm = $this->getOrmParams($prop['type'], $descendants);
				$eagerPref = $prefix.'_'.$prop['index'];
				$ra = $this->createAlias($alias, $porm, $a, $rorm, 'eager', 'left', $aliases, $joins, $count, $eagerPref);
				$this->processDescendants($ra['alias'], $rorm, $aliases, $joins, $count, $eagerPref);
				if ($drill) {
					$this->parseEagerAttr($eagerPref, $ra['context'], $drill, $rorm, $aliases, $joins, $descendants, $count);
				}
			}
		} else {
				throw new \Exception('Query syntax error!');
		}
	}
	
	private function splitParam($param, $i) {
		if ($param && $param[0] == ':') {
			return $param.'_'.$i;
		}
		return $this->sqlValue($param);
	}
	
	private function sqlValue($v) {
		return ($v === null) ? 'null' : $v;
	}
	
	private function formUnarCondition($oper, $arg) {
		if (is_array($arg) && !empty($arg)) {
			if (count($arg) > 1) {
				$results = [];
				$i = 0;
				foreach ($arg as $a) {
					$results[] = '('.$oper.' '.$this->splitParam($a, $i).')';
					$i++;
				}
				return '('.implode(' and ', $results).')';
			} else {
				$arg = $arg[0];
			}
		}
		return '('.$oper.' '.$this->sqlValue($arg).')';
	}
	
	private function formPostPredCondition($oper, $arg) {
		if (is_array($arg) && !empty($arg)) {
			if (count($arg) > 1) {
				$results = [];
				$i = 0;
				foreach ($arg as $a) {
					$results[] = '('.$this->splitParam($a, $i).' '.$oper.')';
					$i++;
				}
				return '('.implode(' and ', $results).')';
			} else {
				$arg = $arg[0];
			}
		}
		return '('.$this->sqlValue($arg).' '.$oper.')';
	}	
	
	private function formBinarCondition($oper, $arg1, $arg2) {
		if (!is_array($arg1)) {
			$arg1 = [$arg1];
			
		}
		if (!is_array($arg2)) {
			$arg2 = [$arg2];
		}
		
		$result = [];
		$n = count($arg1);
		for ($i = 0; $i < $n; $i++) {
			if ($n == 1) {
				if ($arg1[$i] === null || $arg2[$i] === null) {
					$arg = ($arg1[$i] === null) ? $arg2[$i] : $arg1[$i];
					if ($oper === '=') {
						return $arg.' is null'; 
					}
				}
				
				return '('.$this->sqlValue($arg1[$i]).' '.$oper.' '.$this->sqlValue($arg2[$i]).')';
			}
			$result[] = '('.$this->splitParam($arg1[$i], $i).' '.$oper.' '.$this->splitParam($arg2[$i], $i).')';
		}
		return (count($result) == 1) ? $result[0] : '('.implode(' and ', $result).')';
	}
	
	private function formBetweenCondition($arg1, $arg2, $arg3) {
		if (!is_array($arg1)) {
			$arg1 = [$arg1];
				
		}
		if (!is_array($arg2)) {
			$arg1 = [$arg2];
		}
		if (!is_array($arg3)) {
			$arg3 = [$arg3];
		}		
		$result = [];
		$n = count($arg1);
		for ($i = 0; $i < count($arg1); $i++) {
			if ($n == 1) {
				return '('.$this->sqlValue($arg1[$i]).' between '.$this->sqlValue($arg2[$i]).' and '.$this->sqlValue($arg3[$i]).')';
			}
			$result[] = '('.$this->splitParam($arg1[$i], $i).' between '.$this->splitParam($arg2[$i], $i).' and '.$this->splitParam($arg3[$i], $i).')';
		}
		
		return (count($result) == 1) ? $result[0] : '('.implode(' and ', $result).')';
	}
	
	private function parseFilter($context, $orm, $filter, &$aliases, &$joins, &$count) {
		if (!$count) {
			$count = 1;
		}
		if (is_array($filter)) {
			if (array_values($filter) === $filter) {
				array_walk($filter, function (&$v) {
					if (is_string($v)) {
						$v = "'$v'";
					}
				});
				return $filter;
			}
			
			foreach ($filter as $oper => $args) {
				if (!is_array($args) || count($args) == 0) {
					throw new \Exception('Query syntax error!');
				}
				if (in_array($oper, FO::l())) {
					$type = self::$operTypes[$oper];
					switch ($type) {
						case 'unar': {
							return $this->formUnarCondition(self::$opers[$oper], $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count));
						}break;
						case 'binar': {
							$oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
							$oper2 = $this->parseFilter($context, $orm, $args[1], $aliases, $joins, $count);
							return $this->formBinarCondition(self::$opers[$oper], $oper1, $oper2);
						}break;
						case 'multi': {
							$opers = [];
							foreach ($args as $arg) {
								$opers[] = $this->parseFilter($context, $orm, $arg, $aliases, $joins, $count);
							}
							return '('.implode(' '.self::$opers[$oper].' ', $opers).')';
						}break;
						case 'postpred': {
							return $this->formPostPredCondition(self::$opers[$oper], $this->parseFilter($context, $orm, $arg, $aliases, $joins, $count));
						}break;
						default: {
							switch ($oper) {
								case FO::BETWEEN: {
									$oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
									$oper2 = $this->parseFilter($context, $orm, $args[1], $aliases, $joins, $count);
									$oper3 = $this->parseFilter($context, $orm, $args[2], $aliases, $joins, $count);
										
									return $this->formBetweenCondition($oper1, $oper2, $oper3);
								}break;
								case FO::IN: {
									$oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
									$arr = $args[1];
									array_walk($arr, function (&$v) {if (is_string($v)) {$v = "'".$v."'";}});
									return '('.$this->sqlValue($oper1).' in ('.implode(',', $arr).'))';
								}break;
								case FO::NVL: {
									$opers = [];
									foreach ($args as $arg) {
										$opers[] = $this->parseFilter($context, $orm, $arg, $aliases, $joins, $count);
									}
									return 'coalesce('.implode(', ', $opers).')';
								};
								case FO::LIKE: {
									$oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
									$oper2 = $this->parseFilter($context, $orm, $args[1], $aliases, $joins, $count);
									return '('.$this->sqlValue($oper1).' REGEXP '.$this->sqlValue($oper2).')';
								};
							}
						}break;
					}
				}
				throw new \Exception('Invalid operation!');
			}
		} else if ($filter && is_string($filter) && ($filter != '?') && ($filter[0] != ':')) {
			return $this->parseFilterAttr($context, $filter, $orm, $aliases, $joins, $count);
		} else if ($filter instanceof \DateTime) {
			return '\''.$filter->format(\DateTime::ISO8601).'\'';
		}
		return $filter;
	}
	
	private function parseEager($prefix, $context, $orm, $eager, &$aliases, &$joins, $descendants, &$count = 0) {
		if (!$count) {
			$count = 1;
		}		
		foreach ($eager as $attr) {
			$this->parseEagerAttr($prefix, $context, $attr, $orm, $aliases, $joins, $descendants, $count);
		}
	}
	
	private function ormFields($orm, $withKeys = false) {
		$result = [];
		foreach ($orm['map'] as $p) {
			if (!$p['is_array'] && (!$p['is_key'] || $withKeys)) {
				$result = array_merge($result, $p['fields']);
			}
		}
		return $result;
	}
	
	private function parseFetch($orm, $fetch) {
		// TODO Парсинг жаднозагружаемых атрибутов и атрибутов дочерних классов
		$result = [];
		foreach ($fetch as $alias => $fld) {
			if (is_string($fld)) {
				$result[] = 'main.'.$orm['map'][$fld]['fields'][0].' as '.$alias;
			} else if (is_array($fld)) {
				foreach ($fld as $oper => $val) {
					switch ($oper) {
						case AGGREG::SUM: $result[] = 'sum(main.'.$orm['map'][$val]['fields'][0].') as '.$alias;break;
						case AGGREG::AVG: $result[] = 'avg(main.'.$orm['map'][$val]['fields'][0].') as '.$alias;break;
						case AGGREG::COUNT: $result[] = 'count(main.'.$orm['map'][$val]['fields'][0].') as '.$alias;break;
						case AGGREG::MIN: $result[] = 'min(main.'.$orm['map'][$val]['fields'][0].') as '.$alias;break;
						case AGGREG::MAX: $result[] = 'max(main.'.$orm['map'][$val]['fields'][0].') as '.$alias;break;
					}
				}
			}
		}
		return join(', ',$result);
	}
	
	private function buildSelect($cn, $options = [], $quantaty = false, $conditions = false) {
		extract($options);
		
		$orm = $this->getOrmParams($cn, isset($descendants) ? $descendants : []);
		
		if ($quantaty) {
			$q = 'select count(*) as cnt';
		} else {
			if (isset($fetch)) {
				$q = 'select '.$this->parseFetch($orm, $fetch);
			} else {
				$q = 'select distinct main.*';
			}
		}
		$aliases = [];
		$joins = [];
		$where = '';
		$counter = 0;

		$aliases['main::'.$orm['tn']] = ['alias'=>'main', 'tn' => $orm['tn'], 'cn' => $orm['cn']];
		
		$this->processAncestors('main', $orm, 'base', 'inner', $aliases, $joins, $counter);

		if (isset($eager) && is_array($eager) && !empty($eager)) {
			$this->parseEager('','main', $orm, $eager, $aliases, $joins, isset($descendants) ? $descendants : [], $counter);
		}

		if (isset($filter) && is_array($filter) && !empty($filter)) {
			$where = $this->parseFilter('main', $orm, $filter, $aliases, $joins, $counter);
		}
		
		$sorts = [];
		if (isset($sort) && !$quantaty) {
			foreach ($sort as $s) {
				if (isset($s['expr'])) {
					$expr = $s['expr'];
					if (is_array($expr)) {
						$expr = $this->parseFilter('main', $orm, $expr, $aliases, $joins, $counter);
					} else {
						$expr = $this->parseFilterAttr('main', $expr, $orm, $aliases, $joins, $counter);
					}
					$direction = 'asc';
					if (isset($s['dir']) && $s['dir'] == SortOrder::DESC) {
						$direction = 'desc';
					}
					$sorts[] = $expr.' '.$direction;
				}
			}
		}

		$this->processDescendants('main', $orm, $aliases, $joins, $counter);
	
		if (!$quantaty) {
			foreach ($aliases as $tn => $alias) {
				if (isset($alias['type'])) {
					switch ($alias['type']) {
						case 'base': {
							$eorm = $this->getOrmParams($alias['cn']);
							$flds = $this->ormFields($eorm);
							foreach ($flds as $fld) {
								$q = $q.', '.$alias['alias'].'.'.$fld;
							}
						}break;
						case 'desc': {
							$eorm = $this->getOrmParams($alias['cn']);
							$flds = $this->ormFields($eorm, false);
							foreach ($flds as $fld) {
								if (isset($alias['eagerPrefix'])) {
									$q = $q.', '.$alias['alias'].'.'.$fld.' as e'.$alias['eagerPrefix'].'__'.$fld;
								} else {
									$q = $q.', '.$alias['alias'].'.'.$fld;
								}
							}
							if (!$orm['discriminator'] && in_array($alias['cn'], $orm['descendants'])) {
								$q = $q.', '.$alias['alias'].'.'.$eorm['map'][$eorm['key'][0]]['fields'][0].' is not null as is_class_'.$eorm['index'];
							}
						}break;
						case 'eager': {
							$eorm = $this->getOrmParams($alias['cn']);
							$flds = $this->ormFields($eorm, true);
							foreach ($flds as $fld) {
								$q = $q.', '.$alias['alias'].'.'.$fld.' as e'.$alias['eagerPrefix'].'__'.$fld;
							}
						}break;
					}
				}
			}
		}
		
		$q = $q.' from '.$orm['tn'].' as main '.implode(' ', $joins);
		
		if ($conditions) {
			$where = $where ? ('('.$conditions.') and ('.$where.')') : $conditions;
		}
		
		if ($where) {
			$q = $q.' where '.$where;
		}
		
		if (!empty($sorts) && !$quantaty) {
			$q = $q.' order by '.implode(', ', $sorts);
		}
		
		if (!$quantaty && (isset($offset) && $offset || isset($count) && $count)) {
			$lim = [];
			if (isset($offset) && $offset) {
				$lim[] = $offset;
			}
			if (isset($count) && $count) {
				$lim[] = $count;
			}
			$q = $q.' limit '.implode(',', $lim);
		}
		return $q;
	}
	
	private function processData($orm, $data, $cb) {
		foreach ($orm['map'] as $prop => $fld) {
			if (isset($data[$prop]) && !$fld['is_array']) {
				$values = [];
				if ($fld['is_ref'] && is_array($data[$prop])) {
					$values = $data[$prop];
				} else if ($fld['is_ref'] && is_object($data[$prop])) {
					$values = $this->getObjectKey($data[$prop]);
					if (!$values) {
						continue;
					}
				} else if ($fld['is_ref'] && is_string($data[$prop])) {
					$values = array_values($this->idToKeyData($fld['type'], $data[$prop]));
				} else {
					$values[] = $data[$prop];
				}
				
				$n = count($fld['fields']);
				for ($j = 0; $j < $n; $j++) {
					if (!isset($values[$j]) && $n > 1) {
						throw new \Exception('No data set for reference field '.$fld['fields'][$j].'!');
					}
					$cb($fld['fields'][$j], $values[$j]);
				}
			}
		}
	}
	
	private function insert($orm, $data, $cn = null) {
		$key_data = [];
		$key = $orm['key'];
		
		$tn = $orm['tn'];
		if (isset($orm['parent'])) {
			$key_data = $this->insert($orm['parent'], $data, $cn ?? $orm['cn']);
			$n = count($key);
			for ($i = 0; $i < $n; $i++) {
				if (!isset($key_data[$i])) {
					throw new \Exception('Invalid key');
				}
				$data[$key[$i]] = $key_data[$i];
			}
		}
		
		if ($tn == '_virtual') {
			return $key_data;
		}
		
		$fields = [];
		$pexpr = [];
		$params = [];
		$i = 1;
		
		$record = [];
		
		if ($orm['discriminator']) {
			$data[$orm['discriminator']] = $cn ?? $orm['cn'];
		}
		
		$this->processData($orm, $data, function ($fld, $value) use (&$fields, &$pexpr, &$params, &$record, &$i) {
			if (!isset($record[$fld])) {
				$fields[] = $fld;
				$pexpr[] = '?';
				$params[$i] = $value;
				$record[$fld] = $value;
				$i++;
			}
		});
		
		$fields = implode(', ', $fields);
		$pexpr = implode(', ', $pexpr);
		
		$out = [];
		$this->ds->execute("insert into $tn ($fields) values ($pexpr)", $params, $out);
		if (count($key_data) == 0) {
			if ($out['rowid'] && count($key) == 1) {
				$key_data = [$out['rowid']];	
			} else {
				foreach ($key as $k) {
					if (isset($orm['map'][$k])) { 
						foreach ($orm['map'][$k]['fields'] as $fld) {
							if (isset($record[$fld])) {
								$key_data[] = $record[$fld];
							} else {
								throw new \Exception('Key data was not provided for class '.$orm['cn']);
							}
						}
					}
				}
			}
		}
		
		return $key_data;
	}
	
	private function update($orm, $id, $data) {
		$key_data = [];
		$key = $orm['key'];
		$tn = $orm['tn'];
		$count = 0;
		if (isset($orm['parent'])) {
			$count = $this->update($orm['parent'], $id, $data);
		}
	
		if ($tn == '_virtual') {
			return $count;
		}
				
		$fields = [];
		$params = [];
		$i = 1;
		
		$this->processData($orm, $data, function ($fld, $value) use (&$fields, &$params, &$i) {
			$fields[] = $fld.' = ?';
			$params[$i] = $value;
			$i++;
		});
		
		if (count($fields) == 0) {
			return $count;
		}
		
		$filter = [];
		$id = is_array($id) ? $id : [$id];
		$j = 0;
		foreach ($orm['key'] as $k) {
			$filter[] = $orm['map'][$k]['fields'][0].' = ?';
			$params[$i] = $this->castValue($id[$j], $orm['map'][$k]['type']);
			$j++;
			$i++;
		}
		
		$fields = implode(', ', $fields);
		$filter = implode(' and ', $filter);
		$out = [];
		$this->ds->execute("update $tn set $fields where $filter", $params, $out);
		$count = $count ? $count : $out['affected'];
		return $count;
	}
	
	private function del($orm, $id) {
		$key_data = [];
		$key = $orm['key'];
		$tn = $orm['tn'];
		$count = 0;
		if (isset($orm['parent'])) {
			$count = $this->del($orm['parent'], $id);
			if ($count == 0) {
				return $count;
			}
		}
		
		if ($tn == '_virtual') {
			return $count;
		}
			
		$filter = [];
		$params = [];
		$i = 0;
		$id = is_array($id) ? $id : [$id];
		foreach ($orm['key'] as $k) {
			$prop = $orm['map'][$k];
			
			foreach ($prop['fields'] as $fld) {
				$filter[] = $fld.' = ?';
				$params[$i + 1] = $id[$i];
				$i++;
			}
		}
	
		$filter = implode(' and ', $filter);
	
		$this->ds->execute("delete from $tn where $filter", $params, $out);
		return $out['affected'];
	}

	/**
	 * @param object $obj
	 * @return mixed
	 */
	public function key($obj, $multikey_as_string = true) {
		if (is_string($obj)) {
			$orm = $this->getOrmParams($obj);
			return $orm['key'];
		} else {
			$kvs = $this->getObjectKey($obj);
			if (count($kvs) == 1) {
				return $kvs[0];
			} else if ($multikey_as_string && $this->multiFieldIdSeparator) {
				return implode($this->multiFieldIdSeparator, $kvs);
			}
			return $kvs;
		}
	}
	
	private function idToKeyData($class, $id) {
		$kv = $this->splitKey($id);
		if (!is_array($kv)) {
			$kv = [$kv];
		}
		$orm = $this->getOrmParams($class);
		$n = count($orm['key']);
		$result = [];
		for ($i = 0; $i < $n; $i++) {
			if (isset($kv[$i])) {
				$prop = $orm['key'][$i];
				$result[$prop] = $this->castValue($kv[$i], $orm['map'][$prop]['type']);
			}
		}
		return $result;
	}
	
	private function splitKey($id) {
		if (is_string($id)) {
			return explode($this->multiFieldIdSeparator, $id);
		}
		return [$id];
	}
	
	private function castValue($v, $type) {
		if ($type === '\DateTime' && !($v instanceof \DateTime)) {
			return new DateTime($v);
		}
		switch ($type) {
			case 'bool': return boolval($v);
			case 'float': return floatval($v);
			case 'int': return intval($v);
		}
		return $v;
	}
	
	private function assignProperties($item, $orm, $obj) {
		foreach ($orm['map'] as $p => $prop) {
			if (!$prop['is_array']) {
				if ($prop['is_ref']) {
					$refObj = new \stdClass();
					$emptyObj = true;
					foreach ($obj as $f => $v) {
						$pref = 'e_'.$prop['index'];
						if (strpos($f, $pref) === 0) {
							$f2 = str_replace($pref.'_', '', $f);
							if ($f2) {
								if ($f2[0] == '_') {
									$f2 = substr($f2, 1);
								} else {
									$f2 = 'e_'.$f2;
								}
								$refObj->$f2 = $v;
								if ($v) {
									$emptyObj = false;
								}
							}
						}
					}
					if (!$emptyObj) {
						$refOrm = $this->getOrmParams($prop['type']);
						$refCn = $refOrm['cn'];
						$refItem = new $refCn();
						$this->assignProperties($refItem, $refOrm, $refObj);						
						$item->$p = $refItem;
						continue;
					}
				}
				$v = [];
				$hasNulls = false;
				foreach ($prop['fields'] as $f) {
					if (isset($obj->$f)) {
						$tmp = $this->castValue($obj->$f, $prop['type']);
						if (is_null($tmp)) {
							$hasNulls = true;
						}
						$v[] = $tmp;
					} else {
						$hasNulls = true;
					}
				}
				if ($prop['is_ref']) {
					if ($hasNulls) {
						$item->$p = null;
					} else {
						$item->$p = empty($v) ? null : (count($v) == 1 ? $v[0] : implode($this->multiFieldIdSeparator, $v));
					}
				} else {
					$item->$p = empty($v) ? null : (count($v) == 1 ? $v[0] : $v);
				}
			}
		}
		if (isset($orm['parent'])) {
			return $this->assignProperties($item, $orm['parent'], $obj);
		}
		return $item;
	}
	
	
	private function w($cn, $orm, $obj) {
		if ($orm['discriminator']) {
			$d = $orm['map'][$orm['discriminator']]['fields'][0];
			if (isset($obj->$d) && $obj->$d) {
				$cn = $obj->$d;
				$orm = $this->getOrmParams($cn);
			}
		} else {
			$descs = $orm['descendants'];
			foreach ($descs as $desc) {
				$dorm = $this->getOrmParams($desc);
				$chf = 'is_class_'.$dorm['index'];
				if (isset($obj->$chf) && $obj->$chf) {
					$cn = $desc;
					$orm = $dorm;
					break;
				}
			}
		}
		
		if (strpos($cn, '\\') === false) {
			$cn = $this->namespace.$cn;
		}
		return $this->assignProperties(new $cn(), $orm, $obj);
	}
	
	private function Cb($item, $options = []) {
		return (isset($options['wrapper']) && is_callable($options['wrapper'])) ? $options['wrapper']($item) : $item;
	}
	
	/**
	 * @param string $cn
	 * @param array $data
	 * @param array $options
	 * @return object
	 */
	public function create($cn, $data, $options = []) {
		$orm = $this->getOrmParams($cn);
		if (is_object($data)) {
			$data = get_object_vars($data);
		}
		if (isset($orm['discriminator'])) {
			$data[$orm['discriminator']] = $cn;
		}
		
		$this->begin();
		try {
			$key = $this->insert($orm, $data);
			$params = [];
			$filter = [];
			$i = 0;
			foreach ($orm['key'] as $p) {
				$filter[] = [FO::EQ => [$p, ':'.$p]];
				$params[':'.$p] = $key[$i];
				$i++;
			}
			$options['filter'] = [FO::AND => $filter];
			$options['count'] = 1;
			$this->commit();
			return $this->Cb($this->w($cn, $orm, $this->ds->get($this->buildSelect($cn, $options), $params)), $options);
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
	}
	
	/**
	 * @param string $cn
	 * @param mixed $id
	 * @param array | object $data
	 * @param array $options
	 * @return object
	 */
	public function edit($cn, $id, $data, $options = []) {
		$orm = $this->getOrmParams($cn);
		if (is_object($data)) {
			$data = get_object_vars($data);
		}
		
		$this->begin();
		try {
			$affected = $this->update($orm, $this->splitKey($id), $data);
			$this->commit();
			if ($affected == 0) {
				return null;
			}
			$idData = $this->idToKeyData($cn, $id);
			foreach ($idData as $prop => $v) {
				if (isset($data[$prop])) {
					$idData[$prop] = $data[$prop];
				}
			}
			$id2 = implode($this->multiFieldIdSeparator, $this->dataToKey((object)$idData, $cn));
			return $this->getById($cn, $id2, $options);
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
	}
	
	/**
	 * @param object $obj
	 * @param array $options
	 * @return object
	 */
	public function save($obj, $options = []) {
		$orm = $this->getOrmParams(get_class($obj));
		$id = $this->getObjectKey($obj);
		
		$data = [];
		foreach ($obj as $prop => $v) {
			if (!in_array($prop, $orm['key'])) {
				$data[$prop] = $v;
			}
		}
		$this->begin();
		try {
			$affected = $this->update($orm, $id, $data);
			if ($affected == 0) {
				foreach ($orm['key'] as $k) {
					$data[$k] = $obj->$k;
				}
				$id = $this->insert($orm, $data);
			}
			$this->commit();
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
		$params = [];
		$filter = [];
		$i = 0;
		foreach ($orm['key'] as $p) {
			$filter[] = [FO::EQ => [$p, ':'.$p]];
			$params[':'.$p] = $key[$i];
			$i++;
		}
		$options['filter'] = [FO::AND => $filter];
		$options['count'] = 1;
		return $this->Cb($this->w($cn, $orm, $this->ds->get($this->buildSelect($cn, $options), $params), $this->checkCb($options)), $options);
	}
	
	/**
	 * @param string | object $cn
	 * @param string $id
	 * @return boolean
	 */
	public function delete($cn, $id = null) {
		if (is_object($cn)) {
			$orm = $this->getOrmParams(get_class($cn));
			$id = $this->getObjectKey($cn);
		} else {
			$orm = $this->getOrmParams($cn);
		}
		
		$id = $this->splitKey($id);
		$affected = 0;
		$this->begin();
		try {
			$affected = $this->del($orm, $id);
			$this->commit();
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
		return $affected > 0;
	}
	
	private function eagerLoadCollections($orm, $baseList, $eager) {
		$collections = [];
		foreach ($eager as $p) {
			$path = explode('.', $p);
			if (isset($orm['map'][$path[0]])) {
				$p = $orm['map'][$path[0]];
				if ($p['is_ref'] && $p['is_array'] && isset($p['backRef']) && $p['backRef']) {
					if (!isset(	$collections[$path[0]])) {
						$corm = $this->getOrmParams($p['type']);
						$collections[$path[0]] = [
								'eager' => [],
								'property' => $p['backRef'],
								'orm' => $corm
						];
					}
					if (count($path) > 1) {
						$collections[$path[0]]['eager'][] = implode('.', array_slice($path, 1));
					}
				}
			}
		}
		
		if (empty($collections)) {
			return;
		}
		
		$filter = [];
		$listMap = [];
		
		foreach ($baseList as $item) {
			$key = $this->getObjectKey($item);
			$filter[] = (count($key) == 1) ? $key[0] : $key;
			foreach ($eager as $p) {
				$path = explode('.', $p);
				$pn = $path[0];
				$p = $orm['map'][$pn];
				if ($p['is_ref'] && $p['is_array'] && isset($p['backRef']) && $p['backRef']) {
					if (!is_array($item->$pn)) {
						$item->$pn = [];
					}
				}
			}
			$listMap[implode($this->multiFieldIdSeparator, $key)] = $item;
		}
		
		foreach ($collections as $pn => $c) {
			$corm = $c['orm'];
			$coll = [];
			$n = count($corm['map'][$c['property']]['fields']);
			if ($n == 1) {
				if (count($filter) > 0) {
					$coll = $this->fetch($corm['cn'],[
						'filter' => [FO::IN => [$c['property'], $filter]],
						'eager' => $c['eager']
					],[]);
				}
			} else if ($n > 0) {
				$f = [];
				$fetch = false;
				$brp = $corm['map'][$c['property']];
				$cprms = [];
				$prmn = 1;
				foreach ($filter as $key) {
					$f1 = [];
					for ($i = 0; $i < $n; $i++) {
						$fetch = true;
						$cprms['p'.$prmn] = $key[$i];
						$f1[] = 'main.'.$brp['fields'][$i].' = :p'.$prmn; 
						$prmn++;
					}
					$f[] = '('.join(' and ', $f1).')';
				}
				if ($fetch) {
					$copts = ['eager' => $c['eager']];
					$q = $this->buildSelect($corm['cn'], $copts, false, join(' or ', $f));
					$coll = $this->qfetch($corm, $q, $copts, $cprms);
				}
			}

			foreach ($coll as $ci) {
				$p = $c['property'];
				$rv = $ci->$p;
				$sk = '';
				if (is_array($rv)) {
					$sk = implode($this->multiFieldIdSeparator, $rv);
				} else if (is_a($rv, $corm['cn'])) {
					$sk = $this->key($rv);
				} else {
					$sk = $rv;
				}
				if (isset($listMap[$sk])) {
					$listMap[$sk]->$pn[] = $ci;
				}
			}
		}
	}
	
	private function processParams($params) {
		$result = [];
		foreach ($params as $p => $v) {
			if (is_array($v)) {
				$n = count($v);
				for ($i = 0; $i < $n; $i++) {
					$result[$p.'_'.$i] = $v[$i];
				}
			} else {
				$result[$p] = $v;
			}
		}
		return $result;
	}
	
	private function qfetch($orm, $q, $options, array $params = []) {
		try {
			$result = [];
			$items = $this->ds->query($q, $this->processParams($params));
			foreach ($items as $item) {
				$result[] = $this->w($orm['cn'], $orm, $item);
			}
			
			if (isset($options['eager']) && is_array($options['eager']) && !empty($options['eager'])) {
				$this->eagerLoadCollections($orm, $result, $options['eager']);
			}
			
			array_walk($result, function (&$item) use ($options) {
				$item = $this->Cb($item, $options);
			});
			return $result;
		} catch (\Throwable $e) {
			throw $e;
		}
	}
	
	/**
	 * @param string $cn
	 * @param array $options
	 * @param array $params
	 * @return array
	 */
	public function fetch($cn, array $options = [], array $params = []) {
		unset($options['fetch']);
		$orm = $this->getOrmParams($cn, isset($options['descendants']) ? $options['descendants'] : []);
		$q = $this->buildSelect($cn, $options);
		return $this->qfetch($orm, $q, $options, $params);
	}
	
	/**
	 * @param string $cn
	 * @param array $options
	 * @param array $params
	 * @return array
	 */
	public function aggregate($cn, array $options = [], array $params = []) {
		$result = [];
		unset($options['eager']);
		$q = $this->buildSelect($cn, $options);
		try {
			return $this->ds->query($q, $this->processParams($params));
		} catch (\Throwable $e) {
			throw $e;
		}
	}
	
	/**
	 * @param string $cn
	 * @param array $options
	 * @param array $params
	 * @return array
	 */
	public function count($cn, array $options = [], array $params = []) {
		$result = [];
		return intval($this->ds->scalar($this->buildSelect($cn, $options, true), $this->processParams($params)));
	}
	
	/**
	 * @param string $cn
	 * @param array $options
	 * @param array $params
	 * @return object
	 */
	public function get($cn, array $options = [], array $params = []) {
		$options['count'] = 1;
		$result = $this->fetch($cn, $options, $params);
		return empty($result) ? null : $result[0];
	}
	
	/**
	 * @param string $cn
	 * @param string $id
	 * @return object
	 */
	public function getById($cn, $id, $options = []) {
		$kd = $this->idToKeyData($cn, $id);
		$params = [];
		$filter = [];		
		foreach ($kd as $k => $d) {
			$params[$k] = $d;
			$filter[] = [FO::EQ => [$k, ':'.$k]];
		}
		
		$n = count($filter);
		
		if ($n > 1) {
			$options['filter'] = [FO::AND => $filter];
		} else if ($n == 1) {
			$options['filter'] = $filter[0];
		} else {
			throw new \Exception('Invalid id "'.$id.'" specified for class '.$cn);
		}
		$options['count'] = 1;
		$result = $this->fetch($cn, $options, $params);
		return empty($result) ? null : $result[0];
	}
	
	/**
	 * @param string $cn
	 * @param array $options
	 * @param array $params
	 * @return \Traversable
	 */
	public function iterate($cn, array $options = [], array $params = []) {
		return new CursorWrapper(
				$this->ds->iterate($this->buildSelect($cn, $options), $this->processParams($params)),
				function ($obj) use ($options) {
					$result = $this->w($cn, $this->getOrmParams($cn), $obj, $this->checkCb($options));
					if (isset($options['eager']) && is_array($options['eager']) && !empty($options['eager'])) {
						$this->eagerLoadCollections($orm, [$result], $options['eager']);
					}
					return $this->Cb($result, $options);
				}
			);
	}
	
	public function attrTypes($obj) {
		$result = new \stdClass();
		$orm = $this->getOrmParams(get_class($obj));
		foreach ($orm['map'] as $pn => $prop) {
			$result->$pn = new \stdClass();
			$result->$pn->type = $prop['type'];
			$result->$pn->ref = $prop['is_ref'];
			$result->$pn->collection = $prop['is_array'];
		}
		return $result;
	}
	
	public function lazyLoaders($obj, array $options = []) {
		$result = new \stdClass();
		$orm = $this->getOrmParams(get_class($obj));
		foreach ($orm['map'] as $pn => $prop) {
			if ($prop['is_ref']) {
				$result->$pn = $prop['is_array'] ?
					function () use ($obj, $pn, $prop) {
						$rorm = $this->getOrmParams($prop['type']);
						$id = $this->getObjectKey($obj);
						$params = ['id' => $id];
						$options = ['filter' => [FO::EQ => [$prop['backRef'], ':id']]];
						return $this->fetch($rorm['cn'], $options, $params);
					} :
					function () use ($obj, $pn, $prop) {
						$rorm = $this->getOrmParams($prop['type']);
						$options = ['count' => 1];
						$refv = $obj->$pn;
						$n = count($rorm['keys']);
						$params = [];
						if ($n == 1) {
							$options['filter'] = [FO::EQ => [$rorm['keys'][0], ':id']];
							$params['id'] = $refv;
						} else {
							$conditions = [];
							for ($i = 0; $i < $n; $i++) {
								$conditions[] = [FO::EQ => [$rorm['keys'][$i], $refv[$i]]];
							}
							$options['filter'] = [FO::AND => $conditions];
						}
						$refs = $this->fetch($rorm['cn'], $options, $params);
						return (!empty($refs)) ? $refs[0] : null;
					};
			}
		}
		return $result;
	}
}