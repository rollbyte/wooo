<?php
namespace wooo\tests\orm;

/**
 * @author krasilneg
 * @orm.key Master
 * @orm.key Detail
 */
class ClassE {
    /**
     * @var ClassD
     * @orm.field code
     */
    public $Dref;
    
    /**
     * @var ClassA
     * @orm.field code
     * @orm.field master
     * @orm.immutable code
     */
    public $Master;
    
    /**
     * @var ClassA
     * @orm.field code
     * @orm.field detail
     * @orm.immutable code
     */
    public $Detail;
}