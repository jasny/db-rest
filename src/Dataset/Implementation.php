<?php

namespace Jasny\DB\REST\Dataset;

use Jasny\DB\REST\Client;
use Jasny\DB\EntitySet;
use Jasny\DB\Entity;

use GuzzleHttp\Exception\RequestException;
use Doctrine\Common\Inflector\Inflector;

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
        $db = \Jasny\DB::conn();
        if (!$db instanceof Client) throw new Exception("Default connection isn't a REST client");
        
        return $db;
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
     * Get method for checking is an entity exists.
     * Should be either GET or HEAD
     * 
     * @return string
     */
    protected static function getExistsMethod()
    {
        return 'GET';
    }
    
    /**
     * Create an entity set
     * 
     * @param Entities[]|\Traversable $entities  Array of entities
     * @param int|callback            $totals
     * @param int                     $flags
     * @return EntitySet
     */
    public static function entitySet($entities = [], $totals = null, $flags = 0)
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
        return '/' . strtr(Inflector::tableize($plural), '_', '-') . '/:id';
    }
    
    
    /**
     * Add filter to request query
     * 
     * @param array $filter
     * @return array
     */
    protected static function filterToQuery($filter)
    {
        return (array)static::getDB()->filterToQuery($filter);
    }
    
    /**
     * Add filter to request query
     * 
     * @param string|array $sort
     * @return array
     */
    protected static function sortToQuery($sort)
    {
        return (array)static::getDB()->sortToQuery($sort);
    }
    
    /**
     * Add filter to request query
     * 
     * @param int|array $limit
     * @return array
     */
    protected static function limitToQuery($limit)
    {
        return (array)static::getDB()->limitToQuery($limit);
    }

    
    /**
     * Fetch a document.
     * 
     * @param string|array $id   ID or filter
     * @param array        $opts
     * @return static|\Jasny\DB\Entity
     */
    public static function fetch($id, array $opts = [])
    {
        $filter = is_array($id) ? $id : static::idToFilter($id);
        $query = static::filterToQuery($filter);
        
        $opts += ['parse' => 'application/json', 'expected_type' => 'object'];
        
        $data = static::getDB()->get(static::getUri(), compact('query') + $opts);
        
        if (!is_object($data)) return $data; // Either null or 'expected_type' is overwritten
        
        $class = static::getResourceClass();
        return $class::fromData($data);
    }
    
    /**
     * Check if a document exists.
     * 
     * {@internal It would be nice to do a HEAD requests here, but APIs often don't support it}}
     * 
     * @param string|array $id   ID or filter
     * @param array        $opts
     * @return boolean
     */
    public static function exists($id, array $opts = [])
    {
        $filter = is_array($id) ? $id : static::idToFilter($id);
        $query = static::filterToQuery($filter);
        
        $opts['parse'] = false;
        $opts['http_errors'] = false;
        
        $method = static::getExistsMethod();
        $response = static::getDB()->request($method, static::getUri(), compact('query') + $opts);
        
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400 && $statusCode != 404) {
            $request = static::getDB()->createRequest($method, static::getUri(), $query);
            throw RequestException::create($request, $response);
        }
        
        return $statusCode < 300; // Any 2xx code
    }

    
    /**
     * Fetch all documents.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @return static[]
     */
    public static function fetchAll(array $filter = [], $sort = [], $limit = null, array $opts = [])
    {
        $query = static::sortToQuery($sort) + static::limitToQuery($limit) + static::filterToQuery($filter);
        $opts += ['parse' => 'application/json', 'expected_type' => 'array'];
        
        $uri = static::getUri();
        $data = static::getDB()->get($uri, compact('query') + $opts);
        
        return is_array($data) ? static::entitySet($data) : $data;
    }
    
    /**
     * Fetch ids and descriptions as key/value pairs.
     * 
     * @param array     $filter
     * @param array     $sort
     * @param int|array $limit  Limit or [limit, offset]
     * @return array
     */
    public static function fetchPairs(array $filter = array(), $sort = null, $limit = null, array $opts = [])
    {
        if (!is_a(static::getResourceClass(), Entity\Identifiable::class, true)) {
            throw new \LogicException(static::getResourceClass() . " entities aren't identifiable");
        }
        
        if (!method_exists(static::getResourceClass(), '__toString')) {
            throw new \LogicException(static::getResourceClass() . " entities can't be casted to a string");
        }
        
        $entities = static::fetchAll($filter, $sort, $limit, $opts);
        $pairs = [];
        
        foreach ($entities as $entity) {
            $pairs[$entity->getId()] = (string)$entity;
        }
        
        return $pairs;
    }

    /**
     * Count all resources
     * 
     * @param array $filter
     * @return int
     */
    public static function count(array $filter = [], array $opts = [])
    {
        $entities = static::fetchAll($filter, null, null, $opts);
        return count($entities);
    }
}
