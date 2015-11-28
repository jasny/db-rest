<?php

namespace Jasny\DB\REST;

use GuzzleHttp\Psr7\Response;

/**
 * Tests for Jasny\DB\Rest\Client
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create a client with a mock handler and history middleware
     * 
     * @param array $responses
     * @param array $container  History container
     * @param array $config     Client configuration
     * @return Phake_IMock|Client
     */
    protected function getClientWithMockHandler(array $responses = [], array &$container = [], array $config = [])
    {
        $client = new Client('http://www.example.com', $config);
        $handlerStack = $client->getConfig('handler');
        
        $mock = new MockHandler($responses);
        $handlerStack->setHandler($mock);
        
        $history = Middleware::history($container);
        $handlerStack->push($history, 'history');
        
        return $client;
    }
    
    /**
     * Test creating a new client with only the URI
     */
    public function testConstructor_URI()
    {
        $client = new Client('http://www.example.com');
        $this->assertEquals('http://www.example.com', $client->getConfig('base_uri'));
    }
    
    /**
     * Test creating a new client with config options
     */
    public function testConstructor()
    {
        $client = new Client([
            'base_uri' => 'http://www.example.com',
            'auth' => ['user', 'pwd'],
            'timeout' => 3
        ]);
        
        $this->assertEquals('http://www.example.com', $client->getConfig('base_uri'));
        $this->assertEquals(['user', 'pwd'], $client->getConfig('auth'));
        $this->assertEquals(3, $client->getConfig('timeout'));
    }
    
    /**
     * This if the constructor has added our middleware
     */
    public function testConstructor_HasMiddleware()
    {
        $client = new Client('http://www.example.com');
        
        $handler = $client->getConfig('handler');
        $stack = \Jasny\get_private_property($handler, 'stack');
        
        $names = array_column($stack, 1);
        $this->assertContains('bind_uri', $names);
        $this->assertContains('last_response', $names);
        $this->assertContains('parse', $names);
    }

    
    /**
     * Test Client::request() with `data` option as GET request
     */
    public function testRequest_GET()
    {
        $data = ['foo' => 1, 'bar' => 44];
        $headers = ['Content-Type' => 'application/json'];
        
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        $response = $client->request('GET', '/', compact('data', 'headers'));
        
        $this->assertInstanceOf(Response::class, $response);
        
        $this->assertCount(1, $history);
        $this->assertSame('foo=1&bar=44', $history[0]['request']->getUri()->getQuery());
    }
    
    /**
     * Test Client::request() with `data` option and `Content-Type: application/json`
     */
    public function testRequest_JsonData()
    {
        $data = ['foo' => 1, 'bar' => 44];
        $headers = ['Content-Type' => 'application/json'];
        
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        $response = $client->request('POST', '/', compact('data', 'headers'));
        
        $this->assertInstanceOf(Response::class, $response);
        
        $this->assertCount(1, $history);
        $this->assertSame('{"foo":1,"bar":44}', (string)$history[0]['request']->getBody());
    }
    
    /**
     * Test Client::request() with `data` option and `Content-Type: multipart/formdata`
     */
    public function testRequest_MultipartData()
    {
        $data = ['foo' => '1', 'bar' => '44'];
        $headers = ['Content-Type' => 'multipart/formdata'];
        
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        $response = $client->request('POST', '/', compact('data', 'headers'));
        
        $this->assertInstanceOf(Response::class, $response);
        
        $this->assertCount(1, $history);
        
        $body = (string)$history[0]['request']->getBody();
        $formData = \Jasny\call_private_method($client, 'parseMultipartFormData', $body);
        $this->assertSame($data, $formData);
        
    }
    
    /**
     * Test Client::request() with `data` option and `Content-Type: multipart/formdata`
     */
    public function testRequest_UrlencodedData()
    {
        $data = ['foo' => '1', 'bar' => '44'];
        $headers = ['Content-Type' => 'x-www-form-urlencoded'];
        
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        $response = $client->request('POST', '/', compact('data', 'headers'));
        
        $this->assertInstanceOf(Response::class, $response);
        
        $this->assertCount(1, $history);
        $this->assertSame('foo=1&bar=44', (string)$history[0]['request']->getBody());
    }
    
    /**
     * Test Client::request() with `data` option and an unknown content type
     */
    public function testRequest_UnknownData()
    {
        $data = 'hello-world';
        $headers = ['Content-Type' => 'x-unknown'];
        
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        $response = $client->request('POST', '/', compact('data', 'headers'));
        
        $this->assertInstanceOf(Response::class, $response);
        
        $this->assertCount(1, $history);
        $this->assertSame($data, (string)$history[0]['request']->getBody());
    }
    
    /**
     * Test Client::request() with `data` option and the client configured with `Content-Type`
     */
    public function testRequest_Configure()
    {
        $data = ['foo' => 1, 'bar' => 44];
        $headers = ['Content-Type' => 'application/json'];
        
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history, compact('headers'));
        $response = $client->request('POST', '/', compact('data'));
        
        $this->assertInstanceOf(Response::class, $response);
        
        $this->assertCount(1, $history);
        $this->assertSame('{"foo":1,"bar":44}', (string)$history[0]['request']->getBody());
    }
    
    /**
     * Test Client::request() with `data` option without specifying the content type
     * 
     * @expectedException PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessage Don't know how to encode data, Content-Type header isn't set
     */
    public function testRequest_DataWithoutContentType()
    {
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        $response = $client->request('POST', '/', ['data' => 'hello-world']);
        
        $this->assertInstanceOf(Response::class, $response);
        
        $this->assertCount(1, $history);
        $this->assertSame($data, (string)$history[0]['request']->getBody());
    }
    
    
    /**
     * Test Client::filterToQuery
     * 
     * @param array $query
     * @param array $filter
     */
    public function testFilterToQuery()
    {
        $query = Client::filterToQuery(['foo' => 1, 'bar' => 'abc']);
        $this->assertSame(['foo' => 1, 'bar' => 'abc'], $query);
    }
    
    /**
     * Test Client::sortToQuery
     */
    public function testSortToQuery()
    {
        $query = Client::sortToQuery('~foo');
        $this->assertSame(['sort' => '~foo'], $query);
    }
    
    /**
     * Test Client::limitToQuery with only a limit
     */
    public function testLimitToQuery_WithoutOffset()
    {
        $query = Client::limitToQuery(10);
        $this->assertSame(['limit' => 10], $query);
    }
    
    /**
     * Test Client::limitToQuery with both a limit and offset
     */
    public function testLimitToQuery_WithOffset()
    {
        $query = Client::limitToQuery([10, 30]);
        $this->assertSame(['limit' => 10, 'offset' => 30], $query);
    }
    
    /**
     * Test Client::limitToQuery with both a limit and offset
     */
    public function testLimitToQuery_Assoc()
    {
        $this->assertSame(['limit' => 10, 'offset' => 30], Client::limitToQuery(['limit' => 10, 'offset' => 30]));
        $this->assertSame(['limit' => 10, 'page' => 6], Client::limitToQuery(['limit' => 10, 'page' => 6]));
        $this->assertSame(['cursor' => 12345], Client::limitToQuery(['cursor' => 12345]));
    }
    
    
    /**
     * Test Client::bindUri
     */
    public function testBindUri()
    {
        $this->assertSame('/foo/bar', Client::bindUri('/foo/bar', []));
        
        $this->assertSame('/foo/bar/', Client::bindUri('/foo/bar/:id', []));
        $this->assertSame('/foo/bar/10', Client::bindUri('/foo/bar/:id', ['id' => 10]));
        $this->assertSame('/foo/bar/10?color=blue', Client::bindUri('/foo/bar/:id', ['id' => 10, 'color' => 'blue']));
        $this->assertSame(
            '/foo/bar/?color=blue&type=x',
            Client::bindUri('/foo/bar/:id', ['color' => 'blue', 'type' => 'x'])
        );
        
        $this->assertSame('/foo/bar/10/blue', Client::bindUri('/foo/bar/:id/:color', ['id' => 10, 'color' => 'blue']));
        $this->assertSame('/foo/bar/?color=blue', Client::bindUri('/foo/bar/:id/:color', ['color' => 'blue']));
        
        $this->assertSame('/foo/bar/10', Client::bindUri('/foo/bar/:id', [], ['id' => 10]));
        $this->assertSame(
            '/foo/bar/10?color=blue',
            Client::bindUri('/foo/bar/:id', ['color' => 'blue'], (object)['id' => 10])
        );
        
        $this->assertSame(
            '/foo/bar/10?color=blue',
            Client::bindUri('/foo/bar/:id', ['id' => 10, 'color' => 'blue'], ['id' => 20])
        );
    }
    
    /**
     * Test Client::bindUri with JsonSerializable object as data
     */
    public function testBindUri_JsonSerialize()
    {
        $mock = $this->getMock('JsonSerializable');
        $mock->method('jsonSerialize')->will($this->returnValue(['id' => 10]));
        
        $this->assertSame('/foo/bar/10', Client::bindUri('/foo/bar/:id', [], $mock));
    }

    /**
     * Test bindUri middleware
     */
    public function testBindUriMiddleware()
    {
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        
        $client->get('/foo/:id', ['query' => ['id' => 10, 'color' => 'blue']]);
        
        $this->assertCount(1, $history);
        $this->assertSame('/foo/10', $history[0]['request']->getUri()->getPath());
        $this->assertSame('color=blue', $history[0]['request']->getUri()->getQuery());
    }

    /**
     * Test bindUri middleware with multipart data
     */
    public function testBindUriMiddleware_Multipart()
    {
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        
        $client->post('/foo/:id', ['multipart' => [
            ['name' => 'id', 'contents' => '10'],
            ['name' => 'color', 'contents' => 'blue']
        ]]);
        
        $this->assertCount(1, $history);
        $this->assertSame('/foo/10', $history[0]['request']->getUri()->getPath());
    }
    
    /**
     * Test bindUri middleware with JSON object
     */
    public function testBindUriMiddleware_Json()
    {
        $mock = $this->getMock('JsonSerializable');
        $mock->method('jsonSerialize')->will($this->returnValue(['id' => 10, 'status' => 3]));
        
        $history = [];
        $client = $this->getClientWithMockHandler([new Response(200)], $history);
        
        $client->post('/foo/:id', ['query' => ['color' => 'blue'], 'json' => $mock]);
        
        $this->assertCount(1, $history);
        $this->assertSame('/foo/10', $history[0]['request']->getUri()->getPath());
        $this->assertSame('color=blue', $history[0]['request']->getUri()->getQuery());
        $this->assertSame('{"id":10,"status":3}', (string)$history[0]['request']->getBody());
    }
    
    
    /**
     * Test last response middleware
     */
    public function testGetLastResponse()
    {
        $client = $this->getClientWithMockHandler([
            new Response(204),
            new Response(201, ['Content-Type' => 'application/json'], '{test: 2}'),
            new Response(200, ['Content-Type' => 'text/plain'], 'Test number 3')
        ]);
        
        $client->get('/test/1');
        
        $client->get('/test/2');
        $response2 = $client->getLastResponse();
        $this->assertEquals(['application/json'], $response2->getHeader('Content-Type'));
        $this->assertEquals('{test: 2}', (string)$response2->getBody());
        
        $client->get('/test/3');
        $response3 = $client->getLastResponse();
        $this->assertEquals(['text/plain'], $response3->getHeader('Content-Type'));
        $this->assertEquals('Test number 3', (string)$response3->getBody());
    }
    
    
    /**
     * Test parse middleware
     */
    public function testParseMiddleware()
    {
        $responseBody = <<<JSON
{
    "foo": "bar",
    "type": 2,
    "color": "blue"
}
JSON;
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);

        $history = [];
        $client = $this->getClientWithMockHandler([$response], $history);
        
        $result = $client->get('/foo/1', ['parse' => 'application/json']);
        
        $this->assertInstanceOf('stdClass', $result);
        $this->assertEquals((object)[
            'foo' => 'bar',
            'type' => 2,
            'color' => 'blue'
        ], $result);
        
        $this->assertCount(1, $history);
        $this->assertSame(['application/json; q=1.0, text/plain; q=0.5'], $history[0]['request']->getHeader('Accept'));
    }
    
    /**
     * Test parse middleware when a 404 not found response is given
     */
    public function testParseMiddleware_NotFound()
    {
        $response = new Response(404, ['Content-Type' => 'text/plain'], 'not found');
        $client = $this->getClientWithMockHandler([$response]);
        
        $result = $client->get('/foo/1', ['parse' => 'application/json']);
        $this->assertNull($result);
    }
    
    /**
     * Test parse middleware when a 400 bad request response is given
     * 
     * @expectedException GuzzleHttp\Exception\ClientException
     * @expectedExceptionMessage bar not set
     */
    public function testParseMiddleware_BadRequest()
    {
        $response = new Response(400, ['Content-Type' => 'application/json'], '"bar not set"');
        $client = $this->getClientWithMockHandler([$response]);
        
        $client->get('/foo/1', ['parse' => 'application/json']);
    }
    
    /**
     * Test parse middleware when a 500 server error response is given
     * 
     * @expectedException GuzzleHttp\Exception\ServerException
     * @expectedExceptionMessage unexpected error
     */
    public function testParseMiddleware_ServerError()
    {
        $response = new Response(500, ['Content-Type' => 'text/plain'], 'unexpected error');
        $client = $this->getClientWithMockHandler([$response]);
        
        $client->get('/foo/1', ['parse' => 'application/json']);
    }

    /**
     * Test parse middleware with a corrupt response body
     * 
     * @expectedException Jasny\DB\REST\InvalidContentException
     * @expectedExceptionMessage Corrupt JSON response: unexpected end of data
     */
    public function testParseMiddleware_InvalidResponse()
    {
        $responseBody = <<<JSON
{
    "foo": "
JSON;
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $client = $this->getClientWithMockHandler([$response]);
        
        $client->get('/foo/1', ['parse' => 'application/json']);
    }

    /**
     * Test parse middleware with a corrupt response body
     * 
     * @expectedException Jasny\DB\REST\InvalidContentException
     * @expectedExceptionMessage Server responded with 'text/html', while expecting 'application/json'
     */
    public function testParseMiddleware_HTMLResponse()
    {
        $response = new Response(200, ['Content-Type' => 'text/html']);
        $client = $this->getClientWithMockHandler([$response]);
        
        $client->get('/foo/1', ['parse' => 'application/json']);
    }

    /**
     * Test parse middleware with an unsupported content type
     * 
     * @expectedException Exception
     * @expectedExceptionMessage Parsing is only supported for 'application/json', not 'application/x-foobar'
     */
    public function testParseMiddleware_UnsupportedContentType()
    {
        $response = new Response(200, ['Content-Type' => 'application/x-foobar']);
        $client = $this->getClientWithMockHandler([$response]);
        
        $client->get('/foo/1', ['parse' => 'application/x-foobar']);
    }

    /**
     * Test parse middleware with an unsupported content type
     * 
     * @expectedException PHPUnit_Framework_Error_Notice
     * @expectedExceptionMessage Server response doesn't specify the content type, assuming application/json
     */
    public function testParseMiddleware_ContentTypeNotice()
    {
        $response = new Response(200, [], 'null');
        $client = $this->getClientWithMockHandler([$response]);
        
        $client->get('/foo/1', ['parse' => 'application/json']);
    }

    /**
     * Test parse middleware without a parse option
     */
    public function testParseMiddleware_NoParse()
    {
        $response = new Response(200);
        $client = $this->getClientWithMockHandler([$response]);
        
        $result = $client->get('/foo/1');
        $this->assertInstanceOf(Response::class, $result);
    }
    
    /**
     * Test Client::extractErrorMessage
     */
    public function testExtractErrorMessage()
    {
        $client = new Client('http://www.example.com');
        
        $this->assertSame(
            'error I',
            \Jasny\call_private_method($client, 'extractErrorMessage', 'error I')
        );
        
        $this->assertSame(
            'error II',
            \Jasny\call_private_method($client, 'extractErrorMessage', (object)['message' => 'error II'])
        );
        
        $this->assertSame(
            'error III',
            \Jasny\call_private_method($client, 'extractErrorMessage', (object)['error' => 'error III'])
        );
        
        $err = (object)['error' => (object)['message' => 'error IV']];
        $this->assertSame(
            'error IV',
            \Jasny\call_private_method($client, 'extractErrorMessage', $err)
        );
    }
    
    /**
     * Test Client::extractErrorMessage
     * 
     * @expectedException PHPUnit_Framework_Error_Notice
     * @expectedExceptionMessage Failed to extract error message from response
     */
    public function testExtractErrorMessage_Notice()
    {
        $client = new Client('http://www.example.com');
        \Jasny\call_private_method($client, 'extractErrorMessage', (object)['foo' => 1]);
    }
}
