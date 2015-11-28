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
 *   $db = new Jasny\DB\REST\Client('http://api.example.com', ['headers' => ['Content-Type' => 'application/json']]);
 *   $fooSet = $db->get('/foo/', ['parse' => 'application/json']);
 * 
 *   $foo = $db->get('/foo/:id', ['query' => ['id' => 10], 'parse' => 'application/json']);
 *   $foo->title = 'Hello World';
 *   $foo->save();
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
     * @param string $baseUri (may be omitted)
     * @param array  $config  Settings
     */
    public function __construct($baseUri, array $config = [])
    {
        if (is_string($baseUri)) {
            $config['base_uri'] = $baseUri;
        } else {
            $config = $baseUri;
        }
        
        if (!isset($config['handler'])) $config['handler'] = HandlerStack::create();
        $config['handler']->unshift($this->bindUriMiddleware(), 'bind_uri');
        $config['handler']->unshift($this->lastResponseMiddleware(), 'last_response');
        $config['handler']->unshift($this->parseMiddleware(), 'parse');
        
        parent::__construct($config);
    }

    /**
     * Do a HTTP request
     * 
     * @param string $method
     * @param string $uri
     * @param array  $options
     * @return \GuzzleHttp\Promise\PromiseInterface|Response
     */
    public function requestAsync($method, $uri = null, array $options = [])
    {
        if (isset($options['data'])) {
            if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
                $options['query'] = $options['data'];
                unset($options['data']);
            } else {
                $this->applyPostData($options);
            }
        }
        
        return parent::requestAsync($method, $uri, $options);
    }

    /**
     * Create a request object.
     * 
     * <i>For internal use only</i>
     * 
     * @ignore
     * 
     * @param string $method
     * @param string $uri
     * @param array  $query
     * @return Request
     */
    public function createRequest($method, $uri, array $query = [])
    {
        $uri = rtrim($this->getConfig('base_uri'), '/') . static::bindUri($uri, $query);
        return new Request($method, $uri);
    }

    /**
     * Apply 'data' option based on content type
     * 
     * @param array $options
     */
    protected function applyPostData(&$options)
    {
        $headers = (isset($options['headers']) ? $options['headers'] : []) + $this->getConfig('headers');
        $contentType = isset($headers['Content-Type']) ? $headers['Content-Type'] : null;

        switch ($contentType) {
            case 'application/json':
                $options['json'] = $options['data'];
                break;
            case 'multipart/formdata':
                array_walk($options['data'], function(&$value, $name) {
                    $value = ['name' => $name, 'contents' => $value];
                });
                $options['multipart'] = $options['data'];
                break;
            case 'x-www-form-urlencoded':
                $options['form_params'] = $options['data'];
                break;
            default:
                if (!isset($contentType)) {
                    trigger_error("Don't know how to encode data, Content-Type header isn't set", E_USER_WARNING);
                }
                $options['body'] = $options['data'];
        }

        unset($options['data']);
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param array $filter
     * @return array
     */
    public function filterToQuery($filter)
    {
        return (array)$filter;
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param string|array $sort
     * @return array
     */
    public function sortToQuery($sort)
    {
        return isset($sort) ? compact('sort') : [];
    }
    
    /**
     * Add filter to request paramaters
     * 
     * @param array $limit
     * @return array
     */
    public function limitToQuery($limit)
    {
        if (!isset($limit)) return [];
        if (!is_array($limit)) return compact('limit');
        
        if (is_int(key($limit))) {
            $keys = count($limit) === 1 ? ['limit'] : ['limit', 'offset'];
            return array_combine($keys, $limit);
        }
        
        // Only allow specific keywords for security reasons
        $keys = ['limit', 'offset', 'page', 'cursor'];
        return array_intersect_key($limit, array_fill_keys($keys, $limit));
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
                } elseif ($contentType === 'x-www-form-urlencoded') {
                    $data = [];
                    parse_str((string)$request->getBody(), $data);
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
                
                return $promise->then(function(Response $response) use ($request) {
                    $response->request = $request;
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
                    
                return $promise->then(function(Response $response) use ($request, $options) {
                    return $this->parseResponse($request, $response, $options);
                });
            };
        };
    }
    
    /**
     * Parse the HTTP response
     * 
     * @param Request  $request
     * @param Response $response
     * @param array    $options
     * @return mixed
     */
    protected function parseResponse(Request $request, Response $response, array $options)
    {
        $this->parseResponseAssert($request, $response, $options);
        
        list($contentType) = preg_replace('/\s*;.*$/', '', $response->getHeader('Content-Type')) + [$options['parse']];
        $status = $response->getStatusCode();
        
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
        return $this->parseResponseSuccesss($data, $request, $response, $options);
    }
    
    /**
     * Assert that the response can be parsed
     * 
     * @param Request  $request
     * @param Response $response
     * @param array    $options
     */
    protected function parseResponseAssert($request, $response, $options)
    {
        $parse = $options['parse'];
        list($contentType) = preg_replace('/\s*;.*$/', '', $response->getHeader('Content-Type')) + [null];
        
        // Only JSON is currently supported
        if ($parse !== 'application/json') {
            throw new \Exception("Parsing is only supported for 'application/json', not '{$parse}'");
        }
        
        if (!isset($contentType)) {
            trigger_error("Server response doesn't specify the content type, assuming {$parse}", E_USER_NOTICE);
        }

        if (!in_array($contentType, [$parse, 'text/plain'])) {
            $message = "Server responded with '$contentType', while expecting '$parse'";
            throw new InvalidContentException($message, $request, $response);
        }
    }
    
    /**
     * Parse the response of a success request
     * 
     * @param mixed    $data
     * @param Request  $request
     * @param Response $response
     * @param array    $options
     * @return type
     */
    protected function parseResponseSuccesss($data, $request, $response, $options)
    {
        if (!isset($data)) {
            $message = "Corrupt JSON response: " . json_last_error_msg();
            throw new InvalidContentException($message, $request, $response);
        }
        
        if (!empty($options['expected_type']) && gettype($data) !== $options['expected_type']) {
            $message = "Was expecting a {$options['expected_type']} for `" . $request->getMethod() . " " .
                $request->getUri() . "`, but got a " . gettype($data);
            throw new UnexpectedContentException($message, $request, $response);
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
