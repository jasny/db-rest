<?php

namespace Jasny\DB\REST\Resource;

use Jasny\DB\Entity;
use Jasny\DB\FieldMapping;
use Jasny\DB\REST\Sorted;

/**
 * Static methods to interact with a dataset (as resource)
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait BasicImplementation
{
    use Entity\Implementation;

    /**
     * Get the HTTP method for saving the resource
     * 
     * @return string
     */
    protected function getSaveMethod()
    {
        return 'PUT';
    }

    /**
     * Update properties from data
     * 
     * @param array $data
     */
    protected function updateFromData($data)
    {
        if ($this instanceof FieldMapping) {
            $data = $this->mapFromFields($data);
        }
        
        if ($this instanceof Entity\Identifiable) {
            $prop = static::getIdProperty();
            
            if (!isset($data[$prop])) {
                $class = get_called_class();
                trigger_error("Response data can't be identified as this $class resource", E_USER_NOTICE);
                return;
            }
            
            if ($this->getId() && $data[$prop] !== $this->getId()) {
                throw new \Exception("Response has $prop '{$data[$prop]}', while " .
                    preg_replace('/^.*\\\\/', '', get_called_class()) . " has $prop '" . $this->getId() . "'");
            }
        }
        
        // Using closure to prevent setting protected methods
        $set = function($entity) use ($data) {
            foreach ($data as $key=>$value) {
                $entity->$key = $value;
            }
        };
        $set->bindTo(null);

        $set($this);
        
        if ($this instanceof Entity\ChangeAware && method_exists($this, 'markAsPersisted')) {
            $this->markAsPersisted();
        }
    }
    
    /**
     * Save the resource.
     * 
     * @param array $opts  Options are passed to the request
     * @return $this
     */
    public function save(array $opts = [])
    {
        if ($this instanceof Sorted && method_exists($this, 'prepareSort')) $this->prepareSort();
        
        $opts['data'] = $this->toData();
        $opts['parse'] = 'application/json';
        
        $result = static::getDB()->request($this->getSaveMethod(), static::getUri(), $opts);
        if ($result) $this->updateFromData($result);
        
        return $this;
    }
    
    /**
     * Delete the resource
     * 
     * @return $this
     */
    public function delete(array $opts = [])
    {
        if (!$this instanceof Entity\Identifiable) {
            throw new Exception("Unable to delete. " . get_called_class() . " isn't identifiable");
        }
        
        static::getDB()->delete(static::getUri(), $opts);

        return $this;
    }
}
