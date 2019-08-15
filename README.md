# [WOOO FRAMEWORK](https://www.wooo.dev)
PHP7 framework

## Overview

Wooo is a lightweight web application framework written in PHP7. It implements a chain-of-responsibility design pattern for request handling. The framework provides IoC container, DBAL, ORM and basic authentication out of the box.

## Quick start

Define a contract

```php
namespace my\app;

interface IComponent {
  public function doSomething(string $v): string;
}
```

Write the application component

```php
namespace my\app;

class MyComponent implements IComponent {
  private $prefix = '';
  public function __construct(string $prefix)
  {
    $this->prefix = $prefix;
  }
  
  public function doSomething(string $v): string
  {
    return $this->prefix . $v;
  }
}
```

Initialize application as an IoC container

```php
require './vendor/autoload.php';
use wooo\core\App;

new App(
/*
 * explicitely specified app path
 * can be ommited
 */
  realpath(__DIR__),
  // configuration settings
  ['THE_PREFIX' => 'Hello, '],
  // IoC specification
  [
    'my\\app\\IComponent' => [
      'module' => 'my\\app\\MyComponent',
      // injection of configuration into constructor argument
      'args' => ['${THE_PREFIX}']
    ]
  ]
);
```

Create some request handling middlewares

```php
require './vendor/autoload.php';
use wooo\core\App;
use wooo\core\Request;
use wooo\core\Response;
use my\app\IComponent;

(new wooo\core\App(
      realpath(__DIR__),
      ['THE_PREFIX' => 'Hello, '],
      [
        'my\\app\\IComponent' => [
          'module' => 'my\\app\\MyComponent',
          'args' => ['${THE_PREFIX}']
        ]
      ]
    )
  )->use(
    /*
     * core and application components from IOC container
     * are passed as arguments according to middleware signature
     */
    function (IComponent $com, Request $req) {
      /*
       * set custom request data to pass through chain of middlewares
       */
      $req->param = $com->doSomething($req->getQuery()->param);
    }
  )->get(
    // multipath routing
    ['/', '/:code'],
    function (Request $req, Response $res) {
      $res->send(
        'This is a result of ' .
        ($req->getParameters()->code ?? 'no code') . ' is:'  .
        $req->param
      );
    }
  )->post(
    '/:fn',
    function (Request $req, Response $res) {
      file_put_contents(
        __DIR__ . '/' .
        $req->getParameters()->fn,
        $req->param
      );
      $res->redirect('/');
    }
  );
```

For the detailed information please visit the framework [documentation site](https://www.wooo.dev/en/docs).