<?php

namespace Jasny\DB\REST\Dataset;

use Jasny\DB\REST\Client,
    Jasny\DB\REST\UnexpectedContentException,
    Jasny\DB\EntitySet,
    Doctrine\Common\Inflector\Inflector;

/**
 * Static methods to interact with a collection of resources
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait Implementation
{
    /**
     * Get the database connection
     * 
     * @return Client
     */
    protected static function getDB()
    {
        return \Jasny\DB::conn();
    }
    
    /**
     * Get the document class
     * 
     * @return string
     */
    protected static function getResourceClass()
    {
        return get_called_class();
    }
    
    
    /**
     * Create an entity set
     * 
     * @param array        $entities
     * @param int|callback $totals
     * @param int          $flags
     * @return EntitySet
     */
    public static function entitySet(array $entities = [], $totals = 0, $flags = 0)
    {
        return new EntitySet(static::getResourceClass(), $entities, $totals, $flags);
    }
    
    /**
     * Get the URI for this collection (with placeholders)
     * 
     * @return string
     */
    protected static function getUri()
    {
        if (isset(static::$uri)) return static::$uri;

        // Guess URI
        $class = preg_replace('/^.+\\\\/', '', static::getResourceClass());
        $plural = Inflector::pluralize($class);
        return Inflector::tableize($plural) . '/:id';
    }
    
    
    /**
     * Fetch a document.
     * 
     * @param string|array $id  ID or filter
     * @return static|\Jasny\DB\Entity
     */
    public static function fetch($id)
    {
        $filter = is_array($id) ? $id : static::idToFilter($id);
        $data = static::db()->get(static::getUri(), $filter);
        
        if (isset($data) && !is_array($data)) {
            throw new UnexpectedContentException("Was expecting an object for $uri, but got a " . gettype($data));
        }
        
        $class = static::getResourceClass();
        return $class::fromData($data);
    }
    
    /**
     * Check if a document exists.
     * 
     * @param string|array $id  ID or filter
     * @return boolean
     */
    public static function exists($id)
    {
        $filter = is_array($id) ? $id : static::idToFilter($id);
        $data = static::db()->get(static::getUri(), $filter);
        
        return isset($data);
    }
    
    /**
     * Fetch all documents.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @return static[]
     */
    public static function fetchAll(array $filter = [], $sort = [], $limit = null)
    {
        $params = $filter;
        
        $uri = static::getUri($params);
        $data = $this->db()->get($uri);
        
        if (!is_array($data)) {
            throw new UnexpectedContentException("Was expecting an array for $uri, but got a " . gettype($data));
        }
        
        return static::entitySet();
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param array $params
     * @param array $filter
     */
    protected static function addFilterParams(array &$params, $filter)
    {
        Client::addFilterParams($params, $filter);
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param array        $params
     * @param string|array $sort
     */
    protected static function addSortParams(array &$params, $sort)
    {
        Client::addSortParams($params, $sort);
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param array     $params
     * @param int|array $limit
     */
    protected static function addLimitParams(array &$params, $limit)
    {
        Client::addLimitParams($params, $limit);
    }
    
    /**
     * Fetch all descriptions.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @return array
     */
    public static function fetchList(array $filter = [], $sort = [], $limit = null)
    {
        $list = [];
        foreach (static::fetchAll($filter, $sort, $limit) as $record) {
            $list[$record->getId()] = (string)$record;
        }
        
        return $list;
    }

    /**
     * Count all documents in the collection
     * 
     * @param array $filter
     * @return int
     */
    public static function count(array $filter = [])
    {
        $query = static::filterToQuery($filter);
        return static::getCollection()->count($query);
    }
}
