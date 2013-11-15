phpREST - A simple utility library to send and receive HTTP requests
====================================================================

Main components
---------------

### Client ###

The `Client` class help you to execute HTTP request.


### Server ###

The `Server` class allows you to receive and process incoming HTTP requests.

*TODO: Router class for REST-style routes*


### Record ###

Utility class for form validation


Example
-------

```php
$req = new REST\Client('http://sample.api.com/api.php');
$result = $req->get([
	'param' => 'value'
]);
```

License
-------

![Zeyon](http://www.zeyon.net/assets/img/frame/headerlogo.png)

Copyright (C) 2008 - 2013 [Zeyon Technologies Inc.](http://www.zeyon.net)

This work is licensed under the GNU Lesser General Public License (LGPL) which should be included with this software. You may also get a copy of the GNU Lesser General Public License from [http://www.gnu.org/licenses/lgpl.txt](http://www.gnu.org/licenses/lgpl.txt).
