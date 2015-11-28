<?php

namespace Jasny\DB\REST\Resource;

use Jasny\DB\Entity;
use Jasny\DB\EntitySet;
use Jasny\DB\REST\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;

use Jasny\DB\REST\Dataset\ImplementationStub as Dataset;

use Phake;

/**
 * Test for Jasny\DB\REST\Dataset\BasicImplementation
 */
class ImplementationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Setup class
     */
    protected function setUp()
    {
        Dataset::$db = null;
        Dataset::$uri = null;
        Dataset::$resourceClass = null;
    }
    
    
    /**
     * Test Dataset\Implementation::getResourceClass()
     */
    public function testGetResourceClass()
    {
        $class = \Jasny\call_private_method(Dataset::class, 'getResourceClass');
        $this->assertEquals(Dataset::class, $class);
    }
    
    /**
     * Test Dataset\Implementation::getResourceClass()
     */
    public function testEntitySet()
    {
        $entitySet = \Jasny\call_private_method(Dataset::class, 'entitySet', [['foo' => 1], ['foo' => 2]]);
        
        $this->assertInstanceOf(EntitySet::class, $entitySet);
        $this->assertSame(Dataset::class, $entitySet->getEntityClass());
        $this->assertCount(2, $entitySet->getArrayCopy());
        
        $this->assertNull($entitySet->countTotal());        
    }
    
    /**
     * Test Dataset\Implementation::getResourceClass() for totalCount and flags arguments
     */
    public function testEntitySet_Args()
    {
        $entitySet = \Jasny\call_private_method(Dataset::class, 'entitySet', [], 22, 256);
        
        $this->assertInstanceOf(EntitySet::class, $entitySet);
        $this->assertSame(22, $entitySet->countTotal());
        $this->assertSame(256, \Jasny\get_private_property($entitySet, 'flags'));
    }
    
    /**
     * Test Dataset\Implementation::getUri() guessing the uri
     */
    public function testGetUri()
    {
        $uri = \Jasny\call_private_method(Dataset::class, 'getUri');
        $this->assertSame('/implementation-stubs/:id', $uri);
    }
    
    /**
     * Test Dataset\Implementation::fetch()
     */
    public function testFetch()
    {
        Dataset::$uri = '/items/:id';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['foo' => 'y'])->thenReturn(['id' => 10]);
        
        $options = [
            'query' => ['id' => 10],
            'qux' => 'red',
            'parse' => 'application/json',
            'expected_type' => 'object'
        ];
        Phake::when(Dataset::$db)->get('/items/:id', $options)->thenReturn((object)['foo' => 1]);
        
        $result = Dataset::fetch('y', ['qux' => 'red']);
        
        Phake::verify(Dataset::$db)->filterToQuery(['foo' => 'y']);
        Phake::verify(Dataset::$db)->get('/items/:id', $options);
        
        $this->assertInstanceOf(Dataset::class, $result);
        $this->assertSame(1, $result->foo);
    }
    
    /**
     * Test Dataset\Implementation::fetch() with alternative output
     */
    public function testFetch_String()
    {
        Dataset::$uri = '/items/:id';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['foo' => 'y'])->thenReturn(['id' => 10]);
        
        $options = [
            'query' => ['id' => 10],
            'qux' => 'red',
            'parse' => 'application/json',
            'expected_type' => 'string'
        ];
        Phake::when(Dataset::$db)->get('/items/:id', $options)->thenReturn('hello world');
        
        $result = Dataset::fetch('y', ['qux' => 'red', 'expected_type' => 'string']);
        
        Phake::verify(Dataset::$db)->filterToQuery(['foo' => 'y']);
        Phake::verify(Dataset::$db)->get('/items/:id', $options);
        
        $this->assertSame('hello world', $result);
    }

    
    /**
     * Test Dataset\Implementation::exists() returning true
     */
    public function testExists_True()
    {
        Dataset::$uri = '/items/:id';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['foo' => 'y'])->thenReturn(['id' => 10]);
        
        $options = [
            'query' => ['id' => 10],
            'qux' => 'red',
            'parse' => false,
            'http_errors' => false
        ];
        Phake::when(Dataset::$db)->request('GET', '/items/:id', $options)->thenReturn(new Response(200));
        
        $result = Dataset::exists('y', ['qux' => 'red']);
        
        Phake::verify(Dataset::$db)->filterToQuery(['foo' => 'y']);
        Phake::verify(Dataset::$db)->request('GET', '/items/:id', $options);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test Dataset\Implementation::exists() returning true
     */
    public function testExists_False()
    {
        Dataset::$uri = '/items/:id';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['foo' => 'y'])->thenReturn(['id' => 10]);
        
        $options = [
            'query' => ['id' => 10],
            'qux' => 'red',
            'parse' => false,
            'http_errors' => false
        ];
        Phake::when(Dataset::$db)->request('GET', '/items/:id', $options)->thenReturn(new Response(404));
        
        $result = Dataset::exists('y', ['qux' => 'red']);
        
        Phake::verify(Dataset::$db)->filterToQuery(['foo' => 'y']);
        Phake::verify(Dataset::$db)->request('GET', '/items/:id', $options);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test Dataset\Implementation::exists() with a server error
     * 
     * @expectedException \GuzzleHttp\Exception\ClientException
     * @expectedExceptionMessage Client error: `GET http://www.example.com/items/10` resulted in a `400 Bad Request` response
     */
    public function testExists_Fail()
    {
        Dataset::$uri = '/items/:id';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['foo' => 'y'])->thenReturn(['id' => 10]);
        Phake::when(Dataset::$db)->createRequest('GET', '/items/:id', ['id' => 10])
            ->thenReturn(new Request('GET', 'http://www.example.com/items/10'));
        
        $options = [
            'query' => ['id' => 10],
            'qux' => 'red',
            'parse' => false,
            'http_errors' => false
        ];
        Phake::when(Dataset::$db)->request('GET', '/items/:id', $options)->thenReturn(new Response(400));
        
        Dataset::exists('y', ['qux' => 'red']);
    }
    
    
    /**
     * Test Dataset\Implementation::fetchAll()
     */
    public function testFetchAll()
    {
        Dataset::$uri = '/items';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['x' => 'y'])->thenReturn(['foo' => 'bar']);
        Phake::when(Dataset::$db)->sortToQuery('x')->thenReturn(['sort' => 'foo']);
        Phake::when(Dataset::$db)->limitToQuery(10)->thenReturn(['limit' => 22]);
        
        $options = [
            'query' => ['foo' => 'bar', 'sort' => 'foo', 'limit' => 22],
            'qux' => 'red',
            'parse' => 'application/json',
            'expected_type' => 'array'
        ];
        Phake::when(Dataset::$db)->get('/items', $options)->thenReturn([(object)['foo'=>1], (object)['foo'=>2]]);
        
        $result = Dataset::fetchAll(['x' => 'y'], 'x', 10, ['qux' => 'red']);
        
        Phake::verify(Dataset::$db)->filterToQuery(['x' => 'y']);
        Phake::verify(Dataset::$db)->sortToQuery('x');
        Phake::verify(Dataset::$db)->limitToQuery(10);
        
        Phake::verify(Dataset::$db)->get('/items', $options);
        
        $this->assertInstanceOf(EntitySet::class, $result);
        $this->assertCount(2, $result->getArrayCopy());
        
        $this->assertInstanceOf(Dataset::class, $result[0]);
        $this->assertSame(1, $result[0]->foo);
        
        $this->assertInstanceOf(Dataset::class, $result[1]);
        $this->assertSame(2, $result[1]->foo);
    }
    
    /**
     * Test Dataset\Implementation::fetchA;;() with alternative output
     */
    public function testFetchAll_String()
    {
        Dataset::$uri = '/items';
        Dataset::$db = Phake::mock(Client::class);
        
        $options = [
            'query' => [],
            'qux' => 'red',
            'parse' => 'application/json',
            'expected_type' => 'string'
        ];
        Phake::when(Dataset::$db)->get('/items', $options)->thenReturn('my sweet items');
        
        $result = Dataset::fetchAll([], null, null, ['qux' => 'red', 'expected_type' => 'string']);
        
        Phake::verify(Dataset::$db)->get('/items', $options);
        $this->assertSame('my sweet items', $result);
    }

    
    /**
     * Test Dataset\Implementation::fetchPairs()
     */
    public function testFetchPairs()
    {
        Dataset::$uri = '/items';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['x' => 'y'])->thenReturn(['foo' => 'bar']);
        Phake::when(Dataset::$db)->sortToQuery('x')->thenReturn(['sort' => 'foo']);
        Phake::when(Dataset::$db)->limitToQuery(10)->thenReturn(['limit' => 22]);
        
        $options = [
            'query' => ['foo' => 'bar', 'sort' => 'foo', 'limit' => 22],
            'qux' => 'red',
            'parse' => 'application/json',
            'expected_type' => 'array'
        ];
        Phake::when(Dataset::$db)->get('/items', $options)->thenReturn([(object)['foo'=>1], (object)['foo'=>2]]);
        
        $result = Dataset::fetchPairs(['x' => 'y'], 'x', 10, ['qux' => 'red']);
        
        Phake::verify(Dataset::$db)->filterToQuery(['x' => 'y']);
        Phake::verify(Dataset::$db)->sortToQuery('x');
        Phake::verify(Dataset::$db)->limitToQuery(10);
        
        Phake::verify(Dataset::$db)->get('/items', $options);
        
        $this->assertSame([1 => 'hello 1', 2 => 'hello 2'], $result);
    }
    
    /**
     * Test Dataset\Implementation::fetchPairs()
     * 
     * @expectedException LogicException
     * @expectedExceptionMessage entities aren't identifiable
     */
    public function testFetchPairs_NotIdentifiable()
    {
        Dataset::$resourceClass = get_class(Phake::mock(Entity::class));
        Dataset::fetchPairs();
    }
    
    
    /**
     * Test Dataset\Implementation::fetchPairs()
     * 
     * @expectedException LogicException
     * @expectedExceptionMessage entities can't be casted to a string
     */
    public function testFetchPairs_NotCastable()
    {
        Dataset::$resourceClass = get_class(Phake::mock(Entity\Identifiable::class));
        Dataset::fetchPairs();
    }
    
    /**
     * Test Dataset\Implementation::testCount()
     */
    public function testCount()
    {
        Dataset::$uri = '/items';
        Dataset::$db = Phake::mock(Client::class);
        
        Phake::when(Dataset::$db)->filterToQuery(['x' => 'y'])->thenReturn(['foo' => 'bar']);
        
        $options = [
            'query' => ['foo' => 'bar'],
            'qux' => 'red',
            'parse' => 'application/json',
            'expected_type' => 'array'
        ];
        Phake::when(Dataset::$db)->get('/items', $options)->thenReturn([(object)['foo'=>1], (object)['foo'=>2]]);
        
        $result = Dataset::count(['x' => 'y'], ['qux' => 'red']);
        
        Phake::verify(Dataset::$db)->filterToQuery(['x' => 'y']);
        Phake::verify(Dataset::$db)->get('/items', $options);
        
        $this->assertSame(2, $result);
    }
}
