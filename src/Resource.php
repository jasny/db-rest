<?php

namespace Jasny\DB\REST;

use Jasny\DB\Entity,
    Jasny\Meta\Introspection,
    Jasny\Meta\TypedObject,
    Jasny\DB\FieldMapping,
    Jasny\DB\FieldMap,
    Jansy\DB\REST\Dataset;
/**
 * Base class for REST Resources
 */
class Resource implements
    Resource\ActiveRecord,
    Entity\LazyLoading,
    Sorted,
    Introspection,
    TypedObject,
    FieldMapping
{
    use Resource\MetaImplementation,
        Resource\LazyLoading,
        Dataset\Implementation,
        FieldMap,
        Entity\Meta
    {
        Resource\Basics::setValues as private _setValues;
        Resource\Basics::save as private _save;
        Resource\Basics::jsonSerialize as private _jsonSerialize;
        Resource\LazyLoading::lazyload as private _lazyload;
        Entity\Meta::getIdentityField as private getIdentityFieldFromMeta;
    }
    
    /**
     * Class constructor
     */
    public function __construct()
    {
        if (!$this->_id instanceof \RESTId) $this->_id = new \RESTId($this->_id);
        $this->cast();
    }
    
    /**
     * Get the field map.
     * 
     * @return array
     */
    protected static function getFieldMap()
    {
        return [];
    }
    
    /**
     * Create a ghost object.
     * 
     * @param mixed|array $values  Unique ID or values
     * @return static
     */
    public static function lazyload($values)
    {
        return static::_lazyload($values)->cast();
    }
    
    /**
     * Get the id value
     * 
     * @return string|int|array
     */
    public function getId()
    {
        $prop = static::getIdProperty();

        if (is_array($prop)) {
            $id = [];
            foreach ($prop as $key) {
                $id[$key] = $this->$key;
            }
            
            return $id;
        }
        
        return $this->$prop;
    }
    
    /**
     * Get the identity property
     * 
     * @return string|array
     */
    public static function getIdProperty()
    {
        $prop = static::getIdPropertyFromMeta();
        if (!isset($prop) && property_exists(get_called_class(), 'id')) $prop = 'id';
        
        return $prop;
    }
    
    /**
     * Set the values.
     * 
     * @param array|object $values
     * @return $this
     */
    public function setValues($values)
    {
        if (isset($values['id'])) $values['_id'] = $values['id'];
        
        return $this->_setValues($values)->cast();
    }
    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function save()
    {
        $this->cast();
        return $this->_save();
    }

    
    /**
     * Get the field to sort on
     * 
     * @return string
     */
    public static function getDefaultSorting()
    {
        return property_exists(__CLASS__, '_sort') ? ['_sort' => DB::ASCENDING] : [];
    }
    
    /**
     * Prepare sorting field
     */
    protected function prepareSort()
    {
        if (property_exists($this, '_sort')) {
            $this->_sort = strtolower(iconv("UTF-8", "ASCII//TRANSLIT", (string)$this));
        }
    }
    
    
    /**
     * Prepare object for json
     * 
     * @return object
     */
    public function jsonSerialize()
    {
        $object = (object)(['id' => null] + (array)$this->_jsonSerialize());
        $object->id = $object->_id;
        unset($object->_id);
        
        return $object;
    }
    
    /**
     * Clear property for each child.
     * 
     * @param array        $list
     * @param string|array $prop
     */
    protected function jsonSerializeUnsetIn(&$list, $prop)
    {
        foreach ($list as &$item) {
            $item = $item instanceof \JsonSerializable ? $item->jsonSerialize() : clone $item;
            
            foreach ((array)$prop as $p) {
                if (isset($item->$p)) unset($item->$p);
            }
        }
    }
}
