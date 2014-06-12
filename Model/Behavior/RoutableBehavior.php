<?php
App::uses('Hash', 'Utility');
App::uses('Router', 'Routing');

/**
 * This behavior will enable the data of
 * a model to be accessable through routes
 */
class RoutableBehavior extends ModelBehavior {
	
	/**
	 * Default config for a single Model.
	 *
	 * @var array
	 */
	protected $_defaults = array(
		'route' => null,
		'fields' => null,
		'scope' => null,
		'cascadingScope' => false,
		'recursive' => true,
		'virtual' => 'routable',
		'parent' => null,
		'link' => null,
		'home' => null,
		'full' => false
	);
	
	/**
	 * Indicates whether to generate full base urls.
	 * Where null indicates neutral.
	 * 
	 * Will serve as an override to the same option
	 * in the user model config.
	 *
	 * @var boolean|null
	 */
	protected $_full = null;
	
	/**
	 * Returns array pointers to records grouped by model,
	 * so you can modify the original data in a more accessible
	 * form (rather than through nested associations).
	 *
	 * Example: {Model}.{primaryKey}.(record)
	 *
	 * @param array $results
	 * @param string $primaryKey
	 * @param array $groupKeys
	 * @return array
	 */
	protected function _identify(array &$results, $primaryKey = 'id', array $groupKeys = array()) {
		$separator = '.';
		$pointers = array();
		$groups = array_merge(App::objects('Model'), $groupKeys);		
		$map = Hash::flatten($results, $separator);
		
		// targeting primaryKey paths
		$target = $separator . $primaryKey;
		$target_length = strlen($target);
		
		foreach($map as $path => $value) {
			$length = strlen($path);
			$target_index = $length - $target_length;
			
			if($target_index < 0) {
				$target_index = 0;
			}
			
			// check if path ends with primaryKey target
			if(strpos($path, $target) !== $target_index) {
				continue;
			}
			
			// the path has been identified as being a primaryKey
			$parts = explode($separator, $path);
			$count = count($parts);
			
			// loop over the exploded path
			for($i = $count - 1; $i; $i--) {
				$group = $parts[$i];
				
				// see which model this primaryKey belongs to
				if(!in_array($group, $groups, true)) {
					continue;
				}
				
				// if no pointer yet, create one
				if(empty($pointers[$group])) {
					$pointers[$group] = array();
				}
				
				// move up the path parts so we have the path of the entire record
				$recordPath = array_splice($parts, 0, $count - 1);
				
				// refer to the results array
				$pointer =& $results;
				
				// update reference until refering to the targeted record
				foreach($recordPath as $recordPathPart) {
					$pointer =& $pointer[$recordPathPart];
				}
				
				// add pointer of record by adding the reference by primaryKey of the targeted record
				$pointers[$group][$pointer[$primaryKey]] =& $pointer;
				break;
			}
		}
		
		return $pointers;
	}
	
	/**
	 * Returns the CakeRoute by template.
	 *
	 * @param string $template
	 * @return CakeRoute|null
	 */
	protected function _matchCakeRoute($template) {
		$route = null;
		
		foreach(Router::$routes as $candidate) {
			if($candidate->template == $template) {
				$route = $candidate;
				break;
			}
		}
		
		return $route;
	}
	
	/**
	 * Will run a ancestry query on the provided model,
	 * starting from the provided record ID the query will
	 * run upwards across all ancestors to retrieve all ID's.
	 *
	 * @param Model $Model
	 * @param int $id
	 * @return array|null
	 */
	protected function _getAncestry(Model $Model, $id) {	
		$parent_field = $this->settings[$Model->alias]['parent'];
		
		$result = $Model->query("
			SELECT GROUP_CONCAT(@parent_id :=(
				SELECT	{$parent_field}
				FROM	{$Model->table}
				WHERE	{$Model->primaryKey} = @parent_id
			)) AS ancestry
			FROM (SELECT @parent_id := {$id}) vars
			STRAIGHT_JOIN {$Model->table}
			WHERE @parent_id IS NOT NULL
		");
		
		if($ancestry = Hash::get($result, "0.0.ancestry")) {
			$ancestry = explode(',', $ancestry);
		}
		
		return $ancestry;
	}
	
	/**
	 * Gets all the fields required for virtualization.
	 *
	 * @param Model $Model
	 * @param boolean $primaryKey
	 * @param boolean $optionKeys
	 * @return array
	 */
	protected function _getFields(Model $Model, $primaryKey = true, $optionKeys = true) {
		$config = $this->settings[$Model->alias];
		
		$fields = $config['fields'];
		
		// add primary key
		if($primaryKey) {
			$fields[] = $Model->primaryKey;
		}
		
		// add configured fields
		if($optionKeys) {
			foreach(array('parent', 'link', 'home') as $field) {
				if($config[$field]) {
					$fields[] = $config[$field];
				}
			}
		}
		
		// add the alias to non-virtual fields
		$virtualFields = array();
		
		if(!empty($Model->virtualFields)) {
			$virtualFields = $Model->virtualFields;
		}
		
		foreach($fields as &$field) {
			if(!in_array($field, $virtualFields, true)) {
				$field = "{$Model->alias}.{$field}";
			}
		}
		
		return array_unique($fields);
	}
	
	/**
	 * Retrieves the URI path to the provided $record using
	 * the $field parameter as path piece. The $pointers array
	 * should contain references from self::_identify of the
	 * dataset the provided $record originated from.
	 *
	 * This method will return null if the provided $record
	 * has parents that do not match the scope parameter if
	 * cascadingScope is set to true.
	 *
	 * @param Model $Model
	 * @param array $pointers
	 * @param array $record
	 * @param string $field
	 * @return array|null
	 */
	protected function _getRecursiveRoutePath(Model $Model, array &$pointers, array &$record, $field) {
		$path = array();
		$config = $this->settings[$Model->alias];
		
		// check whether record has a parent field
		if(!$config['parent'] || !array_key_exists($config['parent'], $record)) {
			return $path;
		}
		
		$parent_id = $record[$config['parent']];
		
		// check whether record has the recursor field
		if(!isset($record[$field])) {
			return $path;
		}
		
		$path[] = $record[$field];
		
		// check pointers for parents
		while($parent = Hash::get($pointers, "{$Model->alias}.{$parent_id}")) {
			$path[] = $parent[$field];
			$parent_id = $parent[$config['parent']];
		}
		
		$path = array_reverse($path);
		
		// if not all parents are found attempt a ancestry query
		if($parent_id) {
			$cascading = ($config['scope'] && $config['cascadingScope']);
			
			if($ancestors = $this->_getAncestry($Model, $record[$Model->primaryKey])) {
				$scope = null;
				
				// adds the record scope to the ancestry lookup if the scope set to cascade
				if($cascading) {
					$scope = $config['scope'];
				}
				
				// get all records that match the ancestry of the missing parent
				$tree_records = $Model->find('all', array(
					'conditions' => array(
						array("{$Model->alias}.{$Model->primaryKey}" => $ancestors),
						$scope
					),
					'fields' => $this->_getFields($Model),
					'recursive' => -1
				));
				
				// continue to the next record if the ancestry is incomplete
				if(count($tree_records) !== count($ancestors)) {
					return null;
				}
				
				// group by model and id
				$tree_pointers = $this->_identify($tree_records, $Model->primaryKey);
				
				$tree = array();
				
				// order the ancestors into a tree path
				foreach($ancestors as $ancestor_id) {
					$tree[] = $tree_pointers[$Model->alias][$ancestor_id][$field];
				}
				
				// merge with earlier path
				$tree = array_reverse($tree);
				$path = array_merge($tree, $path);
			}
			else {
				$path = array();
			}
		}
		
		return $path;
	}
	
	/**
	 * Initiate behavior for the model using specified settings.
	 *
	 * Available settings:
	 *
	 * - route: (string) set this to the path of the route as defined in routes.php,
	 *   the behavior will use this route to build URIs.
	 *
	 * - fields: (array or string) set this to the fieldnames of the data that needs to
	 *   be passed to the route, make sure it matches the same order as defined in the route.
	 *   The last field will be used to build /parent/child URIs when the 'recursive' setting
	 *   is set to true.
	 *
	 * - scope: (array, optional) when generating a list of URIs or URLs for a specific model,
	 *   this setting will make sure all the data retrieved passes the requirements.
	 *
	 * - cascadingScope: (boolean, optional) when retrieving records with parent records setting
	 *   this to true will require ancestral (parent) records to pass the 'scope' setting,
	 *   otherwise the record will not be considered.
	 * 
	 * - recursive: (boolean, optional) if set to true the last field specified in the 'fields'
	 *   setting will be used to build paths to records with parent/child relationships.
	 *
	 * - virtual: (string, optional) when the behavior is adding URIs to all retrieved data, this
	 *   setting will define its fieldname. Default is 'routable'.
	 *
	 * - parent: (string, optional) fieldname of the parent key. Leave undefined and
	 *   'parent_id' will be assumed when the 'recursive' setting is set to true.
	 *
	 * - link: (string, optional) if this setting is set to a existing field that contains a URI
	 *   of some sort, the virtual field will be replaced by the value of this one (if not empty),
	 *   so no URI will be generated by the behavior.
	 *
	 * - home: (string, optional) if this setting is set to a existing field which indicates whether
	 *   or not this record represents the homepage, the virtual field will be replaced by a slash /
	 *   and so no URI will be generated by the behavior.
	 *
	 * - full: (boolean, optional) set this to true to enable the behavior to always prepend the
	 *   full base URL to the URI, making it a URL.
	 *
	 * @param Model $Model instance of model
	 * @param array $config array of configuration settings.
	 * @return void
	 */
	public function setup(Model $Model, $config = array()) {
		if(!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $this->_defaults;
		}
		
		$config = array_merge($this->settings[$Model->alias], $config);
		
		if(!empty($config['fields'])) {
			if(is_string($config['fields'])) {
				$config['fields'] = (array)$config['fields'];
			}
			
			$config['fields'] = array_values($config['fields']);
			$config['fields'] = array_unique($config['fields']);
		}
		else {
			$error = __d('admin', 'Fields for %s of model %s were not configured', get_class($this), $Model->alias);
			trigger_error($error, E_USER_ERROR);
		}
		
		if(isset($config['route'])) {
			if($route = $this->_matchCakeRoute($config['route'])) {
				$config['route'] = $route;
				
				if($config['recursive']) {
					$config['recursive'] = substr($route->template, -1) == '*';
				}
			}
			else {
				$error = __d('admin', 'Route %s for %s of model %s could not be found', $config['route'], get_class($this), $Model->alias);
				trigger_error($error, E_USER_ERROR);
			}
		}
		else {
			$error = __d('admin', 'Route for %s of model %s was not configured', get_class($this), $Model->alias);
			trigger_error($error, E_USER_ERROR);
		}
		
		if($config['recursive'] && !isset($config['parent'])) {
			$config['parent'] = 'parent_id';
		}
		
		$this->settings[$Model->alias] = $config;
	}
	
	/**
	 * Model afterFind callback, here the virtual field
	 * will be added to all data found.
	 *
	 * @param Model $Model
	 * @param array $results
	 * @param boolean $primary
	 * @return array
	 */
	public function afterFind(Model $Model, $results, $primary = false) {
		return $this->virtualizeRoutes($Model, $results);
	}
	
	/**
	 * Will add the URL as $config['virtual'] to each
	 * record in the provided data.
	 *
	 * @param Model $Model
	 * @param array $record
	 * @return array
	 */
	public function virtualizeRoutes(Model $Model, array $data) {
		$config = $this->settings[$Model->alias];
		$pointers = $this->_identify($data, $Model->primaryKey);
		$keys = array_combine($config['fields'], $config['fields']);
		
		if(empty($pointers[$Model->alias])) {
			return $data;
		}
		
		// checks whether the data has all the required fields to virtualize
		$test = $pointers[$Model->alias][array_rand($pointers[$Model->alias])];
		$fields = array_intersect_key($test, $keys);
		
		if(count($fields) !== count($config['fields'])) {
			return $data;
		}
		
		// pop out the recursor field from the rest of the route's fields
		if($config['recursive']) {
			$recursor = array_pop($keys);
		}
		
		// honors the internal override and the user config for generating full base urls or not
		$full_base = $this->_full || (is_null($this->_full) && $config['full']);
		
		foreach($pointers[$Model->alias] as $id => &$record) {
			$record[$config['virtual']] = null;
			
			// check if record has a link override
			if($config['link'] && !empty($record[$config['link']])) {
				$record[$config['virtual']] = $record[$config['link']];
				continue;
			}
			
			// check if record has a homepage override
			if($config['home'] && !empty($record[$config['home']]) && $record[$config['home']]) {
				$record[$config['virtual']] = '/';
				continue;
			}
			
			$path = array();
			
			// handle recursive routes
			if($config['recursive']) {
				$path = $this->_getRecursiveRoutePath($Model, $pointers, $record, $recursor);				
				$fields = array_intersect_key($record, $keys);
			}
			else {
				$fields = array_intersect_key($record, $keys);
			}
			
			// having a null $path will invalidate the route
			if(!is_null($path)) {
				$record[$config['virtual']] = Router::url($config['route']->defaults + $fields + $path, $full_base);
			}
		}
		
		return $data;
	}
	
	/**
	 * Transforms and maps the provided id's
	 * to uniform resource identifiers.
	 * 
	 * @param array $conditions
	 * @return array Looking like array($id => $uri)
	 */
	public function generateUriList(Model $Model, $conditions = null) {
		$config = $this->settings[$Model->alias];
		
		// join default conditions
		if(is_array($config['scope'])) {
			if($conditions) {
				$conditions = array($conditions, $config['scope']);
			}
			else {
				$conditions = $config['scope'];
			}
		}
		
		// unless set to generate full URLs enable override to generate URIs
		if($this->_full !== true) {
			$this->_full = false;
		}
		
		// queries the strictly necessary data to virtualize
		$data = $Model->find('all', array(
			'conditions' => $conditions,
			'fields' => $this->_getFields($Model),
			'recursive' => -1
		));
		
		// disable override
		$this->_full = null;
		
		return Hash::combine($data, "{n}.{$Model->alias}.{$Model->primaryKey}", "{n}.{$Model->alias}.{$config['virtual']}");
	}
	
	/**
	 * Transforms and maps the provided id's
	 * to uniform resource locators.
	 * 
	 * @param array $conditions
	 * @return array Looking like array($id => $url)
	 */
	public function generateUrlList(Model $Model, $conditions = null) {
		// override to always generate full URLs
		$this->_full = true;
		
		// generate URLs with override enabled
		$urls = $this->generateUriList($Model, $conditions);
		
		// disable override
		$this->_full = null;
		
		return $urls;
	}
	
	/**
	 * Does a reverse lookup of the provided $uri and
	 * configures the $Model->id and $Model->data
	 * with the record that this path points to.
	 * 
	 * Will return the ID or null if no record found.
	 *
	 * @param Model $Model
	 * @param string|array $uri 
	 * @return int|null
	 */
	public function serve(Model $Model, $uri) {		
		if(is_string($uri)) {
			$uri = explode('/', $uri);
			$uri = array_filter($uri, 'strlen');
		}
		
		if(!is_array($uri)) {
			throw new InvalidArgumentException();
		}
		
		$uriStr = '/' . implode('/', $uri);
		$uriArr = array_values($uri);
		
		$config = $this->settings[$Model->alias];
		$keys = $this->_getFields($Model, false, false);
		$query = $matches = array();
		
		// pop out the recursor field
		if($config['recursive']) {
			$recursor = array_pop($keys);
		}
		
		// assign non-recursive URI parts to the configured fields
		foreach($uriArr as $index => $part) {
			$field = array_shift($keys);
			
			if(is_null($field)) {
				if($config['recursive']) {
					break;
				}
				
				return null;
			}
			
			$matches[$field] = $part;
		}
		
		// add recursive or non-recursive conditions
		if($config['recursive'] && $lastPart = array_pop($uriArr)) {
			if($matches) {
				$query['conditions'] = array($matches, array($recursor => $lastPart));
			}
			else {
				$query['conditions'] = array($recursor => $lastPart);
			}
		}
		else {
			$query['conditions'] = $matches;
		}
		
		// add scope
		if(is_array($config['scope'])) {
			$query['conditions'] = array($query['conditions'], $config['scope']);
		}
		
		// get the record that matches the provided URI conditions
		if(!$records = $Model->find('all', $query)) {
			return null;
		}
		
		$Model->create();
		
		// check if the virtual matches the provided URI str
		foreach($records as $record) {
			if($record[$Model->alias][$config['virtual']] == $uriStr) {
				$Model->set($record);
				$Model->id = $record[$Model->alias][$Model->primaryKey];
				break;
			}
		}
		
		return $Model->id;
	}
	
}
?>