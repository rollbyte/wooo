<?php

namespace wooo\lib\orm;

use wooo\lib\dbal\interfaces\DbDriver;
use wooo\lib\dbal\interfaces\DbCursor;
use wooo\core\DateTime;

class Mapper
{
  
    private $namespace = '';
  
    /**
     *
     * @var DbDriver $ds
     */
    private $ds;
  
    /**
     *
     * @var array $ormConfig
     */
    private $ormConfig = [];
  
    private $ormCounter = 0;
    
    private $descendants = [];
  
    /**
     *
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
  
    public function __construct(DbDriver $ds, $ns = '', $mfis = null)
    {
        $this->ds = $ds;
        $this->namespace = $ns;
        if ($this->namespace) {
            if ($this->namespace[0] != '\\') {
                $this->namespace = '\\' . $this->namespace;
            }
            if ($this->namespace[strlen($this->namespace) - 1] != '\\') {
                $this->namespace = $this->namespace . '\\';
            }
        }
        if ($mfis) {
            $this->multiFieldIdSeparator = $mfis;
        }
    }
    
    private function className($cn): string
    {
        if (strpos($cn, '\\') === false) {
            return $this->namespace . $cn;
        }
        return $cn;
    }
    
    public function setDescendants(array $setup): Mapper
    {
        foreach ($setup as $parent => $descs) {
            if (is_string($descs)) {
                $descs = [$descs];
            }
            if (is_array($descs)) {
                $pcn = $this->className($parent);
                array_walk(
                    $descs,
                    function (&$v) use ($pcn) {
                        if (!$v || !is_string($v)) {
                            $v = false;
                        }
                        $v = $this->className($v);
                        if (!is_subclass_of($v, $pcn)) {
                            $v = false;
                        }
                    }
                );
                $descs = array_filter($descs);
                
                if (isset($this->ormConfig[$pcn])) {
                    $this->ormConfig[$pcn]['descendants'] = isset($this->ormConfig[$pcn]['descendants']) ?
                    array_unique($this->ormConfig[$pcn]['descendants'] + $descs) :
                        $descs;
                } else {
                    $this->descendants[$pcn] = isset($this->descendants[$pcn]) ?
                        array_unique($this->descendants[$pcn] + $descs) :
                        $descs;
                }
            }
        }
        return $this;
    }
  
    public function multiFieldIdSeparator(): string
    {
        return $this->multiFieldIdSeparator;
    }
  
    public function begin()
    {
        $this->ds->begin();
    }
  
    public function commit()
    {
        $this->ds->commit();
    }
  
    public function rollback()
    {
        $this->ds->rollback();
    }
  
    /**
     *
     * @param string $cn
     * @param array  $descendants
     */
    private function &getOrmParams($cn, $descendants = [])
    {
        $cn = $this->className($cn);
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
                $this->ormConfig[$cn]['parent']['descendants'] = isset($this->ormConfig[$cn]['parent']['descendants']) ?
                    array_unique($this->ormConfig[$cn]['parent']['descendants'] + [$cn]) :
                    [$cn];
            }
            $this->ormConfig[$cn]['tn'] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $rc->getShortName()));
            $doc = $rc->getDocComment();
            if ($doc) {
                $lineend = '.*$/m';
                $matches = null;
                preg_match_all('/@orm.table\s+([_\w]+)' . $lineend, $doc, $matches);
                $n = count($matches[1]);
                if ($n > 0) {
                    $this->ormConfig[$cn]['tn'] = $matches[1][0];
                }
        
                preg_match_all('/@orm.discriminator\s+([_\w]+)' . $lineend, $doc, $matches);
                $n = count($matches[1]);
                if ($n > 0) {
                    $this->ormConfig[$cn]['discriminator'] = $matches[1][0];
                }
        
                preg_match_all('/@orm.key\s+([_\w]+)' . $lineend, $doc, $matches);
                if (!empty($matches[1])) {
                    $this->ormConfig[$cn]['key'] = $matches[1];
                }
            }
      
            $props = $rc->getProperties(\ReflectionProperty::IS_PUBLIC & ~ \ReflectionProperty::IS_STATIC);
      
            if (!isset($this->ormConfig[$cn]['key'])) {
                if (count($props) > 0) {
                    $this->ormConfig[$cn]['key'] = [
                    $props[0]->getName()
                    ];
                } else {
                    throw new OrmException(OrmException::CLASS_NO_KEY, [$cn]);
                }
            }
      
            $this->ormConfig[$cn]['map'] = [];
            $ind = 1;
            foreach ($props as $p) {
                if ($p->getDeclaringClass()->getName() == $rc->getName()) {
                    $this->ormConfig[$cn]['map'][$p->getName()] = [];
                    $pdoc = $p->getDocComment();
                    $datatype = 'string';
                    $is_ref = false;
                    $is_array = false;
          
                    if (preg_match('/@var\s+([_\w\\\]+)(\[\])?/', $pdoc, $matches)) {
                        $datatype = $matches[1];
                        if (
                            $datatype !== 'string' &&
                            $datatype !== 'int' && $datatype !== 'bool' &&
                            $datatype !== 'float'
                        ) {
                            if ($datatype == 'DateTime' || $datatype == '\DateTime') {
                                $dateC = new \ReflectionClass($datatype);
                                $datatype = $dateC->getName();
                            } else {
                                $datatype = $this->className($datatype);
                                $is_ref = class_exists($datatype, true);
                                $refC = new \ReflectionClass($datatype);
                                $datatype = $refC->getName();
                                if (count($matches) > 2 && $matches[2] == '[]') {
                                    $is_array = true;
                                }
                            }
                        }
                    }
                    $this->ormConfig[$cn]['map'][$p->getName()]['index'] = $ind;
                    $this->ormConfig[$cn]['map'][$p->getName()]['immutables'] = [];
          
                    $this->ormConfig[$cn]['map'][$p->getName()]['type'] = $datatype;
                    $this->ormConfig[$cn]['map'][$p->getName()]['is_ref'] = $is_ref;
                    $this->ormConfig[$cn]['map'][$p->getName()]['is_array'] = $is_array;
                    $this->ormConfig[$cn]['map'][$p->getName()]['is_key'] =
                        in_array($p->getName(), $this->ormConfig[$cn]['key']);
          
                    if (!$is_array) {
                        if (preg_match_all('/@orm.immutable\s+(\w[_\w]*)\s*$/m', $pdoc, $matches)) {
                            if (count($matches) > 1) {
                                $this->ormConfig[$cn]['map'][$p->getName()]['immutables'] = $matches[1];
                            }
                        }

                        $this->ormConfig[$cn]['map'][$p->getName()]['fields'] = [
                            strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $p->getName()))
                        ];
                    
                        if (preg_match_all('/@orm.field\s+(\w[_\w]*)\s*$/m', $pdoc, $matches)) {
                            if (count($matches) > 1) {
                                $this->ormConfig[$cn]['map'][$p->getName()]['fields'] = $matches[1];
                            }
                        }
                    } else {
                        $this->ormConfig[$cn]['map'][$p->getName()]['fields'] = [];
                    }
          
                    if (preg_match_all('/@orm.backRef\s+(\w[_\w]*)\s*$/m', $pdoc, $matches)) {
                        if (count($matches) > 1) {
                            $this->ormConfig[$cn]['map'][$p->getName()]['backRef'] = $matches[1][0];
                        }
                    }

                    if (preg_match_all('/@orm.filter\s+(\w[_\w]*)\s*=\s*([_\w]*)$/m', $pdoc, $matches)) {
                        if (count($matches) > 2) {
                            $this->ormConfig[$cn]['map'][$p->getName()]['filter'] =
                                array_combine($matches[1], $matches[2]);
                        }
                    }

                    $ind++;
                }
            }
        }
        
        if (!isset($this->ormConfig[$cn]['descendants']) && isset($this->descendants[$cn])) {
            $this->ormConfig[$cn]['descendants'] = $this->descendants[$cn];
        }
    
        if (is_array($descendants)) {
            array_walk(
                $descendants,
                function (&$desc) use ($cn) {
                    $desc = $this->className($desc);
                    if (!is_subclass_of($desc, $cn)) {
                        $desc = false;
                    }
                }
            );
            $d = array_filter($descendants);
            $this->ormConfig[$cn]['descendants'] = array_unique(array_merge($this->ormConfig[$cn]['descendants'], $d));
        }
    
        return $this->ormConfig[$cn];
    }
  
    private function dataToKey($obj, $cn)
    {
        $result = [];
        $orm = $this->getOrmParams($cn);
        if ($orm) {
            foreach ($orm['key'] as $k) {
                $v = $obj->$k;
                $keymeta = $this->propMeta($orm, $k);
                if (is_object($v) && !($v instanceof \DateTime)) {
                    if (!$keymeta['is_ref']) {
                        throw new OrmException(OrmException::INVALID_ATTRIBUTE_VALUE, [$k]);
                    }
                    $kvs = $this->getObjectKey($v);
                    foreach ($kvs as $i => $kv) {
                        $result[$keymeta['fields'][$i]] = $kv;
                    }
                } else {
                    $flds = array_values(array_diff($keymeta['fields'], $keymeta['immutables'] ?? []));
                    $result[empty($flds) ? $keymeta['fields'][0] : $flds[0]] = $v;
                }
            }
        }
        return array_values($result);
    }
  
    private function getObjectKey($obj)
    {
        if (is_null($obj)) {
            return [];
        }
        return $this->dataToKey($obj, get_class($obj));
    }
  
    private function propOrm($orm, $prop)
    {
        if (isset($orm['map'][$prop])) {
            return $orm;
        }
        if (isset($orm['parent'])) {
            return $this->propOrm($orm['parent'], $prop);
        }
        throw new OrmException(OrmException::ATTR_NOT_FOUND, [$prop, $orm['cn']]);
    }
    
    private function propMeta($orm, $prop)
    {
        if (isset($orm['map'][$prop])) {
            return $orm['map'][$prop];
        }
        if (isset($orm['parent'])) {
            return $this->propMeta($orm['parent'], $prop);
        }
        throw new OrmException(OrmException::ATTR_NOT_FOUND, [$prop, $orm['cn']]);
    }
  
    private function createAlias(
        $context,
        $borm,
        $ref_prop,
        $orm,
        $type,
        $jointype,
        $back_ref,
        &$aliases,
        &$joins,
        &$count,
        $eagerPrefix = null
    ) {
        $prefix = $context;
        if ($ref_prop) {
            $prefix = $prefix . '_' . $ref_prop;
        }
        if (!isset($aliases[$prefix . '::' . $orm['tn']])) {
            $count++;
            $alias = 't' . $count;
            $aliases[$prefix . '::' . $orm['tn']] = [
            'alias' => $alias,
            'tn' => $orm['tn'],
            'cn' => $orm['cn'],
            'type' => $type,
            'context' => $prefix
            ];
            $join = $jointype . ' join ' . $orm['tn'] . ' as ' . $alias . ' on ';
            $conds = [];
            if ($ref_prop) {
                if ($back_ref) {
                    $rpmeta = $this->propMeta($orm, $ref_prop);
                    $i = 0;
                    foreach ($rpmeta['fields'] as $key) {
                        $bormkeymeta = $this->propMeta($borm, $borm['key'][$i]);
                        $conds[] = '(' . $alias . '.' . $key . ' = ' .
                            $context . '.' . $bormkeymeta['fields'][0] . ')';
                        $i++;
                    }
                } else {
                    $rpmeta = $this->propMeta($borm, $ref_prop);
                    $i = 0;
                    foreach ($rpmeta['fields'] as $key) {
                        $ormkeymeta = $this->propMeta($orm, $orm['key'][$i]);
                        $conds[] = '(' . $alias . '.' . $ormkeymeta['fields'][0] . ' = ' .
                            $context . '.' . $key . ')';
                        $i++;
                    }
                }
            } else {
                foreach ($borm['key'] as $i => $key) {
                    $ormkeymeta = $this->propMeta($orm, $orm['key'][$i]);
                    $bormkeymeta = $this->propMeta($borm, $key);
                    $conds[] = '(' . $alias . '.' . $ormkeymeta['fields'][0] .
                        ' = ' . $context . '.' . $bormkeymeta['fields'][0] . ')';
                }
            }
            $joins[] = $join . implode(' and ', $conds);
        }
        if ($eagerPrefix) {
            $aliases[$prefix . '::' . $orm['tn']]['eagerPrefix'] = $eagerPrefix;
        }
        return $aliases[$prefix . '::' . $orm['tn']];
    }
  
    private function processAncestors($context, $orm, $type, $jointype, &$aliases, &$joins, &$count)
    {
        if (isset($orm['parent'])) {
            $porm = $orm['parent'];
            $this->createAlias(
                $context,
                $orm,
                false,
                $porm,
                $type,
                $jointype,
                false,
                $aliases,
                $joins,
                $count
            );
            $this->processAncestors($context, $orm['parent'], $type, $jointype, $aliases, $joins, $count);
        }
    }
  
    private function processDescendants($context, $orm, &$aliases, &$joins, &$count, $eagerPref = null)
    {
        if (isset($orm['descendants']) && is_array($orm['descendants']) && !empty($orm['descendants'])) {
            foreach ($orm['descendants'] as $desc) {
                $dorm = $this->getOrmParams($desc);
                $this->createAlias(
                    $context,
                    $orm,
                    false,
                    $dorm,
                    'desc',
                    'left',
                    false,
                    $aliases,
                    $joins,
                    $count,
                    $eagerPref
                );
            }
        }
    }
  
    private function parseFilterAttr($context, $attr, $orm, &$aliases, &$joins, &$count, $jointype = null)
    {
        $a = $attr;
        $drill = '';
        if (($dotpos = strpos($attr, '.')) > 0) {
            $a = substr($attr, 0, $dotpos);
            $drill = substr($attr, $dotpos + 1);
        }
        $porm = $this->propOrm($orm, $a);
        $this->processAncestors($context, $porm, 'filter', $jointype ? $jointype : 'inner', $aliases, $joins, $count);
        $alias = $aliases[$context . '::' . $porm['tn']]['alias'];
    
        $prop = $this->propMeta($porm, $a);
    
        if ($drill) {
            if ($prop['is_ref']) {
                $rorm = $this->getOrmParams($prop['type']);
                $ra = $this->createAlias(
                    $alias,
                    $porm,
                    $prop['is_array'] ? $prop['backRef'] : $a,
                    $rorm,
                    'filter',
                    'left',
                    $prop['is_array'],
                    $aliases,
                    $joins,
                    $count
                );
                return $this->parseFilterAttr($ra['context'], $drill, $rorm, $aliases, $joins, $count, 'left');
            } else {
                throw new OrmException(OrmException::QUERY_SYTAX_ERROR);
            }
        }
    
        if (count($prop['fields']) > 1) {
            $result = [];
            foreach ($prop['fields'] as $fld) {
                $result[] = $alias . '.' . $fld;
            }
            return $result;
        }
    
        return $alias . '.' . $prop['fields'][0];
    }
  
    private function parseEagerAttr($prefix, $context, $attr, $orm, &$aliases, &$joins, $descendants, &$count)
    {
        $a = $attr;
        $drill = '';
        if (($dotpos = strpos($attr, '.')) > 0) {
            $a = substr($attr, 0, $dotpos);
            $drill = substr($attr, $dotpos + 1);
        }
        $porm = $this->propOrm($orm, $a);
        $prop = $this->propMeta($porm, $a);
    
        if ($prop['is_ref']) {
            if (!$prop['is_array']) {
                $this->processAncestors($context, $porm, 'eager', 'left', $aliases, $joins, $count);
                $alias = $aliases[$context . '::' . $porm['tn']]['alias'];
                $rorm = $this->getOrmParams($prop['type'], $descendants);
                $eagerPref = $prefix . '_' . $prop['index'];
                $ra = $this->createAlias(
                    $alias,
                    $porm,
                    $a,
                    $rorm,
                    'eager',
                    'left',
                    false,
                    $aliases,
                    $joins,
                    $count,
                    $eagerPref
                );
                $this->processDescendants($ra['alias'], $rorm, $aliases, $joins, $count, $eagerPref);
                if ($drill) {
                    $this->parseEagerAttr(
                        $eagerPref,
                        $ra['context'],
                        $drill,
                        $rorm,
                        $aliases,
                        $joins,
                        $descendants,
                        $count
                    );
                }
            }
        } else {
            throw new \Exception('Query syntax error!');
        }
    }
  
    private function sqlValue($v)
    {
        return ($v === null) ? 'null' : $v;
    }
  
    private function formUnarCondition($oper, $arg)
    {
        if (is_array($arg) && !empty($arg)) {
            if (count($arg) > 1) {
                $results = [];
                $i = 0;
                foreach ($arg as $a) {
                    $results[] = '(' . $oper . ' ' . $this->sqlValue($a) . ')';
                    $i++;
                }
                return '(' . implode(' and ', $results) . ')';
            } else {
                $arg = $arg[0];
            }
        }
        return '(' . $oper . ' ' . $this->sqlValue($arg) . ')';
    }
  
    private function formPostPredCondition($oper, $arg)
    {
        if (is_array($arg) && !empty($arg)) {
            if (count($arg) > 1) {
                $results = [];
                $i = 0;
                foreach ($arg as $a) {
                    $results[] = '(' . $this->sqlValue($a) . ' ' . $oper . ')';
                    $i++;
                }
                return '(' . implode(' and ', $results) . ')';
            } else {
                $arg = $arg[0];
            }
        }
        return '(' . $this->sqlValue($arg) . ' ' . $oper . ')';
    }
  
    private function formBinarCondition($oper, $arg1, $arg2)
    {
        if (!is_array($arg1)) {
            $arg1 = [$arg1];
        }
        if (!is_array($arg2)) {
            $v = $arg2;
            $arg2 = [];
            $n = count($arg1);
            if ($n > 1) {
                for ($i = 0; $i < $n; $i++) {
                    $arg2[] = $v . '_' . $i;
                }
            } else {
                $arg2[] = $v;
            }
        }
    
        $result = [];
        $n = count($arg1);
        for ($i = 0; $i < $n; $i++) {
            if ($n == 1) {
                if ($arg1[$i] === 'null' || $arg2[$i] === 'null') {
                    $arg = ($arg1[$i] === 'null') ? $arg2[$i] : $arg1[$i];
                    if ($oper === '=') {
                        return $this->sqlValue($arg) . ' is null';
                    }
                    if ($oper === '<>') {
                        return $this->sqlValue($arg) . ' is not null';
                    }
                }
        
                return '(' . $this->sqlValue($arg1[$i]) . ' ' . $oper . ' ' . $this->sqlValue($arg2[$i]) . ')';
            }
            $result[] = '(' . $this->sqlValue($arg1[$i]) . ' ' .
                        $oper . ' ' .
                        $this->sqlValue($arg2[$i]) . ')';
        }
        return (count($result) == 1) ? $result[0] : '(' . implode(' and ', $result) . ')';
    }
  
    private function formBetweenCondition($arg1, $arg2, $arg3)
    {
        if (!is_array($arg1)) {
            $arg1 = [
            $arg1
            ];
        }
        if (!is_array($arg2)) {
            $arg1 = [
            $arg2
            ];
        }
        if (!is_array($arg3)) {
            $arg3 = [
            $arg3
            ];
        }
        $result = [];
        $n = count($arg1);
        for ($i = 0; $i < count($arg1); $i++) {
            if ($n == 1) {
                return '(' . $this->sqlValue($arg1[$i]) . ' between ' .
                       $this->sqlValue($arg2[$i]) . ' and ' .
                       $this->sqlValue($arg3[$i]) . ')';
            }
            $result[] = '(' . $this->sqlValue($arg1[$i]) . ' between ' .
                    $this->sqlValue($arg2[$i]) . ' and ' .
                    $this->sqlValue($arg3[$i]) . ')';
        }
    
        return (count($result) == 1) ? $result[0] : '(' . implode(' and ', $result) . ')';
    }
  
    private function parseFilter($context, $orm, $filter, &$aliases, &$joins, &$count)
    {
        if (!$count) {
            $count = 1;
        }
        if (is_array($filter)) {
            if (array_values($filter) === $filter) {
                array_walk(
                    $filter,
                    function (&$v) {
                        if (is_string($v) && $v[0] !== ':' && $v !== '?') {
                            $v = "'$v'";
                        }
                    }
                );
                return $filter;
            }
      
            foreach ($filter as $oper => $args) {
                if (!is_array($args) || count($args) == 0) {
                    throw new OrmException(OrmException::QUERY_SYTAX_ERROR);
                }
                if (in_array($oper, FO::l())) {
                    $type = self::$operTypes[$oper];
                    switch ($type) {
                        case 'unar':
                            return $this->formUnarCondition(
                                self::$opers[$oper],
                                $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count)
                            );
                        case 'binar':
                            $oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
                            $oper2 = $this->parseFilter($context, $orm, $args[1], $aliases, $joins, $count);
                            return $this->formBinarCondition(self::$opers[$oper], $oper1, $oper2);
                        case 'multi':
                            $opers = [];
                            foreach ($args as $arg) {
                                $opers[] = $this->parseFilter($context, $orm, $arg, $aliases, $joins, $count);
                            }
                            return '(' . implode(' ' . self::$opers[$oper] . ' ', $opers) . ')';
                        case 'postpred':
                            return $this->formPostPredCondition(
                                self::$opers[$oper],
                                $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count)
                            );
                        default:
                            switch ($oper) {
                                case FO::BETWEEN:
                                    $oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
                                    $oper2 = $this->parseFilter($context, $orm, $args[1], $aliases, $joins, $count);
                                    $oper3 = $this->parseFilter($context, $orm, $args[2], $aliases, $joins, $count);
                                    return $this->formBetweenCondition($oper1, $oper2, $oper3);
                                case FO::IN:
                                    $oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
                                    $arr = $args[1];
                                    array_walk(
                                        $arr,
                                        function (&$v) {
                                            if (is_string($v)) {
                                                $v = "'" . $v . "'";
                                            }
                                        }
                                    );
                                    return '(' . $this->sqlValue($oper1) . ' in (' . implode(',', $arr) . '))';
                                case FO::NVL:
                                    $opers = [];
                                    foreach ($args as $arg) {
                                        $opers[] = $this->parseFilter($context, $orm, $arg, $aliases, $joins, $count);
                                    }
                                    return 'coalesce(' . implode(', ', $opers) . ')';
                                case FO::LIKE:
                                    $oper1 = $this->parseFilter($context, $orm, $args[0], $aliases, $joins, $count);
                                    $oper2 = $this->parseFilter($context, $orm, $args[1], $aliases, $joins, $count);
                                    return '(' . $this->sqlValue($oper1) . ' LIKE ' . $this->sqlValue($oper2) . ')';
                            }
                            break;
                    }
                }
                throw new OrmException(OrmException::INVALID_OPER, [$oper]);
            }
        } elseif (is_string($filter)) {
            if (!$filter || preg_match('/\s+/', $filter) == 1) {
                return "'$filter'";
            }
            if ($filter && (($filter == '?') || ($filter[0] == ':'))) {
                return $filter;
            }
            return $this->parseFilterAttr($context, $filter, $orm, $aliases, $joins, $count);
        } elseif ($filter instanceof \DateTime) {
            return '\'' . $filter->format(\DateTime::ISO8601) . '\'';
        } elseif (is_bool($filter)) {
            return $filter ? '1' : '0';
        } elseif (is_null($filter)) {
            return 'null';
        }
        return $filter;
    }
  
    private function parseEager($prefix, $context, $orm, $eager, &$aliases, &$joins, $descendants, &$count = 0)
    {
        if (!$count) {
            $count = 1;
        }
        foreach ($eager as $attr) {
            $this->parseEagerAttr($prefix, $context, $attr, $orm, $aliases, $joins, $descendants, $count);
        }
    }
  
    private function ormFields($orm, $withKeys = false)
    {
        $result = [];
        if ($withKeys && isset($orm['parent'])) {
            foreach ($orm['key'] as $p) {
                if (!isset($orm['map'][$p])) {
                    $kpm = $this->propMeta($orm['parent'], $p);
                    $result = array_merge($result, $kpm['fields']);
                }
            }
        }
        
        foreach ($orm['map'] as $p) {
            if (!$p['is_array'] && (!$p['is_key'] || $withKeys)) {
                $result = array_merge($result, $p['fields']);
            }
        }
        return array_unique($result, SORT_STRING);
    }
  
    private function parseFetch($orm, $fetch, &$groupBy)
    {
        // TODO Парсинг жаднозагружаемых атрибутов и атрибутов дочерних классов
        $result = [];
        foreach ($fetch as $alias => $fld) {
            if (is_string($fld)) {
                $f = $this->propMeta($orm, $fld)['fields'][0];
                $result[] = 'main.' . $f . ' as ' . $alias;
                $groupBy[] = 'main.' . $f;
            } elseif (is_array($fld)) {
                foreach ($fld as $oper => $val) {
                    switch ($oper) {
                        case AGGREG::SUM:
                            $result[] = 'sum(main.' . $this->propMeta($orm, $val)['fields'][0] . ') as ' . $alias;
                            break;
                        case AGGREG::AVG:
                            $result[] = 'avg(main.' . $this->propMeta($orm, $val)['fields'][0] . ') as ' . $alias;
                            break;
                        case AGGREG::COUNT:
                            $result[] = 'count(main.' . $this->propMeta($orm, $val)['fields'][0] . ') as ' . $alias;
                            break;
                        case AGGREG::MIN:
                            $result[] = 'min(main.' . $this->propMeta($orm, $val)['fields'][0] . ') as ' . $alias;
                            break;
                        case AGGREG::MAX:
                            $result[] = 'max(main.' . $this->propMeta($orm, $val)['fields'][0] . ') as ' . $alias;
                            break;
                    }
                }
            }
        }
        return join(', ', $result);
    }
  
    private function buildSelect(string $cn, array $options = [], $quantaty = false, $conditions = false)
    {
        $descendants = null;
        $fetch = null;
        $eager = null;
        $filter = null;
        $sort = null;
        $offset = null;
        $count = null;
        extract($options, EXTR_IF_EXISTS);
    
        $orm = $this->getOrmParams($cn, is_array($descendants) ? $descendants : []);
    
        $groupBy = [];
        
        if ($quantaty) {
            $q = 'select count(*) as cnt';
        } else {
            if (is_array($fetch)) {
                $q = 'select ' . $this->parseFetch($orm, $fetch, $groupBy);
            } else {
                $q = 'select distinct main.*';
            }
        }
        $aliases = [];
        $joins = [];
        $where = '';
        $counter = 0;
    
        $aliases['main::' . $orm['tn']] = [
            'alias' => 'main',
            'tn' => $orm['tn'],
            'cn' => $orm['cn']
        ];
    
        $this->processAncestors('main', $orm, 'base', 'inner', $aliases, $joins, $counter);
    
        if (is_array($eager) && !empty($eager)) {
            $this->parseEager(
                '',
                'main',
                $orm,
                $eager,
                $aliases,
                $joins,
                isset($descendants) ? $descendants : [],
                $counter
            );
        }
    
        if (is_array($filter) && !empty($filter)) {
            $where = $this->parseFilter('main', $orm, $filter, $aliases, $joins, $counter);
        }
    
        $sorts = [];
        if (is_array($sort) && !$quantaty) {
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
                    $sorts[] = $expr . ' ' . $direction;
                }
            }
        }
    
        $this->processDescendants('main', $orm, $aliases, $joins, $counter);
    
        if (!$quantaty && (!isset($fetch) || empty($fetch))) {
            foreach ($aliases as $alias) {
                if (isset($alias['type'])) {
                    switch ($alias['type']) {
                        case 'base':
                            $eorm = $this->getOrmParams($alias['cn']);
                            $flds = $this->ormFields($eorm);
                            foreach ($flds as $fld) {
                                  $q = $q . ', ' . $alias['alias'] . '.' . $fld;
                            }
                            break;
                        case 'desc':
                            $eorm = $this->getOrmParams($alias['cn']);
                            $flds = $this->ormFields($eorm, false);
                            foreach ($flds as $fld) {
                                if (isset($alias['eagerPrefix'])) {
                                    $q = $q . ', ' . $alias['alias'] . '.' . $fld .
                                         ' as e' . $alias['eagerPrefix'] . '__' . $fld;
                                } else {
                                    $q = $q . ', ' . $alias['alias'] . '.' . $fld;
                                }
                            }
                            if (!$orm['discriminator'] && in_array($alias['cn'], $orm['descendants'])) {
                                $q = $q . ', ' . $alias['alias'] . '.' .
                                     $this->propMeta($eorm, $eorm['key'][0])['fields'][0] .
                                     ' is not null as is_class_' . $eorm['index'];
                            }
                            break;
                        case 'eager':
                            $eorm = $this->getOrmParams($alias['cn']);
                            $flds = $this->ormFields($eorm, true);
                            foreach ($flds as $fld) {
                                $q = $q . ', ' . $alias['alias'] . '.' . $fld .
                                     ' as e' . $alias['eagerPrefix'] . '__' . $fld;
                            }
                            break;
                    }
                }
            }
        }
    
        $q = $q . ' from ' . $orm['tn'] . ' as main ' . implode(' ', $joins);
    
        if ($conditions) {
            $where = $where ? ('(' . $conditions . ') and (' . $where . ')') : $conditions;
        }
    
        if ($where) {
            $q = $q . ' where ' . $where;
        }
        
        if (!empty($groupBy)) {
            $q = $q . ' group by ' . join(', ', $groupBy);
        }
    
        if (!empty($sorts) && !$quantaty) {
            $q = $q . ' order by ' . implode(', ', $sorts);
        }
    
        if (!$quantaty && (isset($offset) && $offset || isset($count) && $count)) {
            $lim = [];
            if (isset($offset) && $offset) {
                $lim[] = $offset;
            }
            if (isset($count) && $count) {
                $lim[] = $count;
            }
            $q = $q . ' limit ' . implode(',', $lim);
        }
        return $q;
    }
  
    private function processData($orm, $data, $cb, $keyData = [])
    {
        $fldValues = [];
        
        $processProperty = function ($prop, $fld) use ($data, &$fldValues, $cb, $keyData) {
            if (array_key_exists($prop, $data) && !$fld['is_array']) {
                $values = [];
                $n = count($fld['fields']);
                if ($fld['is_ref'] && is_array($data[$prop])) {
                    $values = $data[$prop];
                } elseif ($fld['is_ref'] && is_object($data[$prop])) {
                    $values = $this->getObjectKey($data[$prop]);
                    if (!$values) {
                        return;
                    }
                } elseif ($fld['is_ref'] && is_string($data[$prop])) {
                    $values = $this->splitKey($data[$prop]);
                } else {
                    $values[] = $data[$prop];
                }
                $fields = $fld['fields'];
                $n = count($fields);
                $mask = false;
                if (count($values) < $n) {
                    $tmp = array_diff($fld['fields'], $fld['immutables']);
                    $mask = array_keys($tmp);
                    $fields = array_values($tmp);
                    $n = count($fields);
                }
                $values = $this->buildRefValue($fields, $values, $keyData + $fldValues);
                if ($fld['is_ref']) {
                    $values = array_values($this->idToKeyData($fld['type'], $values, $mask));
                } elseif ($n == 1) {
                    $values[0] = $this->castValue($values[0], $fld['type']);
                }
                for ($j = 0; $j < $n; $j++) {
                    if (!array_key_exists($j, $values) && $n > 1) {
                        throw new OrmException(OrmException::NO_DATA_FOR_REF_FIELD, [$fields[$j]]);
                    }
                    if (!array_key_exists($fields[$j], $fldValues)) {
                        $fldValues[$fields[$j]] = $values[$j];
                        $cb($fields[$j], $values[$j]);
                    }
                }
            }
        };
        
        if (isset($orm['parent'])) {
            foreach ($orm['key'] as $kp) {
                if (!isset($orm['map'][$kp])) {
                    $processProperty($kp, $this->propMeta($orm['parent'], $kp));
                }
            }
        }
        
        foreach ($orm['map'] as $prop => $fld) {
            $processProperty($prop, $fld);
        }
    }
  
    private function insert($orm, $data, $cn = null)
    {
        $key_data = [];
        $key = $orm['key'];
    
        $tn = $orm['tn'];
        if (isset($orm['parent'])) {
            $key_data = $this->insert($orm['parent'], $data, $cn ?? $orm['cn']);
            $data = array_merge($data, $this->idToKeyData($orm['cn'], $key_data));
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
    
        $this->processData(
            $orm,
            $data,
            function ($fld, $value) use (&$fields, &$pexpr, &$params, &$record, &$i) {
                $fields[] = $fld;
                $pexpr[] = '?';
                $params[$i] = $value;
                $record[$fld] = $value;
                $i++;
            }
        );
    
        $fields = implode(', ', $fields);
        $pexpr = implode(', ', $pexpr);
    
        $out = [];
        $this->ds->execute("insert into $tn ($fields) values ($pexpr)", $params, $out);
        if (count($key_data) == 0) {
            $kvls = [];
            foreach ($key as $ind => $k) {
                if ($keyprop = $this->propMeta($orm, $k)) {
                    foreach ($keyprop['fields'] as $fld) {
                        if (isset($record[$fld])) {
                            $kvls[$fld] = $record[$fld];
                        } else {
                            if ($out['rowid'] && $ind == 0) {
                                $kvls[$fld] = $out['rowid'];
                            } else {
                                throw new OrmException(OrmException::NO_KEY_DATA, [$orm['cn']]);
                            }
                        }
                    }
                }
            }
            $key_data = array_values($kvls);
        }
    
        return $key_data;
    }
  
    private function update($orm, $id, $data, $with_descs = false)
    {
        $key_data = [];
        $tn = $orm['tn'];
        $count = 0;
        if (isset($orm['parent'])) {
            $count = $this->update($orm['parent'], $id, $data);
        }
        
        if ($with_descs && isset($orm['descendants']) && is_array($orm['descendants'])) {
            foreach ($orm['descendants'] as $desc) {
                $dorm = $this->getOrmParams($desc);
                $this->update($dorm, $id, $data, true);
            }
        }
    
        if ($tn == '_virtual') {
            return $count;
        }
    
        $fields = [];
        $params = [];
    
        $filter = [];
        $id = is_array($id) ? $id : $this->splitKey($id);
        $j = 0;
        
        $kd = $this->idToKeyData($orm['cn'], $id);
        $this->processData(
            $orm,
            $kd,
            function ($fld, $value) use (&$key_data, &$j) {
                $key_data[$fld] = $value;
                $j++;
            }
        );
        
        $i = 1;
                
        $this->processData(
            $orm,
            $data,
            function ($fld, $value) use (&$fields, &$params, &$i) {
                $fields[] = $fld . ' = ?';
                $params[$i] = $value;
                $i++;
            },
            $key_data
        );
        
        if (count($fields) == 0) {
            return $count;
        }
        
        foreach ($key_data as $k => $v) {
            $filter[] = $k . ' = ?';
            $params[$i] = $v;
            $i++;
        }
    
        $fields = implode(', ', $fields);
        $filter = implode(' and ', $filter);
        $out = [];
        $this->ds->execute("update $tn set $fields where $filter", $params, $out);
        $count = $count ? $count : $out['affected'];
        return $count;
    }
  
    private function del($orm, $id, $with_descs = false)
    {
        $tn = $orm['tn'];
        $count = 0;
        if (isset($orm['parent'])) {
            $count = $this->del($orm['parent'], $id);
            if ($count == 0) {
                return $count;
            }
        }
        
        if ($with_descs && isset($orm['descendants']) && is_array($orm['descendants'])) {
            foreach ($orm['descendants'] as $desc) {
                $dorm = $this->getOrmParams($desc);
                $this->del($dorm, $id, true);
            }
        }
    
        if ($tn == '_virtual') {
            return $count;
        }
    
        $filter = [];
        $params = [];
        $i = 0;

        $kd = $this->idToKeyData($orm['cn'], $id);
        $this->processData(
            $orm,
            $kd,
            function ($fld, $value) use (&$filter, &$params, &$i) {
                $filter[] = $fld . ' = ?';
                $params[$i + 1] = $value;
                $i++;
            }
        );
        $filter = implode(' and ', $filter);
        $out = [];
        $this->ds->execute("delete from $tn where $filter", $params, $out);
        return $out['affected'];
    }
  
    /**
     *
     * @param  object $obj
     * @return mixed
     */
    public function key($obj, $multikey_as_string = true)
    {
        if (is_string($obj)) {
            $orm = $this->getOrmParams($obj);
            return $orm['key'];
        } else {
            $kvs = $this->getObjectKey($obj);
            if (count($kvs) == 1) {
                return $kvs[0];
            } elseif ($multikey_as_string && $this->multiFieldIdSeparator) {
                return implode($this->multiFieldIdSeparator, $kvs);
            }
            return $kvs;
        }
    }
    
    private function buildRefValue($keys, $values, $context = [])
    {
        if (count($keys) == count($values)) {
            return $values;
        }
        $result = [];
        $i = 0;
        foreach ($keys as $key) {
            if (array_key_exists($key, $context)) {
                $result[] = $context[$key];
            } elseif (array_key_exists($i, $values)) {
                $result[] = $values[$i];
                $i++;
            }
        }
        return $result;
    }
  
    private function idToKeyData($class, $id, $mask = false)
    {
        $id = is_array($id) ? $id : $this->splitKey($id);
        $orm = $this->getOrmParams($class);
        $n = count($orm['key']);
        $result = [];
        
        $fldnames = [];
        $propmetas = [];
        foreach ($orm['key'] as $i => $prop) {
            if (!$mask || in_array($i, $mask)) {
                $propmeta = $this->propMeta($orm, $prop);
                $propmetas[] = $propmeta;
                foreach ($propmeta['fields'] as $fld) {
                    $fldnames[$fld] = true;
                }
            }
        }
        $fldnames = array_keys($fldnames);
        
        $fldvalues = [];
        
        $ind = 0;
        $context = null;
        $prev = null;
        foreach ($id as $i => $v) {
            if (
                is_null($prev) ||
                (is_array($prev) && !is_array($v)) ||
                (!is_array($prev) && is_array($v))
            ) {
                $context = array_shift($propmetas);
            }
            if (is_array($v)) {
                foreach ($v as $j => $v1) {
                    if (!isset($fldvalues[$context['fields'][$j]])) {
                        $fldvalues[$context['fields'][$j]] = $v1;
                        $ind++;
                    }
                }
            } else {
                $fldvalues[$fldnames[$ind]] = $v;
                $ind++;
            }
            $prev = $v;
        }
        
        for ($i = 0; $i < $n; $i++) {
            if (!$mask || in_array($i, $mask)) {
                $prop = $orm['key'][$i];
                $propmeta = $this->propMeta($orm, $prop);
                $flds = $propmeta['fields'];
                $n2 = count($flds);
                if ($n2 == 1) {
                    $result[$prop] = $this->castValue($fldvalues[$flds[0]] ?? null, $propmeta['type']);
                } else {
                    $ref_id = [];
                    foreach ($flds as $fld) {
                        $ref_id[] = $fldvalues[$fld];
                    }
                    $result[$prop] = $ref_id;
                }
            }
        }
        return $result;
    }
  
    private function splitKey($id)
    {
        if (is_string($id)) {
            return explode($this->multiFieldIdSeparator, $id);
        }
        return is_array($id) ? $id : [$id];
    }
  
    private function castValue($v, $type)
    {
        if ($type === \DateTime::class && !($v instanceof \DateTime)) {
            if (!$v) {
                return null;
            }
            return new DateTime($v, new \DateTimeZone('UTC'));
        }
        switch ($type) {
            case 'bool':
                return is_bool($v) ? $v : boolval($v);
            case 'float':
                return is_float($v) ? $v : (($v === '' || $v === null) ? null : floatval($v));
            case 'int':
                return is_int($v) ? $v : (($v === '' || $v === null) ? null : intval($v));
            default:
                return $v;
        }
    }
  
    private function assignProperties($item, $orm, $obj)
    {
        foreach ($orm['map'] as $p => $prop) {
            if (!$prop['is_array']) {
                if ($prop['is_ref']) {
                    $refObj = new \stdClass();
                    $emptyObj = true;
                    foreach ($obj as $f => $v) {
                        $pref = 'e_' . $prop['index'];
                        if (strpos($f, $pref) === 0) {
                            $f2 = str_replace($pref . '_', '', $f);
                            if ($f2) {
                                if ($f2[0] == '_') {
                                    $f2 = substr($f2, 1);
                                } else {
                                    $f2 = 'e_' . $f2;
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
                        $item->$p = empty($v) ?
                                    null :
                                    (count($v) == 1 ? $v[0] : implode($this->multiFieldIdSeparator, $v));
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
  
    private function w($cn, $orm, $obj)
    {
        if ($orm['discriminator']) {
            $porm = $this->propOrm($orm, $orm['discriminator']);
            if ($porm) {
                $d = $porm['map'][$orm['discriminator']]['fields'][0];
                if (isset($obj->$d) && $obj->$d) {
                    $cn = $obj->$d;
                    $orm = $this->getOrmParams($cn);
                }
            }
        } else {
            $descs = $orm['descendants'];
            foreach ($descs as $desc) {
                $dorm = $this->getOrmParams($desc);
                $chf = 'is_class_' . $dorm['index'];
                if (isset($obj->$chf) && $obj->$chf) {
                    $cn = $desc;
                    $orm = $dorm;
                    break;
                }
            }
        }
    
        if (strpos($cn, '\\') === false) {
            $cn = $this->namespace . $cn;
        }
        return $this->assignProperties(new $cn(), $orm, $obj);
    }
  
    private function cb($item, $options = [])
    {
        return (isset($options['wrapper']) && is_callable($options['wrapper'])) ? $options['wrapper']($item) : $item;
    }
    
    private function fieldTypes($orm, ?array $props = null)
    {
        if (!isset($orm['fieldTypes'])) {
            $orm['fieldTypes'] = [];
            if (isset($orm['parent'])) {
                $orm['fieldTypes'] = array_merge($orm['fieldTypes'], $this->fieldTypes($orm['parent'], $props));
            }
            foreach ($orm['map'] as $nm => $prop) {
                if (empty($props) || isset($props[$nm]) || in_array($nm, $props)) {
                    if ($prop['is_ref']) {
                        $rorm = $this->getOrmParams($prop['type']);
                        $rft = $this->fieldTypes($rorm, $rorm['key']);
                        $keyflds = [];
                        foreach ($rorm['key'] as $kp) {
                            $pm = $this->propMeta($rorm, $kp);
                            $keyflds = array_merge($keyflds, $pm['fields']);
                        }
                
                        foreach ($prop['fields'] as $i => $fld) {
                            if (isset($rft[$keyflds[$i]])) {
                                $orm['fieldTypes'][$fld] = $rft[$keyflds[$i]];
                            }
                        }
                    } elseif (count($prop['fields']) == 1) {
                        $orm['fieldTypes'][$prop['fields'][0]] = $prop['type'];
                    }
                }
            }
        }
        return $orm['fieldTypes'];
    }
  
    /**
     *
     * @param  string $cn
     * @param  array  $data
     * @param  array  $options
     * @return object
     */
    public function create($cn, $data, $options = []): object
    {
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
            $this->commit();
            return $this->getById($cn, $key, $options);
        } catch (\Exception $e) {
            $this->rollback();
            throw new OrmException(OrmException::CREATE_FAILED, [$cn], $e);
        }
    }
  
    /**
     *
     * @param  string $cn
     * @param  mixed  $id
     * @param
     *          array | object $data
     * @param  array  $options
     * @return object
     */
    public function edit($cn, $id, $data, $options = []): ?object
    {
        $orm = $this->getOrmParams($cn, $options['descendants'] ?? null);
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
    
        $this->begin();
        try {
            $affected = $this->update($orm, $this->splitKey($id), $data, true);
            $this->commit();
            if ($affected == 0) {
                return null;
            }
            $idData = $this->idToKeyData($cn, $id);
            $idKeys = array_keys($idData);
            foreach ($idKeys as $prop) {
                if (isset($data[$prop])) {
                    $idData[$prop] = $data[$prop];
                }
            }
            $id2 = implode($this->multiFieldIdSeparator, $this->dataToKey((object)$idData, $cn));
            return $this->getById($cn, $id2, $options);
        } catch (\Exception $e) {
            $this->rollback();
            throw new OrmException(OrmException::UPDATE_FAILED, [$cn], $e);
        }
    }
  
    /**
     *
     * @param  object $obj
     * @param  array  $options
     * @return object
     */
    public function save($obj, $options = []): object
    {
        $cn = get_class($obj);
        $orm = $this->getOrmParams($cn);
        $id = $this->getObjectKey($obj);
    
        $data = get_object_vars($obj);
        $this->begin();
        try {
            $affected = $this->update($orm, $id, $data);
            if ($affected == 0) {
                $id = $this->insert($orm, $data);
            }
            $this->commit();
            return $this->getById($cn, $id, $options);
        } catch (\Exception $e) {
            $this->rollback();
            throw new OrmException(OrmException::SAVE_FAILED, [$cn], $e);
        }
    }
  
    /**
     *
     * @param
     *          string | object $cn
     * @param  string $id
     * @return boolean
     */
    public function delete($cn, $id = null, array $options = []): bool
    {
        if (is_object($cn)) {
            $id = $this->getObjectKey($cn);
            $cn = get_class($cn);
            if (is_array($id) && empty($options)) {
                $options = $id;
            }
        }
        $orm = $this->getOrmParams($cn, $options['descendants'] ?? null);
        if (is_string($id)) {
            $id = array_values($this->idToKeyData($cn, $id));
        }
        $affected = 0;
        $this->begin();
        try {
            $affected = $this->del($orm, $id, true);
            $this->commit();
        } catch (\Exception $e) {
            $this->rollback();
            throw new OrmException(OrmException::DELETE_FAILED, [$cn], $e);
        }
        return $affected > 0;
    }
  
    private function eagerLoadCollections($orm, $baseList, $eager)
    {
        $collections = [];
        foreach ($eager as $p) {
            $path = explode('.', $p);
            if ($p = $this->propMeta($orm, $path[0])) {
                if ($p['is_ref'] && $p['is_array'] && isset($p['backRef']) && $p['backRef']) {
                    if (!isset($collections[$path[0]])) {
                        $corm = $this->getOrmParams($p['type']);
                        $collections[$path[0]] = [
                            'eager' => [],
                            'property' => $p['backRef'],
                            'orm' => $corm,
                            'filter' => $p['filter'] ?? []
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
                $p = $this->propMeta($orm, $pn);
                if ($p && $p['is_ref'] && $p['is_array'] && isset($p['backRef']) && $p['backRef']) {
                    if (!is_array($item->$pn)) {
                        $item->$pn = [];
                    }
                }
            }
            $listMap[implode($this->multiFieldIdSeparator, $key)] = $item;
        }
    
        foreach ($collections as $pn => $c) {
            $corm = $c['orm'];
            
            $filter2 = [];
            $params = [];
            $prmn = 1;
            if (is_array($c['filter'])) {
                foreach ($c['filter'] as $attr => $value) {
                    $fporm = $this->propMeta($corm, $attr);
                    if ($fporm) {
                        $filter2[] = [FO::EQ => [$attr, ':p' . $prmn]];
                        $params['p' . $prmn] = $this->castValue($value, $fporm['type']);
                        $prmn++;
                    }
                }
            }
            
            $coll = [];
            $n = count($this->propMeta($corm, $c['property'])['fields']);
            if ($n == 1) {
                if (count($filter) > 0) {
                    $ff = [
                        FO::IN => [
                            $c['property'],
                            $filter
                        ]
                    ];
                    if (!empty($filter2)) {
                        $ff = [FO::AND => array_merge([$ff], $filter2)];
                    }
                    $coll = $this->fetch(
                        $corm['cn'],
                        [
                            'filter' => $ff,
                            'eager' => $c['eager']
                        ],
                        $params
                    );
                }
            } elseif ($n > 0) {
                $f = [];
                $fetch = false;
                $brp = $this->propMeta($corm, $c['property']);
                foreach ($filter as $key) {
                    $f1 = [];
                    for ($i = 0; $i < $n; $i++) {
                        $fetch = true;
                        $params['p' . $prmn] = $key[$i];
                        $f1[] = 'main.' . $brp['fields'][$i] . ' = :p' . $prmn;
                        $prmn++;
                    }
                    $f[] = '(' . join(' and ', $f1) . ')';
                }
                if ($fetch) {
                    $copts = ['eager' => $c['eager']];
                    if (!empty($filter2)) {
                        $copts['filter'] = [FO::AND => $filter2];
                    }
                    $q = $this->buildSelect($corm['cn'], $copts, false, join(' or ', $f));
                    $coll = $this->qfetch($corm, $q, $copts, $params);
                }
            }
      
            foreach ($coll as $ci) {
                $p = $c['property'];
                $rv = $ci->$p;
                $sk = '';
                if (is_array($rv)) {
                    $sk = implode($this->multiFieldIdSeparator, $rv);
                } elseif (is_a($rv, $corm['cn'])) {
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
    
    private function isInternal(\ReflectionClass $rc)
    {
        if ($rc->isInternal()) {
            return true;
        }
        
        if ($rc->getParentClass()) {
            return $this->isInternal($rc->getParentClass());
        }
        
        return false;
    }
  
    private function processParams($params)
    {
        $result = [];
        foreach ($params as $p => $v) {
            if (is_object($v) && $v) {
                $rc = new \ReflectionClass(get_class($v));
                if (!$this->isInternal($rc)) {
                    $orm = $this->getOrmParams($rc->getName());
                    if ($orm) {
                        $v = $this->getObjectKey($v);
                    }
                }
            }
            
            if (is_array($v)) {
                $n = count($v);
                for ($i = 0; $i < $n; $i++) {
                    $result[$p . '_' . $i] = $v[$i];
                }
            } else {
                $result[$p] = $v;
            }
        }
        return $result;
    }
  
    private function qfetch($orm, $q, $options, array $params = [])
    {
        try {
            $result = [];
            $ft = $this->fieldTypes($orm);
            
            foreach ($orm['descendants'] as $desc) {
                $dorm = $this->getOrmParams($desc);
                $ft = array_merge($ft, $this->fieldTypes($dorm));
            }
            
            $items = $this->ds->query($q, $this->processParams($params), $ft);
            foreach ($items as $item) {
                $result[] = $this->w($orm['cn'], $orm, $item);
            }
      
            if (isset($options['eager']) && is_array($options['eager']) && !empty($options['eager'])) {
                $this->eagerLoadCollections($orm, $result, $options['eager']);
            }
      
            array_walk(
                $result,
                function (&$item) use ($options) {
                    $item = $this->cb($item, $options);
                }
            );
            return $result;
        } catch (\Throwable $e) {
            throw $e;
        }
    }
  
    /**
     *
     * @param  string $cn
     * @param  array  $options
     * @param  array  $params
     * @return array
     */
    public function fetch($cn, array $options = [], array $params = [])
    {
        unset($options['fetch']);
        $orm = $this->getOrmParams($cn, isset($options['descendants']) ? $options['descendants'] : []);
        $q = $this->buildSelect($cn, $options);
        return $this->qfetch($orm, $q, $options, $params);
    }
  
    /**
     *
     * @param  string $cn
     * @param  array  $options
     * @param  array  $params
     * @return array
     */
    public function aggregate($cn, array $options = [], array $params = [])
    {
        unset($options['eager']);
        $q = $this->buildSelect($cn, $options);
        try {
            return $this->ds->query($q, $this->processParams($params));
        } catch (\Throwable $e) {
            throw $e;
        }
    }
  
    /**
     *
     * @param  string $cn
     * @param  array  $options
     * @param  array  $params
     * @return array
     */
    public function count($cn, array $options = [], array $params = []): int
    {
        return intval($this->ds->scalar($this->buildSelect($cn, $options, true), $this->processParams($params)));
    }
  
    /**
     *
     * @param  string $cn
     * @param  array  $options
     * @param  array  $params
     * @return object
     */
    public function get($cn, array $options = [], array $params = []): ?object
    {
        $options['count'] = 1;
        $result = $this->fetch($cn, $options, $params);
        return empty($result) ? null : $result[0];
    }
  
    /**
     *
     * @param  string $cn
     * @param  string $id
     * @param array $options
     * @param array $params
     * @return object
     */
    public function getById($cn, $id, $options = [], $params = []): ?object
    {
        $kd = $this->idToKeyData($cn, $id);
        $filter = [];
        foreach ($kd as $k => $d) {
            if (is_array($d)) {
                $pa = [];
                $jj = 1;
                foreach ($d as $v) {
                    $pn = $k . $jj;
                    $params[$pn] = $v;
                    $pa[] = ':' . $pn;
                    $jj++;
                }
                $filter[] = [FO::EQ => [$k, $pa]];
            } else {
                $params[$k] = $d;
                $filter[] = [FO::EQ => [$k, ':' . $k]];
            }
        }
        
        $n = count($filter);
        
        if ($n == 0) {
            throw new OrmException(
                OrmException::INVALID_ID,
                [(is_array($id) ? implode($this->multiFieldIdSeparator, $id) : $id), $cn]
            );
        }
        
        if (isset($options['filter']) && is_array($options['filter'])) {
            $filter[] = $options['filter'];
            $n++;
        }
        
        if ($n > 1) {
            $options['filter'] = [FO::AND => $filter];
        } elseif ($n == 1) {
            $options['filter'] = $filter[0];
        }
        $options['count'] = 1;
        $result = $this->fetch($cn, $options, $params);
        return empty($result) ? null : $result[0];
    }
    
    private function wrapCursor(DbCursor $cursor, callable $wrapper): \Iterator
    {
        return new class ($cursor, $wrapper) implements \Iterator
        {
            
            /**
             *
             * @var DbCursor $cursor
             */
            private $cursor;
            
            public function __construct(DbCursor $cursor, callable $wrapper)
            {
                $this->wrapper = $wrapper;
                $this->cursor = $cursor;
            }
            
            public function current()
            {
                $cur = $this->cursor->current();
                if (!$cur) {
                    return $cur;
                }
                return ($this->wrapper)($cur);
            }
            
            public function key()
            {
                return $this->cursor->key();
            }
            
            public function next()
            {
                return $this->cursor->next();
            }
            
            public function rewind()
            {
                $this->cursor->rewind();
            }
            
            public function valid()
            {
                return $this->cursor->valid();
            }
        };
    }
  
    /**
     *
     * @param  string $cn
     * @param  array  $options
     * @param  array  $params
     * @return \Traversable
     */
    public function iterate($cn, array $options = [], array $params = []): \Traversable
    {
        $orm = $this->getOrmParams($cn, isset($options['descendants']) ? $options['descendants'] : []);
        $ft = $this->fieldTypes($orm);
        
        foreach ($orm['descendants'] as $desc) {
            $dorm = $this->getOrmParams($desc);
            $ft = array_merge($ft, $this->fieldTypes($dorm));
        }
        
        return $this->wrapCursor(
            $this->ds->iterate($this->buildSelect($cn, $options), $this->processParams($params), $ft),
            function ($obj) use ($options, $cn, $orm) {
                $result = $this->w($cn, $orm, $obj);
                if (isset($options['eager']) && is_array($options['eager']) && !empty($options['eager'])) {
                    $this->eagerLoadCollections($orm, [$result], $options['eager']);
                }
                return $this->cb($result, $options);
            }
        );
    }
    
    private function collectTypes(object $result, array $orm)
    {
        if (isset($orm['parent'])) {
            $this->collectTypes($result, $orm['parent']);
        }
        foreach ($orm['map'] as $pn => $prop) {
            $result->$pn = new \stdClass();
            $result->$pn->type = $prop['type'];
            $result->$pn->ref = $prop['is_ref'];
            $result->$pn->collection = $prop['is_array'];
        }
    }
  
    public function attrTypes(object $obj): object
    {
        $result = new \stdClass();
        $orm = $this->getOrmParams(get_class($obj));
        $this->collectTypes($result, $orm);
        return $result;
    }
    
    private function collectLazyLoaders(object $result, array $orm, object $obj)
    {
        if (isset($orm['parent'])) {
            $this->collectLazyLoaders($result, $orm['parent'], $obj);
        }
        
        foreach ($orm['map'] as $pn => $prop) {
            if ($prop['is_ref']) {
                $result->$pn = $prop['is_array'] ? function () use ($obj, $pn, $prop) {
                    $rorm = $this->getOrmParams($prop['type']);
                    $id = $this->getObjectKey($obj);
                    $params = [
                        'id' => $id
                    ];
                    $options = [
                        'filter' => [
                            FO::EQ => [
                                $prop['backRef'],
                                ':id'
                            ]
                        ]
                    ];
                    return $this->fetch($rorm['cn'], $options, $params);
                } : function () use ($obj, $pn, $prop) {
                    $rorm = $this->getOrmParams($prop['type']);
                    $options = [
                        'count' => 1
                    ];
                    $refv = $obj->$pn;
                    $n = count($rorm['key']);
                    $params = [];
                    if ($n == 1) {
                        $options['filter'] = [
                            FO::EQ => [
                                $rorm['key'][0],
                                ':id'
                            ]
                        ];
                        $params['id'] = $refv;
                    } else {
                        $conditions = [];
                        for ($i = 0; $i < $n; $i++) {
                            $conditions[] = [
                                FO::EQ => [
                                    $rorm['key'][$i],
                                    $refv[$i]
                                ]
                            ];
                        }
                        $options['filter'] = [
                            FO::AND => $conditions
                        ];
                    }
                    $refs = $this->fetch($rorm['cn'], $options, $params);
                    return (!empty($refs)) ? $refs[0] : null;
                };
            }
        }
    }
  
    public function lazyLoaders(object $obj, array $options = []): object
    {
        $result = new \stdClass();
        $orm = $this->getOrmParams(get_class($obj), $options['descendants'] ?? null);
        $this->collectLazyLoaders($result, $orm, $obj);
        return $result;
    }
}
