RoutableBehavior
======================

This CakePHP model behavior may help you span the bridge between your application
routing and your data. Your models can be made aware of the route that they can
facilitate through matching your route's parameters with your model's schema.
This will enable the behavior to do most of the work for you, it can even help you route
your hierarchically structured data and will help you serve the right data to the user
by providing a valid URI.

----------


Examples
---------

### Basic route

Let's say you have a Page model with a `slug` field and you would set up your
route to look like this:

```
Router::connect('/:slug/:id', array(
	'controller' => 'pages',
	'action' => 'view'
), array(
	'pass' => array('slug', 'id'),
	'slug' => '[0-9a-z][-a-z0-9]*',
	'id' => '[0-9]+'
));
```

All you have to do now is setup the RoutableBehavior to match the same routing
template and parameters. Make sure the `fields` setting match the parameters
as in the route and the columns as defined in the model's schema.

```
class Page extends AppModel {
	
	public $actsAs = array(
		'Routable' => array(
			'route' => '/:slug/:id',
			'fields' => array('slug', 'id')
		)
	);

}
```

Doing this will enable you to retrieve a list of URIs or URLs using `generateUriList`
or `generateUrlList` and allow you to parse existing URIs using `serve`. The behavior
will also add URIs or URLs (depending on the `full` setting) to all retrieved data
under the fieldname defined in the `virtual` setting.

Here's an example of using `serve` to retrieve the data you want:

```
class PagesController extends AppController {
	
	public function view($slug, $id) {
		if (!$this->Page->serve($this->request->here)) {
			throw new NotFoundException();
		}

		$this->set('data', $this->Page->data);
	}

}
```

**Tip:** See the [<i class="icon-share"></i> Quick API rundown](#quick-api-rundown) for a
description of the different methods and settings available.

### Routing virtual fields

When you have parameters in your route that have to match a specific format that is
not directly available from your model's data, you can leverage virtual fields to
still make it happen.

Let's say you have a Article model with a `published` timestamp field and a `slug` field
but you want your route to look like the following:

```
Router::connect('/articles/:year/:month/:day/:slug', array(
	'controller' => 'articles',
	'action' => 'view'
), array(
	'pass' => array('year', 'month', 'day', 'slug'),
	'year' => '\d{4}',
	'month' => '([1-9]|10|11|12)',
	'day' => '(0?[1-9]|[12]\d|3[01])'
));
```

Setting up your RoutableBehavior and Article model like demonstrated below will
sort this out.

```
class Article extends AppModel {
	
	public $actsAs = array(
		'Routable' => array(
			'route' => '/articles/:year/:month/:day/:slug',
			'fields' => array('year', 'month', 'day', 'slug')
		)
	);

	public $virtualFields = array(
		"year" => "YEAR(`Article`.`published`)",
		"month" => "MONTH(`Article`.`published`)",
		"day" => "DAYOFMONTH(`Article`.`published`)"
	);

}
```

### Routing hierarchal data

But what if my route looks like this:

```
Router::connect('/*', array(
	'controller' => 'pages',
	'action' => 'view'
));
```

This is the actual problem that this behavior was meant to solve.
Setting it up with the `recursive` setting set to `true` will enable
the behavior to automatically build a path to the intended record
and can even be combined with other parameters as long as the
field used to build the path is defined lastly in the `fields` setting.

Here we have a Page model with `slug` field and a attached TreeBehavior
indicating that the pages have a hierarchal structure. The RoutableBehavior
will make use of the `parent_id` field by default.

**Note:** The RoutableBehavior does not depend on the TreeBehavior for
these kind of routes and you may specify a different field for `parent_id`
using the `parent` setting.

```
class Page extends AppModel {
	
	public $actsAs = array(
		'Tree',
		'Routable' => array(
			'route' => '/*',
			'fields' => 'slug',
			'recursive' => true
		)
	);

}
```

Now let's take a look at the view action of the pages controller.
Retrieving the right page the user has requested is now as
simple as calling `serve`.

```
class PagesController extends AppController {
	
	public function view() {
		if (!$this->Page->serve(func_get_args())) {
			throw new NotFoundException();
		}

		$this->set('data', $this->Page->data);
	}

}
```

Quick API Rundown
---------

### Settings
|Setting|Type|Description|
|:------|:---|:----------|
|route|string|Set this to the path of the route as defined in routes.php, the behavior will use this route to build URIs.|
|scope|string or array|Set this to the fieldnames of the data that needs to be passed to the route, make sure it matches the same order as defined in the route. The last field will be used to build /parent/child URIs when the `recursive` setting is set to true.
|cascadingScope|boolean|When retrieving records with parent records setting this to `true` will require ancestral (parent) records to pass the `scope` setting, otherwise the record will not be considered.|
|recursive|boolean|If set to true the last field specified in the `fields` setting will be used to build paths to records with parent/child relationships.|
|virtual|string|When the behavior is adding URIs to all retrieved data, this setting will define its fieldname. Default is `routable`.|
|parent|string|Fieldname of the parent key. Leave undefined and `parent_id` will be assumed when the `recursive` setting is set to `true`.|
|link|string|If this setting is set to a existing field that contains a URI of some sort, the virtual field will be replaced by the value of this one (if not empty), so no URI will be generated by the behavior.|
|home|string|If this setting is set to a existing field which indicates whether or not this record represents the homepage, the virtual field will be replaced by a slash / and so no URI will be generated by the behavior.
|full|boolean|Set this to `true` to enable the behavior to always prepend the full base URL to the URI, making it a URL.|

### Methods
|Method|Arguments|Return|Description|
|:-----|:--------|:-----|:----------|
|generateUriList|array $conditions|array|Finds uniform resource identifiers|
|generateUrlList|array $conditions|array|Finds uniform resource locators|
|serve|array or string $uri|int or null|Parses the provided URI and will return a ID when it has found a match|

Disclaimer
---------
This behavior needs improving, it has not seen a lot of real world use.
Since Cake's routing is pretty powerful it may not cover all use cases.
One small drawback for example is that you have to keep the `route` setting
the same as the route templates defined in routes.php.

Installation
---------
Drop RoutableBehavior.php in your application's app/Model/Behavior folder.
Tested for CakePHP 2.3 and above.

License
---------
The MIT License (MIT)

Copyright (c) 2014 Max van Holten

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.