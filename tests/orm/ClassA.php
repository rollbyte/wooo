<?php
namespace wooo\tests\orm;

/**
 * @author krasilneg
 * @orm.key Dref
 * @orm.key Id
 * @orm.discriminator ClassName
 */
class ClassA {
    /**
     * @var ClassD
     * @orm.field d
     */
    public $Dref;
    
    /**
     * @var string
     */
    public $Id;
    
    /**
     * @var string
     */
    public $ClassName;
    
    /**
     * @var string
     * @orm.required
     */
    public $Name;
    
    /**
     * @var \DateTime
     */
    public $DateAttr;
    
    /**
     * @var ClassC[]
     * @orm.backRef Aref
     */
    public $Ccol;
    
    /**
     * @var ClassA
     * @orm.field d
     * @orm.field a
     * @orm.immutable d
     */
    public $Aref;
}