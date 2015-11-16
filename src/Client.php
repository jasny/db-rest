<?php

namespace Jasny\DB\REST;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;

use Jasny\DB\Connection;

/**
 * Instances of this class are used to interact with the RESTfull data source.
 * 
 * <code>
 *   $db = new Jasny\DB\REST\Client('http://api.example.com');
 *   $fooSet = $db->get('/foo/', ['parse' => 'application/json']);
 *   $foo = $db->get('/foo/:id', ['query' => ['id' => 10], 'parse' => 'application/json']);
 * </code>
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-rest/master/LICENSE MIT
 * @link    https://jasny.github.com/db-rest
 */
class Client extends \GuzzleHttp\Client implements Connection, Connection\Namable
{
    use Connection\Namable\Implemention;
    
    /**
     * The response of the last request
     * @var Response
     */
    protected $lastResponse;
    
    /**
     * Class constructor
     * 
     * @param array|string $config  Settings or base uri
     */
    public function __construct($config)
    {
        if (is_string($config)) $config = ['base_uri' => $config];
        
        if (!isset($config['handler'])) $config['handler'] = HandlerStack::create();
        $config['handler']->unshift($this->bindUriMiddleware(), 'bind_uri');
        $config['handler']->unshift($this->lastResponseMiddleware(), 'last_response');
        $config['handler']->unshift($this->parseMiddleware(), 'parse');
        
        parent::__construct($config);
    }
    
    
    /**
     * Add filter to request paramaters
     * 
     * @param array $query
     * @param array $filter
     */
    public static function addFilterToQuery(array &$query, $filter)
    {
        $query += $filter;
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param array        $query
     * @param string|array $sort
     */
    public static function addSortToQuery(array &$query, $sort)
    {
        $query['sort'] = $sort;
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param array $query
     * @param array $limit
     */
    public static function addLimitToQuery(array &$query, $limit)
    {
        list($limit, $offset) = (array)$limit + [null, null];
        if (!empty($limit)) $query['limit'] = $limit;
        if (!empty($offset)) $query['offset'] = $offset;
    }
    
    
    /**
     * Bind placeholders in the URI
     * 
     * @return \Closure
     */
    protected function bindUriMiddleware()
    {
        return function (callable $handler) {
            return function (Request $request, array $options) use ($handler) {
                $tplPath = $request->getUri()->getPath();
                
                $query = [];
                parse_str($request->getUri()->getQuery(), $query);
                
                list($contentType) = preg_replace('/\s*;.*/', '', $request->getHeader('Content-Type')) + [null];
                if ($contentType === 'multipart/form-data') {
                    $data = static::parseMultipartFormData((string)$request->getBody());
                } elseif ($contentType === 'application/json') {
                    $data = json_decode((string)$request->getBody());
                }
                
                $url = static::bindUri($tplPath, $query, isset($data) ? $data : null);
                $uri = $request->getUri()
                    ->withPath(parse_url($url, PHP_URL_PATH))
                    ->withQuery(parse_url($url, PHP_URL_QUERY) ?: '');
                
                return $handler($request->withUri($uri), $options);
            };
        };
    }
    
    /**
     * Build a full URI by binding the placeholders
     * 
     * @param string       $uri    URI, with placeholders
     * @param array        $query  Query paramaters
     * @param array|object $data   Body data; used for placeholders but not in uri
     * @return string
     */
    public static function bindUri($uri, $query = [], $data = [])
    {
        $query = (array)$query;
        if ($data instanceof \JsonSerializable) $data = $data->jsonSerialize();
        
        $parts = explode('/', ltrim($uri, '/'));
        $params = $query + (array)$data;
        $missing = null;

        foreach ($parts as $i => $part) {
            if (!$part) continue;
            
            if ($part[0] !== ':') {
                if (!isset($params)) throw new \Exception("Missing required parameter '$missing'");
                continue;
            }
            
            $key = substr($part, 1);
            
            if (isset($params) && !isset($params[$key])) {
                $missing = substr($part, 1);
                $params = null; // If one param is missing, the rest will be used as query params
            }
            
            if (isset($params)) {
                $parts[$i] = $params[$key];
                unset($query[$key]);
            } else {
                $parts[$i] = '';
            }
        }
        
        $path = preg_replace('~//+~', '/', '/' . join('/', $parts));
        return $path . ($query ? '?' . http_build_query($query) : '');
    }
    
    
    /**
     * Middleware for keeping the HTTP response of the last request.
     * Usefull in combination with the `parse` option.
     * 
     * @return \Closure
     */
    protected function lastResponseMiddleware()
    {
        return function (callable $handler) {
            return function (Request $request, array $options) use ($handler) {
                $promise = $handler($request, $options);
                
                return $promise->then(function(Response $response) {
                    $this->lastResponse = $response;
                    return $response;
                });
            };
        };
    }
    
    /**
     * Get the response of the last request
     * 
     * @return Response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }
    
    
    /**
     * Middleware for parsing the HTTP response
     * 
     * @return \Closure
     */
    protected function parseMiddleware()
    {
        return function (callable $handler) {
            return function (Request $request, array $options) use ($handler) {
                // Skip if option 'parse' isn't set
                if (empty($options['parse'])) return $handler($request, $options);

                $options['http_errors'] = false;
                $request = $request->withHeader('Accept', "{$options['parse']}; q=1.0, text/plain; q=0.5");
                
                $promise = $handler($request, $options);
                
                return $promise->then(function(Response $response) use ($request) {
                    return $this->parseResponse($request, $response);
                });
            };
        };
    }
    
    /**
     * Parse the HTTP response
     * 
     * @param Request  $request
     * @param Response $response
     * @return \stdClass
     */
    protected function parseResponse(Request $request, Response $response)
    {
        list($accept) = preg_replace('/\s*[,;].*/', '', $request->getHeader('Accept'));
        list($contentType) = preg_replace('/\s*;.*$/', '', $response->getHeader('Content-Type')) + [null];
        $status = $response->getStatusCode();
        
        // Only JSON is currently supported
        if ($accept !== 'application/json') {
            throw new \Exception("Parsing is only supported for 'application/json', not '{$accept}'");
        }
        
        if (!isset($contentType)) {
            trigger_error("Server response doesn't specify the content type, assuming {$accept}", E_USER_NOTICE);
            $contentType = $accept;
        }

        if (!in_array($contentType, [$accept, 'text/plain'])) {
            $message = "Server responded with '$contentType', while expecting '$accept'";
            throw new InvalidContentException($message, $request, $response);
        }
        
        // Not found
        if ($status === 404) return null;
        
        if ($contentType === 'text/plain') {
            $message = $response->getBody();
        } else {
            $data = json_decode($response->getBody());
            if ($status >= 400) $message = static::extractErrorMessage($data) ?: $response->getBody();
        }
        
        // Client or server error
        if ($status >= 400 && $status < 500) throw new ClientException($message, $request, $response);
        if ($status >= 500) throw new ServerException($message, $request, $response);
        
        // Successful
        if (!isset($data)) {
            $message = "Corrupt JSON response: " . json_last_error_msg();
            throw new InvalidContentException($message, $request, $response);
        }
        
        return $data;
    }
    
    /**
     * Extract error message from response.
     * Note: This function isn't called if error responses are text/plain
     * 
     * @param object|mixed $data
     * @return string
     */
    protected static function extractErrorMessage($data)
    {
        if (is_string($data)) return $data;
        if (isset($data->message)) return $data->message;
        
        if (isset($data->error)) {
            return isset($data->error->message) ? $data->error->message : $data->error;
        }

        trigger_error("Failed to extract error message from response", E_USER_NOTICE);
        return null;
    }
    
    /**
     * Parse a multipart/formdata
     * 
     * @param string $body
     * @return array
     */
    protected static function parseMultipartFormData($body)
    {
        $delimiter = substr($body, 0, strpos($body, "\r\n"));
        $matches = null;
        
        if (!preg_match_all(
            '/\r\nContent-Disposition: form-data; name="([^"]+)"\r\nContent-Length: \d+\r\n\r\n(.*?)\r\n' . 
            preg_quote($delimiter, '/') . '/s',
            $body,
            $matches
        ));
        
        return array_combine($matches[1], $matches[2]);
    }
}
