<?php

namespace Jasny\DB\REST\Dataset;

use Jasny\DB\Entity;
use Jasny\DB\REST\Dataset\Implementation;

/**
 * Stub for Jasny\DB\REST\Resource\BasicImplementation tests
 */
class ImplementationStub implements Entity, Entity\Identifiable
{
    use Implementation {
        Implementation::getDB as protected _getDB;
    }

    public static $db;
    public static $uri;

    public $foo;
    
    protected static function getDB()
    {
        return static::$db ?: static::_getDB();
    }
    
    protected static function idToFilter($id)
    {
        return ['foo' => $id];
    }
    
    public static function getIdProperty()
    {
        return 'foo';
    }
    
    public function getId()
    {
        return $this->foo;
    }
    
    public function __toString()
    {
        return 'hello ' . $this->foo;
    }

    public function setValues($values)
    {
        return $this;
    }

    public function getValues()
    {
    }
    
    public static function fromData($values)
    {
        $values = (array)$values;
        
        $entity = new self();
        if (isset($values['foo'])) $entity->foo = $values['foo'];
        
        return $entity;
    }
    
    public function toData()
    {
    }
    
    public function jsonSerialize()
    {
    }
}
