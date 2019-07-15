<?php
namespace wooo\tests\orm;

/**
 * @author krasilneg
 * @orm.key Code
 */
class ClassC {    
    /**
     * @var string
     */
    public $Code;
    
    /**
     * @var string
     */
    public $Name;
    
    /**
     * @var ClassD
     * @orm.field d
     */
    public $Dref;

    /**
     * @var ClassA
     * @orm.field d
     * @orm.field a
     * @orm.immutable d
     */
    public $Aref;
}