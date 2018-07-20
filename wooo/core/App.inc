<?php
namespace wooo\core;

use wooo\core\exceptions\NotFoundException;
use wooo\core\exceptions\AccessDeniedException;
use wooo\core\exceptions\InternalException;

final class App {
	private static $instance;
	
	/**
	 * @var Config
	 */
	private $config;
	
	/**
	 * @var Scope
	 */
	private $scope;

	/**
	 * @var Session
	 */
	private $session;

	/**
	 * @var Request
	 */
	private $req;
	
	/**
	 * @var Response
	 */
	private $res;
	
	private $appPath;
	
	private $appRoot;
	
	private $appBasePath;
	
	/**
	 * @var ILog
	 */
	private $log;
	
	public static function init($appPath, $config = [], $di = []) {
		if (!self::$instance) {
			self::$instance = new App($appPath, $config, $di);
			self::$instance->log = new Log();
			self::$instance->scope = new Scope(self::$instance->config, $di);
			self::$instance->session = Session::instance(self::$instance->config);
			self::$instance->req = Request::instance(self::$instance->config);
			self::$instance->res = Response::instance(self::$instance->config);
		}
		return self::$instance;
	}
	
	public static function scope() {
		if (self::$instance) {
			return self::$instance->getScope();
		}
		return null;
	}
	
	public static function appPath() {
		if (self::$instance) {
			return self::$instance->appPath;
		}
		return null;
	}
	
	public static function appRoot() {
		if (self::$instance) {
			return self::$instance->appRoot;
		}
		return null;
	}
	
	public static function appBase() {
		if (self::$instance) {
			return self::$instance->appBasePath;
		}
		return null;
	}
	
	private function __construct($appPath, $config, $di) {
		$this->appPath = $appPath;
		$this->config = new Config($config);
		$this->appRoot = $this->config->get('appRootPath', str_replace($_SERVER['DOCUMENT_ROOT'], '', $appPath));
		$host  = $_SERVER['HTTP_HOST'];
		$schema = isset($_SERVER['HTTPS']) ? 'https' : 'http';
		$this->appBasePath = "$schema://$host$this->appRoot";
		
		$INCLUDE_PATH = 
			get_include_path().PATH_SEPARATOR.
			realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..').PATH_SEPARATOR.
			$appPath;
		
		$PATH = $this->config->get('PATH');
		if ($PATH) {
			if (is_array($PATH)) {
				foreach ($PATH as $P) {
					$INCLUDE_PATH = $INCLUDE_PATH.PATH_SEPARATOR.$P;
				}
			} else {
				$INCLUDE_PATH = $INCLUDE_PATH.PATH_SEPARATOR.$PATH;
			}
		}
		set_include_path($INCLUDE_PATH);	
	}
	
	public function log(ILog $log) {
		$this->log = $log;
	}
	
	public function session(ISessionHandler $handler) {
		$this->session->override($handler);
		return $this;
	}
	
	public static function sysLog(): ILog {
		return self::$instance->log;
	}
	
	/**
	 * @return Scope
	 */
	private function getScope() {
		return $this->scope;
	}
	
	private function run($module) {
		$handler = require $module;
		try {
			$handler($this->config, $this->scope, $this->req, $this->res);
		} catch (NotFoundException $e) {
			$this->res->setStatus(404)->send($e->getMessage());
		} catch (AccessDeniedException $e) {
			$this->res->setStatus(403)->send($e->getMessage());
		} catch (InternalException $e) {
			if ($e->getPrevious()) {
				$this->log->error($e->getPrevious());
			}
			$this->res->setStatus(500)->send($e->getMessage());
		} catch (\Throwable $e) {
			$this->log->error($e);
			$this->res->setStatus(500)->send($e->getMessage());
		}
	}

	public function use(string $arg0, string $arg1 = null, string $arg2 = null) {
		$path = $arg0;
		$module = $arg1;
		$method = $arg2;
		if (!$module) {
			$module = $path;
			$path = null;
		}
		
		if ($path) {
			if (!$this->req->checkPath($path)) {
				return $this;
			}
			$this->req->parsePath($path);
		}
		
		if ($method) {
			if ($this->req->getMethod() != $method) {
				return $this;
			}
		}
		
		if ($module) {
			$this->run($module);
		}
		
		return $this;
	}
	
	public function dispatch(array $map) {
		foreach ($map as $path => $handler) {
			$method = null;
			if (is_array($handler)) {
				$method = isset($handler["method"]) ? $handler["method"] : null;
				$handler = isset($handler["handler"]) ? $handler["handler"] : null;
			}
			if (is_string($handler)) {
				if (is_string($path)) {
					$this->use($path, $handler, $method);
				} else {
					$this->use($handler);
				}
			}
		}
		return $this;
	}

	public function get(string $arg0, string $arg1 = null) {
		return $this->use($arg0, $arg1, 'GET');	
	}
	
	public function post(string $arg0, string $arg1 = null) {
		return $this->use($arg0, $arg1, 'POST');
	}
	
	public function delete(string $arg0, string $arg1 = null) {
		return $this->use($arg0, $arg1, 'DELETE');
	}

	public function put(string $arg0, string $arg1 = null) {
		return $this->use($arg0, $arg1, 'PUT');
	}
	
	public function notFound() {
		Response::instance()->setStatus(404)->send("Page not found!");
	}
}