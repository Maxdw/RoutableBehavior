<?php
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('Hash', 'Utility');
App::uses('Router', 'Routing');
App::uses('RoutableBehavior', 'Model/Behavior');

// Include standard cake test models
include CAKE . 'Test' . DS . 'Case' . DS . 'Model' . DS . 'models.php';

/**
 * TestRoutableBehavior class
 * 
 * Mocks the behavior to open up private methods for testing
 */
class TestRoutableBehavior extends RoutableBehavior {
	
	protected function _identify(array &$results, $primaryKey = null, array $groupKeys = array()) {
		// add fixtures as viable models/groupKeys
		$groupKeys[] = 'FlagTree';
		$groupKeys[] = 'Advertisement';
		$groupKeys[] = 'Home';
		$groupKeys[] = 'AnotherArticle';
		
		return parent::_identify($results, $primaryKey, $groupKeys);
	}
	
	public function protected_identify(array &$results, $primaryKey = null) {
		return self::_identify($results, $primaryKey);
	}
	
	public function protected_getAncestry(Model $Model, $id) {
		return parent::_getAncestry($Model, $id);
	}
	
}

/**
 * RoutableBehaviorTest class
 *
 * Test case for the RoutableBehavior's behavior
 */
class RoutableBehaviorTest extends CakeTestCase {
	
	/**
	 * Specifies which predefined test data fixures to load.
	 *
	 * @var array
	 */
	public $fixtures = array('core.flag_tree', 'core.advertisement', 'core.home', 'core.another_article');
	
	/**
	 * Tests RoutableBehavior::setup
	 * provokes the 'fields' error
	 *
	 * @expectedException			PHPUnit_Framework_Error
	 * @expectedExceptionMessage	Fields for TestRoutableBehavior of model Advertisement were not configured
	 */
	public function testSetupFieldsError() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => null,
			'recursive' => false
		));
	}
	
	/**
	 * Tests RoutableBehavior::setup
	 * provokes the 'route' error
	 *
	 * @expectedException			PHPUnit_Framework_Error
	 * @expectedExceptionMessage	Route for TestRoutableBehavior of model Advertisement was not configured
	 */
	public function testSetupRouteError() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => null,
			'fields' => array('title', 'id'),
			'recursive' => false
		));
	}
	
	/**
	 * Tests RoutableBehavior::setup
	 * provokes the 'route' not found error
	 *
	 * @expectedException			PHPUnit_Framework_Error
	 * @expectedExceptionMessage	Route /:title/:id/non-existant-route for TestRoutableBehavior of model Advertisement could not be found
	 */
	public function testSetupRouteNotFoundError() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id/non-existant-route',
			'fields' => array('title', 'id'),
			'recursive' => false
		));
	}
	
	/**
	 * Tests protected RouteableBehavior::_identify
	 */
	public function testIdentify() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(2, 3, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view',
			'recursive' => true
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name'
		));
		
		$data = $this->Tree->find('all');
		$references = $this->Tree->Behaviors->TestRoutable->protected_identify($data, $this->Tree->primaryKey);
		
		$this->assertTrue(!empty($data));
		$this->assertTrue(!empty($references));
		
		$references['FlagTree'][2]['name'] = 'test';
		
		$this->assertTrue(isset($references['FlagTree'][2]['name']));
		$this->assertTrue(isset($data[1]['FlagTree']['name']));
		
		$this->assertEqual($references['FlagTree'][2]['name'], $data[1]['FlagTree']['name']);
	}
	
	/**
	 * Tests protected RoutableBehavior::_matchCakeRoute
	 */
	public function testMatchCakeRoute() {
		$this->Tree = new FlagTree();
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view',
			'recursive' => true
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name'
		));
		
		$this->assertInstanceOf('CakeRoute', $this->Tree->Behaviors->TestRoutable->settings['FlagTree']['route']);
	}
	
	/**
	 * Tests protected RoutableBehavior::_getAncestry
	 */
	public function testGetAncestry() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true
		));
		
		$deepest_id = 5;
		$result = $this->Tree->protected_getAncestry($deepest_id);
		$expected = array('4','3','2','1');		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests virtualizeRoutes
	 * through find 'all'
	 */
	public function testVirtualizeRoutes() {
		$this->Ad = new Advertisement();
		$this->Ad->recursive = -1;
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id'),
			'recursive' => false
		));
		
		$result = $this->Ad->find('all');
		$result = Hash::extract($result, "{n}.{$this->Ad->alias}.routable");
		
		$expected = array('/First Ad/1', '/Second Ad/2');
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests virtualizeRoutes
	 * with nonexistant 'fields' through find 'all'
	 */
	public function testVirtualizeRoutesNonexistantFields() {
		$this->Ad = new Advertisement();
		$this->Ad->recursive = -1;
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id', 'non-existant-field'),
			'recursive' => false
		));
		
		$result = $this->Ad->find('all');
		$result = Hash::extract($result, "{n}.{$this->Ad->alias}.routable");
		
		$expected = array();
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 */
	public function testGenerateUriList() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id'),
			'recursive' => false
		));
		
		$result = $this->Ad->generateUriList();
		
		$expected = array(
			1 => '/First Ad/1',
			2 => '/Second Ad/2'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with more fields configured than exists in the data
	 *
	 * @expectedException			PDOException
	 * @expectedExceptionMessage	SQLSTATE[42S22]: Column not found: 1054 Unknown column 'Advertisement.non-existant-field' in 'field list'
	 */
	public function testGenerateUriListNonexistantFields() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id', 'non-existant-field'),
			'recursive' => false
		));
		
		$result = $this->Ad->generateUriList();
		$expected = array();
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with the fields, link and home options containing virtualFields
	 */
	public function testGenerateUriListVirtualFields() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:year/:month/:day/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('year', 'month', 'day', 'title', 'id'),
			'year' => '\d{4}',
			'month' => '([1-9]|10|11|12)',
			'day' => '(0?[1-9]|[12]\d|3[01])',
			'id' => '[0-9]+'
		));
		
		$this->Ad->virtualFields = array(
			'year' => "YEAR(`{$this->Ad->alias}`.`created`)",
			'month' => "MONTH(`{$this->Ad->alias}`.`created`)",
			'day' => "DAYOFMONTH(`{$this->Ad->alias}`.`created`)"
		);
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:year/:month/:day/:title/:id',
			'fields' => array('year', 'month', 'day', 'title', 'id'),
			'recursive' => false
		));
		
		$result = $this->Ad->generateUriList();
		
		$expected = array(
			1 => '/2007/3/18/First Ad/1',
			2 => '/2007/3/18/Second Ad/2'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with recursive option
	 */
	public function testGenerateUriListRecursive() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true
		));
		
		$result = $this->Tree->generateUriList();
		
		$expected = array(
			1 => '/1.%20Root',
			2 => '/1.%20Root/1.1',
			3 => '/1.%20Root/1.1/1.1.1',
			4 => '/1.%20Root/1.1/1.1.1/1.1.1.1',
			5 => '/1.%20Root/1.1/1.1.1/1.1.1.1/1.1.1.1.1'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with recursive and scope option
	 */
	public function testGenerateUriListRecursiveScope() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true
		));
		
		// asserting scope compliance
		$this->Tree->Behaviors->TestRoutable->settings['FlagTree']['scope'] = array('flag' => 1);
		
		$this->Tree->id = 3;
		$this->Tree->saveField('flag', true);
		
		$result = $this->Tree->generateUriList();
		
		$expected = array(
			3 => '/1.%20Root/1.1/1.1.1'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with recursive and scope option and the condition parameter
	 */
	public function testGenerateUriListRecursiveScopeCondtion() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true
		));
		
		// asserting scope compliance
		$this->Tree->Behaviors->TestRoutable->settings['FlagTree']['scope'] = array('flag' => 1);
		
		$this->Tree->id = 2;
		$this->Tree->saveField('flag', true);
		
		$result = $this->Tree->generateUriList(array(
			'FlagTree.name' => '1.1'
		));
		
		$expected = array(
			2 => '/1.%20Root/1.1'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with recursive, scope and cascadingScope options
	 */
	public function testGenerateUriListRecursiveCascadingScope() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true,
			'cascadingScope' => true,
			'scope' => array(
				'flag' => true
			)
		));
		
		// test route with parents that have disabled flags
		$this->Tree->id = 3;
		$this->Tree->saveField('flag', true);
		
		$result = $this->Tree->generateUriList();
		$expected = array(3 => null);
		$this->assertEquals($expected, $result);
		
		// test route with parents that have enabled flags
		$this->Tree->id = 2;
		$this->Tree->saveField('flag', true);
		
		$this->Tree->id = 1;
		$this->Tree->saveField('flag', true);
		
		$result = $this->Tree->generateUriList();
		
		$expected = array(
			1 => '/1.%20Root',
			2 => '/1.%20Root/1.1',
			3 => '/1.%20Root/1.1/1.1.1'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with recursive and home option
	 */
	public function testGenerateUriListRecursiveHome() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true,
			'home' => 'flag'
		));
		
		$this->Tree->id = 1;
		$this->Tree->saveField('flag', true);
		
		$result = $this->Tree->generateUriList();
		
		$expected = array(
			1 => '/',
			2 => '/1.%20Root/1.1',
			3 => '/1.%20Root/1.1/1.1.1',
			4 => '/1.%20Root/1.1/1.1.1/1.1.1.1',
			5 => '/1.%20Root/1.1/1.1.1/1.1.1.1/1.1.1.1.1'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUriList
	 * with recursive, home and link option
	 */
	public function testGenerateUriListRecursiveHomeLink() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true,
			'home' => 'flag',
			'link' => 'name'
		));
		
		$this->Tree->id = 1;
		$this->Tree->saveField('flag', true);
		
		$result = $this->Tree->generateUriList();
		
		$expected = array(
			1 => '1. Root',
			2 => '1.1',
			3 => '1.1.1',
			4 => '1.1.1.1',
			5 => '1.1.1.1.1'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::generateUrlList
	 */
	public function testGenerateUrlList() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true
		));
		
		$result = $this->Tree->generateUrlList();
		
		$expected = array(
			1 => FULL_BASE_URL . '/1.%20Root',
			2 => FULL_BASE_URL . '/1.%20Root/1.1',
			3 => FULL_BASE_URL . '/1.%20Root/1.1/1.1.1',
			4 => FULL_BASE_URL . '/1.%20Root/1.1/1.1.1/1.1.1.1',
			5 => FULL_BASE_URL . '/1.%20Root/1.1/1.1.1/1.1.1.1/1.1.1.1.1'
		);
		
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 */
	public function testServe() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id'),
			'recursive' => false
		));
		
		$result = $this->Ad->serve('/Second Ad/2');
		$expected = 2;
		$this->assertEquals($expected, $result);
		$this->assertNotEmpty($this->Ad->data);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 * with scope option
	 */
	public function testServeScope() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id'),
			'recursive' => false,
			'scope' => array('title' => 'Second Ad')
		));
		
		$result = $this->Ad->serve('/Second Ad/2');
		$expected = 2;
		$this->assertEquals($expected, $result);
		$this->assertNotEmpty($this->Ad->data);
		
		$result = $this->Ad->serve('/First Ad/1');
		$expected = null;
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 * with model configured virtualFields
	 */
	public function testServeVirtualFields() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:year/:month/:day/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('year', 'month', 'day', 'title', 'id'),
			'year' => '\d{4}',
			'month' => '([1-9]|10|11|12)',
			'day' => '(0?[1-9]|[12]\d|3[01])',
			'id' => '[0-9]+'
		));
		
		$this->Ad->virtualFields = array(
			'year' => "YEAR(`{$this->Ad->alias}`.`created`)",
			'month' => "MONTH(`{$this->Ad->alias}`.`created`)",
			'day' => "DAYOFMONTH(`{$this->Ad->alias}`.`created`)"
		);
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:year/:month/:day/:title/:id',
			'fields' => array('year', 'month', 'day', 'title', 'id'),
			'recursive' => false
		));
		
		$result = $this->Ad->serve('/2007/3/18/Second Ad/2');
		$expected = 2;
		$this->assertEquals($expected, $result);
		
		// test a wrong uri
		$result = $this->Ad->serve('/2007/5/18/Second Ad/2');
		$expected = null;
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 * with a longer URL than configured as a route (nonexistant uri)
	 */
	public function testServeNonExistantUri() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id'),
			'recursive' => false
		));
		
		$result = $this->Ad->serve('/Second Ad/2/more/path/parts');
		$expected = null;
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 * With the URI parts in the wrong order
	 */
	public function testServeWrongOrder() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		// serve in the wrong order
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id'),
			'recursive' => false
		));
		
		$result = $this->Ad->serve('/2/Second Ad');
		$expected = null;
		$this->assertEquals($expected, $result);
		
		// config 'fields' in the wrong order
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('id', 'title'),
			'recursive' => false
		));
		
		$result = $this->Ad->serve('/Second Ad/2');
		$expected = null;
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 * With an invalid argument
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testServeInvalidArgument() {
		$this->Ad = new Advertisement();
		
		Router::connect('/:title/:id', array(
			'controller' => 'pages',
			'action' => 'view'
		), array(
			'pass' => array('title', 'id'),
			'id' => '[0-9]+'
		));
		
		$this->Ad->Behaviors->load('TestRoutable', array(
			'route' => '/:title/:id',
			'fields' => array('title', 'id'),
			'recursive' => false
		));
		
		$this->assertNull($this->Ad->serve(new stdClass()));
		$this->assertEmpty($this->Tree->data);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 * with recursive option
	 */
	public function testServeRecursive() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/*',
			'fields' => 'name',
			'recursive' => true
		));
		
		$this->Tree->save(array(
			'name' => '1.1.1.1.1'
		));
		
		$result = $this->Tree->serve('/1.%20Root/1.1/1.1.1/1.1.1.1/1.1.1.1.1');
		$expected = 5;
		
		$this->assertEquals($expected, $result);
		$this->assertNotEmpty($this->Tree->data);
	}
	
	/**
	 * Tests RoutableBehavior::serve
	 * with recursive option and more than 1 field in the fields option
	 */
	public function testServeRecursiveExtraField() {
		$this->Tree = new FlagTree();
		$this->Tree->order = null;
		$this->Tree->initialize(4, 1, null, null);
		
		Router::connect('/:id/*', array(
			'controller' => 'pages',
			'action' => 'view'
		));
		
		$this->Tree->Behaviors->load('TestRoutable', array(
			'route' => '/:id/*',
			'fields' => array('id', 'name'),
			'recursive' => true
		));
		
		$this->Tree->save(array(
			'name' => '1.1.1.1.1'
		));
		
		$result = $this->Tree->serve('5/1.%20Root/1.1/1.1.1/1.1.1.1/1.1.1.1.1');
		$expected = 5;
		
		$this->assertEquals($expected, $result);
		$this->assertNotEmpty($this->Tree->data);
	}
	
}
?>