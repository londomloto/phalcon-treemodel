<?php

namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT Application
 *
 * @package    KCT Backbone (Application Core)
 * @copyright  PT. KREASINDO CIPTA TEKNOLOGI
 * @author     PT. KREASINDO CIPTA TEKNOLOGI
 * @author     xALPx
 * @author     jokowow
 * @version    1.0
 * @access     public
 */

use Phalcon\Exception,
	Phalcon\DI,
	Phalcon\Registry,
	Phalcon\Assets,
	Phalcon\Mvc\Router,
	Phalcon\Mvc\Application as PhalconApplication,
	Phalcon\Events\Manager as EventsManager,
	//Phalcon\Mvc\Router\Group as RouterGroup,
	Phalcon\Mvc\Model\Transaction\Manager as TxManager,
	Phalcon\Mvc\Model\Manager as ModelsManager,
	Cores\Loader,
	Cores\Assets as KctAsset,
	Cores\Url,
	Cores\RouterGroup,
	Cores\Crypt as KctCrypt,
	Cores\ErrorControl,
	Cores\Text as KctText,
	Cores\Logger,
	Plugins\LoadListeners,
	Plugins\ModelListeners,
	Plugins\DbListeners,
	Plugins\AppListeners;

class Application extends PhalconApplication {

	/**
	 * Variabel untuk menampung konfigurasi aplikasi
	 *
	 * @var     [Phalcon\Config]
	 */
	protected $_config;

	/**
	 * Default constructor	
	 *
	 * @param array $options Konfigurasi aplikasi. Jika tidak diisi,
	 *                       maka system akan menggunakan konfigurasi dari file
	 *                       config
	 */
	public function __construct(array $options = array()) {

		$di	= new DI\FactoryDefault();
		$registry = new Registry();

		if ( ! isset($options['configDir'])) {
			$options['configDir'] = PUBLICPATH.DS.'Configs'.DS;
		}

		if ( ! isset($options['moduleDir'])) {
			$options['moduleDir'] = BASEPATH.DS.Text::masquerade('Apps').DS;
		}

		$this->_config = Config::factory($options['configDir']);

		$registry->modules = array_keys(
			$this->_config->application->modules->toArray()
		);

		$registry->directories = (object)[
			'Cores'      => SYSPATH.DS.'Cores'.DS,
			'Mixins'     => SYSPATH.DS.'Mixins'.DS,
			'Plugins'    => SYSPATH.DS.'Plugins'.DS,
			'Libraries'  => SYSPATH.DS.'Libraries'.DS,
			'Languages'  => SYSPATH.DS.'Languages'.DS,
			'Adapters'   => SYSPATH.DS.'Adapters'.DS,
			'Interfaces' => SYSPATH.DS.'Interfaces'.DS,
			'Filters'    => SYSPATH.DS.'Filters'.DS,
			'Vendors'    => SYSPATH.DS.'Vendors'.DS,
			'Modules'    => $options['moduleDir']
		];

		$di->set('registry', $registry);
		$di->setShared('config', $this->_config);

		parent::__construct($di);
	}

	/**
	 * Inisialisasi awal aplikasi dengan meregister instan aplikasi 
	 * ke dalam register DI (Dependency Injection). Sehingga, aplikasi
	 * berlaku sebagai singleton dan dapat diakses dari mana saja.
	 *
	 * Inisialisasi ini merupakan rutin bootstraping aplikasi paling awal.
	 * Urutan bootstraping ini sangat mandatory, 
	 * dimulai dari Application::_initilaizing(), dan
	 * diakhiri dengan Application::_finalizing()
	 *
	 * @return void
	 */
	protected function _initializing()
	{
		$di		= $this->getDI();
		$config	= $this->_config;
		$em		= new EventsManager();

		$this->setEventsManager($em);
		$di->setShared('app', $this);
	}

	/**
	 * Inisialisasi engine AutoLoader. 
	 *
	 * Autoloader ini nantinya akan merelokasi class - class
	 * yang dipanggil oleh aplikasi secara otomatis, tanpa harus
	 * meng-include class tersebut disetiap script
	 *
	 * @return Phalcon\Loader
	 */
	protected function _initLoader()
	{
		$di         = $this->getDI();
		$em 		= $this->getEventsManager();
		$registry   = $di->get('registry');
		$namespaces = [];
		$bootstraps = [];
		$modules    = [];

		$loader     = new Loader();

		foreach($registry->directories as $dir => $path)
		{
			if ($dir == 'Modules') continue;
			$namespaces[$dir] = str_replace(array(DN, '/'), DS, $path);
		}

		$loader->registerNamespaces($namespaces);
		$loader->register();

		$em->attach('loader', new LoadListeners());
		$em->attach('application:viewRender', new AppListeners());
		
		$loader->setEventsManager($em);

		$di->set('loader', $loader);

		return $loader;
	}

	/**
	 * Insialisasi module - module aplikasi.
	 *
	 * Registrasi semua module yang ada di dalam direktori modules.
	 * Sebagai penanda bahwa sebuah folder dan sub-nya adalah bagian
	 * dari module adalah dengan adanya file Bootstrap.php
	 *
	 * @return void
	 */
	protected function _initModules() {

		$loader     = $this->getDI()->getShared('loader');
		$registry   = $this->getDI()->get('registry');
		$namespaces = [];
		$bootstraps = [];
		$modules    = [];

		foreach($registry->modules as $module)
		{
			$path = $registry->directories->Modules.$module;
			$find = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach($find as $key => $obj)
			{
				if ($obj->getFilename() == 'Bootstrap.php')
				{
					$objPath = $obj->getPath();
					$name    = str_replace($registry->directories->Modules, '', $objPath);
					$name    = str_replace(array(DN, '/'), '\\', $name);
					$objPath = str_replace(array(DN, '/'), DS, $objPath);
					$objPath = preg_replace('/\/$/', '', $objPath).DS;

					$namespaces[$name] = $objPath;
					$bootstraps[$name] = array(
						'root'  => $module,
						'class' => $name.'\Bootstrap'
					);
				}
			}
		}

		$loader->registerNamespaces($namespaces, true);
		$loader->register();
		$this->registerModules($bootstraps);

	}

	/**
	 * Inisialisasi penanganan error, exception dan shutdown
	 *
	 */
	protected function _initErrors()
	{
		$di		= $this->getDI();
		$config	= $this->_config;

		/*register_shutdown_function(
			function() use($di) {
				$control = new ErrorControl($di);
				return $control->handleShutdown();
			}
		);*/

		set_error_handler(
			function($errorCode, $errorMessage, $errorFile, $errorLine) use ($di) {
				$control = new ErrorControl($di);
				return $control->handleError(
					$errorCode,
					$errorMessage,
					$errorFile,
					$errorLine
				);
			}
		);

		set_exception_handler(
			function($e) use ($di) {
				$control = new ErrorControl($di);
				$control->handleException($e);
				return;
			}
		);

	}

	/**
	 * Perbaikan request '_url' dari sistem standar.
	 *
	 * Hal ini dikarenakan sistem berjalan menggunakan metode
	 * routing yang berbeda (adanya pembalikan dan susunan yang
	 * berbeda dari standar yang ada)
	 *
	 * @return void
	 */
	protected function _initUrl()
	{
		$di  = $this->getDI();

		// URL Service
		$url = new Url();
		$url->setBaseUri($this->_config->application->baseUri);
		$url->setPrefix('');

		$request = isset($_REQUEST['_url']) ? $_REQUEST['_url'] : '/';

		if ($request) {
			
			$modules = $this->_config->application->modules->toArray();
			$found = false;

			while(count($modules)) {
				$module   = array_shift($modules);
				$baseuri  = rtrim($module['prefix'], '/').'/';
				$tester   = rtrim($request, '/').'/';
				$segments = explode('/', $tester);
				$segments = array_pad($segments, 3, null);
				$tester   = '/'.$segments[1].'/';
				if ($tester == $baseuri) {
					$found = array(
						'prefix'  => $module['prefix'],
						'baseuri' => $baseuri
					);
					break;
				}
			}

			if ($found) {
				$url->setBaseUri($found['baseuri']);
				$url->setPrefix($found['prefix']);
			}

		}
		
		$di->set('url', $url);
	}

	/**
	 * Inisialisasi engine Router
	 *
	 * Router dirancang agar dapat menangani routing
	 * ke dalam multi-hierarki sebagai berikut:
	 * 		- Unlimited module hirarki
	 * 		- Custom theming
	 * 		- Asset proxy (resource)
	 * 		- Penanganan request dari Dojo, ExtJS
	 * 		
	 * @return void
	 */
	protected function _initRouter()
	{
		$di = $this->getDI();

		$config   = $di->get('config');
		$registry = $di->get('registry');
		$modules  = $config->application->modules;
		$suffix   = $config->application->suffix;
		$suffix   = ! empty($suffix) ? "(".DN."$suffix)?" : $suffix;

		$defaultRouting = $modules[$config->application->defaultRouting]->default;

		$proxyRoute     = '/([a-zA-Z0-9_\-\.\/]{0,})(sys|pub|app|mod|ext|svc)(script|config|style|image|font|vendor|html|error|upload|asset|file)/([a-zA-Z0-9_\-\.\/]{0,})';
		$thumbRoute     = '/([a-zA-Z0-9_\-\.\/]{0,})thumb([0-9]{0,})x([0-9]{0,})/(sys|pub|app|mod|ext|svc)(image|upload|asset|file)/([a-zA-Z0-9_\-\.\/]{0,})';
		
		$router = new Router();
		$router->removeExtraSlashes(true);
		$router->setDefaults((Array)$defaultRouting);

		foreach($registry->modules as $module)
		{
			$path    = $registry->directories->Modules.$module;
			$route   = $modules[$module];
			
			$group = new RouterGroup($route->default->toArray());
			$group->setPrefix($route->prefix);

			// apply baseuri
			$baseuri = $route->prefix;
			$baseuri = rtrim($baseuri, '/').'/';

			$group->setBaseUri($baseuri);

			$group->add('', array(
				'controller' => 'index',
				'action'     => 'index'
			));

			$group->add($proxyRoute, array(
				'action'     => 'proxy',
				'context'    => 2,
				'type'       => 3,
				'path'       => 4
			));

			$group->add($thumbRoute, array(
				'action'     => 'thumb',
				'width'      => 2,
				'height'     => 3,
				'context'    => 4,
				'type'       => 5, // 'image',
				'path'       => 6
			));

			$router->mount($group);

			$find = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach($find as $key => $obj)
			{
				$file = $obj->getFilename();

				if ($file == 'Bootstrap.php')
				{
					$namespace = array();
					$modname   = array();

					$namespace = str_replace($registry->directories->Modules, '', $obj->getPath()).DS.'Controllers';
					$namespace = str_replace(array('\\', '/'), '\\', $namespace);
					$modname   = str_replace('\Controllers', '', $namespace);
					$prefix    = str_replace($module.'\\', '', $modname);
					$prefix    = str_replace(DN, '/', $prefix);
					$prefix    = strtolower(preg_replace('/\/$/', '', $route->prefix).'/'.$prefix);

					$group = new RouterGroup(array(
						'namespace' => $namespace,
						'module'    => $modname
					));
					
					$group->setPrefix($prefix);
					$group->setBaseUri($baseuri);

					$group->add('', array(
						'controller' => 'index',
						'action'	 => 'index'
					));

					$group->add('/:controller'.$suffix, array(
						'controller' => 1,
						'action'	 => 'index'
					));

					$group->add('/:controller/:action'.$suffix, array(
						'controller' => 1,
						'action'	 => 2
					));

					$group->add('/:controller/:action/:params'.$suffix, array(
						'controller' => 1,
						'action'     => 2,
						'params'     => 3
					));

					$group->add($proxyRoute, array(
						'action'     => 'proxy',
						'context'    => 2,
						'type'       => 3,
						'path'       => 4
					));

					$group->add($thumbRoute, array(
						'action'     => 'thumb',
						'width'      => 2,
						'height'     => 3,
						'context'    => 4,
						'type'       => 5, // 'image',
						'path'       => 6
					));

					// print_r($group);
					$router->mount($group);

					/**
					 * Routing untuk xstyle
					 * 
					 */

					$group = new RouterGroup(array(
						'namespace' => $namespace,
						'module'    => $modname
					));

					$group->setPrefix('/app'.$prefix);
					$group->add($proxyRoute, array(
						'action'     => 'proxy',
						'context'    => 2,
						'type'       => 3,
						'path'       => 4
					));

					// print_r($group);
					$router->mount($group);

					/**
					 * Routing untuk Webservice Request
					 * 
					 */
					$api = new RouterGroup(array(
						'namespace' => $namespace,
						'module'    => $modname
					));

					$api->setPrefix('/api'.$prefix);

					/**
					 * GET 	/api/common/user/
					 *
					 * Response server array instance model
					 */
					$api->add('/:controller'.$suffix, array(
						'controller' => 1,
						'action'     => 'index',
						'_identity'  => '',
						'_service'   => 'api',
						'_output'	 => 'json'
					));

					/**
					 * GET 	/api/common/user/1
					 *
					 * Response server instance model
					 */
					$api->add('/:controller/([0-9]+)'.$suffix, array(
						'controller' => 1,
						'_identity'  => 2,
						'_service'   => 'api',
						'_output'    => 'json'
					));

					/**
					 * GET 	/api/common/user/index/
					 *
					 * Response server array instance model
					 */
					$api->add('/:controller/([a-z]+)'.$suffix, array(
						'controller' => 1,
						'action'	 => 2,
						'_identity'  => '',
						'_service'   => 'api',
						'_output'    => 'json'
					));

					/**
					 * GET 	/api/common/user/index/1
					 *
					 * Response server instance model
					 */
					$api->add('/:controller/([a-z]+)/([0-9]+)'.$suffix, array(
						'controller' => 1,
						'action'	 => 2,
						'_identity'  => 3,
						'_service'   => 'api',
						'_output'    => 'json'
					));

					$router->mount($api);
				}
			}

		}

		$di->set('router', $router);

		return $router;
	}

	/**
	 * Inisialisasi Database
	 *
	 * Instantiasi database serta aktivasi beberapa fitur database
	 * seperti profiling (mode: development)
	 *
	 * @return void
	 */
	protected function _initDatabase()
	{
		$di = $this->getDI();
		$em = $this->getEventsManager();

		if (ENVIRONMENT == 'development') {
			$di->setShared('profiler', function(){
				return new \Phalcon\Db\Profiler();
			});
		}

		if ($this->_config->offsetExists('database'))
		{
			$dbs = $this->_config->database->toArray();

			if (count($dbs) > 0)
			{
				foreach($dbs as $name => $config)
				{
					$adapter = '\Phalcon\Db\Adapter\Pdo\\' . $config['adapter'];

					$connection = new $adapter([
						'host'     => $config['host'],
						'port'     => $config['port'],
						'username' => $config['username'],
						'password' => $config['password'],
						'dbname'   => $config['dbname']
					]);

					$em->attach('db', new DbListeners());
					$connection->setEventsManager($em);
					$di->set($name, $connection);

				}
			}
		}
	}

	/**
	 * Inisialisasi Engine Session
	 * 
	 * @return void
	 */
	protected function _initSession()
	{
		$di			= $this->getDI();
		$config		= $this->_config;
		$adapter	= 'Phalcon\Session\Adapter\\'.$config->application->session->adapter;
		$session	= new $adapter($config->application->session->toArray());

		$session->start();
		$di->setShared('session', $session);

		return $session;
	}

	/**
	 * Inisialisasi User Libraries
	 *
	 * Beberapa library diload secara otomatis oleh sistem.
	 * Sisanya, user dapat menentukan library apa saja yang
	 * akan dipanggil secara otomatis ketika proses bootstraping
	 *
	 * @return void
	 */
	protected function _initLibraries()
	{
		$di = $this->getDI();

		// autoload libraries
		$config   = $di->getConfig();
		
		// default autoloads
		$autoload = array(
			'benchmark',
			'auth', 
			'access', 
			'db', 
			'mailer', 
			'device', 
			'security',
			'storage',
			'socket',
			'timezone'
		);

		if ($config->application->offsetExists('autoload')) {
			$autoload = array_merge($autoload, $config->application->autoload->toArray());
		}

		// khusus untuk database
		if (in_array('db', $autoload)) {
			array_splice($autoload, array_search('db', $autoload), 1);
			$this->_initDatabase();
		}

		foreach($autoload as $name) {
			$options = array();
			
			if ($config->application->offsetExists($name)) {
				$options = array_merge($options, $config->application->$name->toArray());
			}

			$class = 'Libraries\\'.ucwords($name);
			$di->setShared($name, call_user_func_array(array($class, 'factory'), array($options)));
		}

	}

	/**
	 * Inisialisasi Engine Cache
	 *
	 * Saat ini, engine cache untuk caching view khususnya
	 * belum dioptimalkan, karena sistem menggunakan mekanisme
	 * sendiri untuk penanganan cache khususnya untuk asset
	 *
	 * @return void
	 */
	protected function _initCache() {

		$di       = $this->getDI();
		$config   = $this->_config;

		$adapter  = '\Phalcon\Cache\Backend\\' . $config->application->cache->adapter;
		$lifetime = $config->application->cache->lifetime;

		$cache    = new $adapter(
			new \Phalcon\Cache\Frontend\Output(array(
				'lifetime' => $lifetime
			)),
			$config->application->cache->toArray()
		);

		$di->set('cache', $cache, true);
	}

	/**
	 * Inisialisasi Event Listener
	 *
	 * Plugin digunakan sebagai event listener dari sebuah
	 * objek yang diobservasi.
	 */
	protected function _initPlugins() {

		/*$di = $this->getDI();
		$em = $this->getEventsManager();

		$em->attach('model', new ModelListeners());

		$modelsManager = new ModelsManager();
		$modelsManager->setEventsManager($em);
		$di->setShared('modelsManager', $modelsManager);*/
	}

	/**
	 * Rutin bootstraping paling akhir.
	 *
	 * Disini hanya men-setup library - library opsional,
	 * seperti fitur transaction, authentication, ACL
	 * 
	 * @return void
	 */
	protected function _finalizing()
	{
		// model transaction isolating
		$di		= $this->getDI();
		$crypt	= new KctCrypt();

		$di->setShared('transaction', function(){
			return new TxManager();
		});

		$di->setShared('assets', new KctAsset($di));
		$di->set('crypt', $crypt);

		// Open authentication integration
		if (isset($this->getDI()->getConfig()->application->oauth)) {
			$oauth = $this->getDI()->getConfig()->application->oauth->toArray();
			$di->setShared('oauth', new \Vendors\Opauth\Opauth($oauth, false));
		}
		
		$ex = new Exception();
		echo $ex->getMessage();
	}

	/**
	 * Registrasi setiap module yang sudah di-crawling dari sub-folder modules
	 *
	 * @param  array $modules   Module - module yang akan diregistrasi
	 * @param  bool $merge      Jika diset true, maka module akan ditambahkan 
	 *                          bukan direplace
	 *
	 * @return void
	 */
	public function registerModules($modules, $merge = NULL)
	{
		$di         = $this->getDI();
		$registry   = $di->get('registry');
		$config     = $this->_config;
		$bootstraps = [];

		foreach($modules as $name => $prop)
		{
			if (isset($this->_modules[$name]))
				continue;

			$root  = $prop['root'];
			$class = $prop['class'];

			// fixup dir
			$dir = explode(DN, $class);
			
			array_pop($dir);

			$dir = implode(DS, $dir);
			$dir = $registry->directories->Modules.$dir;
			$dir = str_replace(array(DN, '/'), DS, $dir);
			$em  = $this->getEventsManager();
			
			$bootstrap = new $class(
				$di,
				$em,
				$dir,
				$root,
				$name
			);
			
			$bootstraps[$name] = function($di) use($bootstrap) {
				$bootstrap->registerAutoloaders($di);
				$bootstrap->registerServices($di);
				return $bootstrap;
			};
			
		}

		return parent::registerModules($bootstraps, $merge);
	}

	/**
	 * Entry point aplikasi
	 *
	 * @return void
	 */
	public function run()
	{
		$this->_initializing();

		$this->_initLoader();
		$this->_initCache();
		$this->_initErrors();
		$this->_initModules();
		$this->_initSession();
		$this->_initLibraries();
		$this->_initRouter();
		$this->_initUrl();
		$this->_initDatabase();
		$this->_initPlugins();

		$this->_finalizing();
		
		echo $this->handle()->getContent();

	}
}
