<?php

namespace Jasny\DB\REST\Resource;

use Jasny\DB\Entity;
use Jasny\DB\FieldMapping;
use Jasny\DB\Dataset;

/**
 * Stub for Jasny\DB\REST\Resource\BasicImplementation tests
 */
class BasicImplementationStub implements
    Entity,
    Entity\Identifiable,
    Entity\SelfAware,
    Entity\ChangeAware,
    Dataset\Sorted,
    FieldMapping
{
    use BasicImplementation;
    
    public static $db;
    public static $map = [];

    public $id;
    public $title;
    public $status;

    public function getId()
    {
        return $this->id;
    }

    public function hasModified($property)
    {
    }

    public function hasUnique($property, $group = null, array $opts = array())
    {
    }

    public function isModified()
    {
    }

    public function isNew()
    {
    }

    public function reload(array $opts = array())
    {
    }
    
    public function markAsPersisted()
    {
    }

    public static function getDB()
    {
        return static::$db;
    }

    public static function getIdProperty()
    {
        return 'id';
    }

    public static function mapFromFields(array $values)
    {
        foreach (static::$map as $field => $prop) {
            if (isset($values[$field])) {
                $values[$prop] = $values[$field];
                unset($values[$field]);
            }
        }
        
        return $values;
    }

    public static function mapToFields(array $values)
    {
        foreach (array_flip(static::$map) as $prop => $field) {
            if (isset($values[$prop])) {
                $values[$field] = $values[$prop];
                unset($values[$prop]);
            }
        }
        
        return $values;
    }

    public static function getDefaultSorting()
    {
        return 'title';
    }
}
