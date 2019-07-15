<?php
namespace wooo\tests\orm;

/**
 * @author krasilneg
 */
class ClassB extends ClassA {
    /**
     * @var int
     */
    public $IntAttr;
    
    /**
     * @var float
     */
    public $FloatAttr;
    
    /**
     * @var bool
     */
    public $BoolAttr;
    
    /**
     * @var ClassE[]
     * @orm.backRef Master
     */
    public $Ecol;
}