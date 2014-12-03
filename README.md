phpREST - A simple utility library to send and receive HTTP requests
====================================================================

Main components
---------------

### Client ###

The `Client` class help you to execute HTTP request.


### Server ###

The `Server` class allows you to receive and process incoming HTTP requests.

*TODO: Router class for REST-style routes*


### Validator ###

Utility class for form validation. Uses [filter_var_array](http://php.net/manual/en/function.filter-var-array.php) to filter
form values.

Available filters (see also [filter.constants](http://php.net/manual/en/filter.constants.php))
```
'PASSWORD'       => FILTER_VALIDATE_PASSWORD,
'USERNAME'       => FILTER_VALIDATE_USERNAME,
'USERNAME_CHARS' => FILTER_VALIDATE_USERNAME_CHARS,
'IPV4'           => FILTER_VALIDATE_IPV4,
'IPV6'           => FILTER_VALIDATE_IPV6,
'URLPATH'        => FILTER_VALIDATE_URLPATH,
'URLQUERY'       => FILTER_VALIDATE_URLQUERY,
'MIN_LENGTH'     => FILTER_VALIDATE_MIN_LENGTH,
'MAX_LENGTH'     => FILTER_VALIDATE_MAX_LENGTH,
'LENGTH'         => FILTER_VALIDATE_LENGTH,
'DATE_RANGE'     => FILTER_VALIDATE_DATE_RANGE,
'DATE_START_END' => FILTER_VALIDATE_DATE_START_END,
'RESOURCE'       => FILTER_VALIDATE_RESOURCE,
'INTZERO'        => FILTER_VALIDATE_INTZERO,
'BOOLEAN'        => FILTER_VALIDATE_BOOLEAN,
'INT'            => FILTER_VALIDATE_INT,
'FLOAT'          => FILTER_VALIDATE_FLOAT,
'IDENTIFIER'     => FILTER_VALIDATE_IDENTIFIER,
'LANGCODE'       => FILTER_VALIDATE_LANGCODE,
'REQUIRED'       => FILTER_VALIDATE_REQUIRED,
'IP'             => FILTER_VALIDATE_IP,
'URL'            => FILTER_VALIDATE_URL,
'EMAIL'          => FILTER_VALIDATE_EMAIL,
'REGEXP'         => FILTER_VALIDATE_REGEXP,
```


### Localizer ###

* Loads language variables from a YAML, JSON or PHP file and caches generated files
* Allows you to query and insert language variables


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
