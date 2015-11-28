<?php

namespace Jasny\DB\REST\Resource;

use GuzzleHttp\Psr7\Response;
use Jasny\DB\REST\Resource\BasicImplementationStub as Resource;

/**
 * Test for Jasny\DB\REST\Resource\BasicImplementation
 */
class BasicImplementationTest extends \PHPUnit_Framework_TestCase
{
    use \Jasny\DB\REST\CreateMockClient;

    /**
     * @var object
     */
    protected $resource;
    
    /**
     * Setup class
     */
    protected function setUp()
    {
        Resource::$db = null;
        Resource::$map = [];
        
        $this->resource = new Resource();
    }
    
    /**
     * Test BasicImplementation::getSaveMethod()
     */
    public function testGetSaveMethod()
    {
        $method = \Jasny\call_private_method($this->resource, 'getSaveMethod');
        $this->assertSame('PUT', $method);
    }
    
    /**
     * Test BasicImplementation::updateFromData()
     */
    public function testUpdateFromData()
    {
        $this->resource->id = 10;
        $this->resource->title = 'foo';
        
        \Jasny\call_private_method($this->resource, 'updateFromData', ['id' => 10, 'title' => 'bar']);
        
        $this->assertSame($this->resource->id, 10);
        $this->assertSame($this->resource->title, 'bar');
    }
    
    /**
     * Test BasicImplementation::updateFromData() when response doesn't have an id
     * 
     * @expectedException PHPUnit_Framework_Error_Notice
     * @expectedExceptionMessage Response data can't be identified as this
     */
    public function testUpdateFromData_NoId()
    {
        $this->resource->id = 10;
        $this->resource->title = 'foo';
        
        \Jasny\call_private_method($this->resource, 'updateFromData', ['title' => 'bar']);
    }
    
    /**
     * Test BasicImplementation::updateFromData() when response has a difference id
     * 
     * @expectedException Exception
     * @expectedExceptionMessage Response has id '33', while BasicImplementationStub has id '10'
     */
    public function testUpdateFromData_IncorrectId()
    {
        $this->resource->id = 10;
        $this->resource->title = 'foo';
        
        \Jasny\call_private_method($this->resource, 'updateFromData', ['id' => 33, 'title' => 'bar']);
    }
    
    /**
     * Test if BasicImplementation::updateFromData() calls markAsPersisted()
     */
    public function testUpdateFromData_ChangeAware()
    {
        $resource = $this->getMock(Resource::class, ['markAsPersisted']);
        $resource->expects($this->once())->method('markAsPersisted');
        
        \Jasny\call_private_method($resource, 'updateFromData', ['id' => 10, 'title' => 'bar']);
    }
    
    /**
     * Test BasicImplementation::updateFromData()
     */
    public function testUpdateFromData_Map()
    {
        Resource::$map = ['name' => 'title'];
        
        $this->resource->id = 10;
        $this->resource->title = 'foo';
        
        \Jasny\call_private_method($this->resource, 'updateFromData', ['id' => 10, 'name' => 'bar']);
        
        $this->assertSame($this->resource->id, 10);
        $this->assertSame($this->resource->title, 'bar');
    }
    
    
    /**
     * Test BasicImplementation::save()
     */
    public function testSave()
    {
        $this->resource->title = 'bar';
        
        $history = [];
        $response = new Response(200, ['Content-Type' => 'application/json'], '{"id":10,"title":"bar","status":1}');
        Resource::$db = $this->getClientWithMockHandler(
            [$response],
            $history,
            ['headers' => ['Content-Type' => 'application/json']
        ]);
        
        $ret = $this->resource->save();
        $this->assertSame($this->resource, $ret);

        $this->assertCount(1, $history);
        $this->assertSame('{"title":"bar"}', (string)$history[0]['request']->getBody());
        
        $this->assertSame(10, $this->resource->id);
        $this->assertSame('bar', $this->resource->title);
        $this->assertSame(1, $this->resource->status);
    }
}
