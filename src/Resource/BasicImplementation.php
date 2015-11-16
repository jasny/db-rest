<?php

namespace Jasny\DB\REST\Resource;

use Jasny\DB\FieldMapping,
    Jasny\DB\Entity,
    Jasny\DB\REST\Sorted;

/**
 * Static methods to interact with a dataset (as resource)
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait Basics
{
    use Entity\Basics;
    
    /**
     * Get the data that needs to be saved in the DB
     * 
     * @return array
     */
    protected function toData()
    {
        $values = $this->getValues();
        if ($this instanceof FieldMapping) $values = static::mapToFields($values);
        
        return $values;
    }
    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function save()
    {
        if (!$this->_id instanceof \RESTId) $this->_id = new \RESTId($this->_id);
        if ($this instanceof Sorted && method_exists($this, 'prepareSort')) $this->prepareSort();
        
        static::getCollection()->save($this->toData());
        return $this;
    }
    
    /**
     * Delete the document
     * 
     * @return $this
     */
    public function delete()
    {
        static::getCollection()->remove(['_id' => $this->_id]);
        return $this;
    }

    /**
     * Check no other document with the same value of the property exists
     * 
     * @param string $property
     * @return boolean
     */
    public function hasUnique($property)
    {
        if (!isset($this->$property)) return true;
        return !static::exists(['_id(not)' => $this->_id, $property => $this->$property]);
    }
    
    
    /**
     * Prepare result when casting object to JSON
     * 
     * @return object
     */
    public function jsonSerialize()
    {
        $this->expand();
        
        $values = $this->getValues();
        
        foreach ($values as &$value) {
            if ($value instanceof \DateTime) $value = $value->format(\DateTime::ISO8601);
            if ($value instanceof \RESTId) $value = (string)$value;
        }
        
        return (object)$values;
    }
    
    /**
     * Convert loaded values to an entity
     * 
     * @param array $values
     * @return static
     */
    public static function fromData($values)
    {
        if (is_a(get_called_class(), 'Jasny\DB\FieldMapping', true)) $values = static::mapFromFields($values);
        return static::entityFromData($values);
    }
}
