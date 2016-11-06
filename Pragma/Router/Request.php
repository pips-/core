<?php
namespace Pragma\Router;

class Request{
	protected $path = '';
	protected $method = '';

	private static $request = null;//singleton

	public function __construct(){
		$this->path = trim(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
		$this->path = str_replace(array('/index.php', '/index.html'), '', $this->path);
		$this->path = str_replace(array('/admin.php', '/admin.html'), '/admin', $this->path);
		$this->path = !empty($this->path) ? $this->path.($this->path=='/admin'?'/':'') : '/';

		$this->method = strtolower($_SERVER['REQUEST_METHOD']);

		if(!empty($this->method) && $this->method == 'post'){//we need to check _METHOD
			if(!empty($_POST['_METHOD'])){
				$verb = strtolower($_POST['_METHOD']);
				switch($verb){
					case 'delete':
					case 'put':
					case 'patch':
						$this->method = $verb;
						break;
				}
			}
		}
	}

	public static function getRequest(){
		if(is_null(self::$request)){
			self::$request = new Request();
		}

		return self::$request;
	}

	public function getPath(){
		return $this->path;
	}

	public function getMethod(){
		return $this->method;
	}
}