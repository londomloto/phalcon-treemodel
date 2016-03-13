<?php namespace Cores;

if ( ! defined('BASEPATH')) exit('No direct script access allowed!');

/*!
 * KCT Model
 *
 * @package KCT Backbone (Application Core)
 * @copyright PT. KREASINDO CIPTA TEKNOLOGI
 * @author PT. KREASINDO CIPTA TEKNOLOGI
 * @author xALPx
 * @author Jokowow
 * @version 1.0
 * @access public
 */

use Phalcon\Mvc\Model as PhalconModel,
	Phalcon\Mvc\Model\Query\Builder,
	Phalcon\Mvc\Model\Resultset\Simple as Resultset,
	Phalcon\Mvc\Model\Exception as ModelException,
	Mixins\Common,
	Mixins\LogsHelper,
	Cores\DataQuery;

abstract class Model extends PhalconModel {
	use Common;
	use LogsHelper;
	
	const OUTPUT_OBJECT = 'object';
	const OUTPUT_ARRAY = 'array';
	const OUTPUT_DEFAULT = 'default';

	private static $_dummy;

	public function __get($name) {
		if($name != 'pid')	return parent::__get($name);
	}

	private static function _getDummy() {
		if ( ! self::$_dummy) {
			self::$_dummy = new static();
		}
		return self::$_dummy;
	}

	public function initialize() {}

	public static function apply(&$target, $source)
	{
		/*$result = $source + $target;
		$target = $result;*/
		$target = array_replace($target, $source);
	}

	public static function manager() {
		// $manager = \Phalcon\DI::getDefault()->getModelsManager();
		return self::_getDummy()->getModelsManager();
	}
	
	public static function relations() {
		return self::manager()->getRelations(get_called_class());
	}

	public static function createBuilder($alias = null) {
		$builder = new Builder();
		$model   = get_called_class();
		
		if ( ! $alias) {
			$builder->from($model);
		} else {
			$builder->addFrom($model, $alias);
		}

		return $builder;
	}

	public static function findIn(array $ids) {
		$pk  = self::getIdentityField();
		$ids = count($ids) > 0 ? $ids : array(null);
		return self::find("$pk IN(".implode(",", $ids).")");
	}

	// Note: deprecated, instead use executeQuery()
	public static function fetchPql($phql, $params = array()) {
		$manager = self::manager();
		return $manager->executeQuery($phql, $params);
	}

	public static function executeQuery($phql, $params = array()) {
		$manager = self::manager();
		return $manager->executeQuery($phql, $params);
	}

	public static function executeSql($sql, $params = array(), $conn = null) {
		// Jika $conn kosong, coba dapatkan dari model
		if ( empty($conn)) {
			$model = new static();
			$conn  = $model->getWriteConnection();
		}

		return $conn->execute($sql, $params);
	}

	public static function fetchSql($sql, $params = array()) {

		$base = new static();
		$conn = $base->getReadConnection();

		return new Resultset(
			null,
			$base,
			$conn->query($sql, $params)
		);
	}

	/**
	 * Model active record feature
	 *
	 * Support:
	 * 		- 	Native resultset
	 * 		-	Pagination (manual / auto)
	 * 		-	Autofiltering
	 * 		-	Autosearching
	 *
	 * Ex:
	 * 		SysUser::fetchQuery()
	 * 			->where(...)
	 * 			->paginate()
	 * 			->asObject();
	 *
	 * 		SysUser::fetchQuery()
	 * 			->where(...)
	 * 			->limit()
	 * 			->asArray()
	 *
	 */
	public static function fetchQuery($alias = null) {
		$builder = self::createBuilder($alias);
		$pager   = new DataQuery(array(
			'provider' => $builder
		));
		return $pager;
	}

	/**
	 * Field metadata
	 *
	 */
	public static function fieldData() {

		$result      = array();
		$model       = new static();

		$metadata    = $model->getModelsMetadata();

		$attributes  = $metadata->getAttributes($model);
		$types       = $metadata->getDataTypes($model);
		$primarykeys = $metadata->getPrimaryKeyAttributes($model);

		foreach($attributes as $prop) {

			$temp = new \stdClass();

			$temp->name = $prop;
			$temp->type = $types[$prop];
			$temp->primary = in_array($prop, $primarykeys);

			$result[] = $temp;
		}

		return $result;
	}
	
	public function decode() {
		return json_decode(json_encode($this->toArray()));
	}
	
	public function encode() {
		return json_encode($this->toArray());
	}

	/*public function beforeSave() {

		$fields = $this->fieldData();
		$tz     = $this->getDI()->get('timezone');

		foreach($fields as $field) {
			if ($field->type == 4 || $field->type == 1) {
				$key = $field->name;
				$val = $this->$key;
				$this->$key = $tz->convertTZ($val, $tz->getAppTimezone(), $tz->getDbTimezone());
			}
		}

	}

	public function afterSave() {

		$fields = $this->fieldData();
		$tz     = $this->getDI()->get('timezone');

		foreach($fields as $field) {
			if ($field->type == 4 || $field->type == 1) {
				$key = $field->name;
				$val = $this->$key;
				$this->$key = $tz->convertTZ($val, $tz->getDbTimezone(), $tz->getAppTimezone());
			}
		}	

		
	}*/

	public function isPhantom() {
		return $this->getDirtyState() != Model::DIRTY_STATE_PERSISTENT;
	}

	/**
	 * toScalar()
	 *
	 * Mengubah model ke dalam bentuk scalar (value pair),
	 * termasuk relasinya (khusus untuk belongsTo atau hasOne).
	 *
	 * @param  boolean $related Jika diset true, maka relasi belongsTo atau hasOne diikutsertakan
	 * @return object
	 */
	public function toScalar($related = true) {

		$model   = (object) $this->toArray();

		foreach($model as $key => $val) {
			if ($val instanceof \Phalcon\Db\RawValue) {
				$model->$key = $val->getValue();
			}
		}

		$manager = $this->getModelsManager();

		if ($related == true) {

			$relations = array();

			if (($btRelations = $manager->getBelongsTo($this))) {
				$relations = array_merge($relations, $btRelations);
			}

			if (($hoRelations = $manager->getHasOne($this))) {
				$relations = array_merge($relations, $hoRelations);
			}

			if (count($relations) > 0) {
				foreach($relations as $rel) {
					$target  = $rel->getReferencedModel();
					$options = $rel->getOptions();

					if ($options && isset($options['alias'])) {
						$alias = $options['alias'];
						if ($this->$alias) {
							foreach($this->$alias->toScalar(false) as $key => $val) {
								$model->{strtolower($alias).'_'.$key} = $val;
							}
						} else {
							$fields = (new $target())->fieldData();
							foreach($fields as $field) {
								$key = $field->name;
								$model->{strtolower($alias).'_'.$key} = '';
							}
						}
					}
				}
			}

		}

		return $model;
	}

	public static function getMetadata() {
		$model = self::_getDummy();
		return $model->getModelsMetadata();
	}

	/**
	 * Shortcut untuk Metadata::getIdentityField
	 *
	 */
	public static function getIdentityField() {
		$model    = new static();
		$metadata = $model->getModelsMetadata();
		return $metadata->getIdentityField($model);
	}

	/**
	 * Shortcut untuk Metadata::hasAttribute()
	 *
	 */
	public static function hasAttribute($name) {
		$model    = new static();
		$metadata = $model->getModelsMetadata();
		return $metadata->hasAttribute($model, $name);
	}

	/**
	 * buildFilter
	 *
	 * Membentuk native model parameter berdasarkan inputan filter
	 *
	 * @param  array $filter
	 * @return array
	 */
	public static function buildFilter($filters = null, $token = null, $prefix = '') {
		$result = array();

		$arrQuery = array();
		$arrBinds = array();

		if (is_string($filters)) {
			$filters = json_decode($filters);
		}

		if (count($filters) > 0) {
			$k = 0;
			foreach($filters as $filter) {
				
				$filter = json_decode(json_encode($filter));
				
				$field  = $filter->field;
				$data   = $filter->data;
				$value  = $data->value;
				$type   = isset($data->type) ? $data->type : 'string';
				
				$comp = isset($data->comparison) ? $data->comparison : '=';
				
				$maps = array(
					'eq'  => '=',
					'lt'  => '<',
					'gt'  => '>',
					'neq' => '<>',
					'lte' => '<=',
					'gte' => '>=',
					'contains' => 'like'
				);

				$comp = isset($maps[$comp]) ? $maps[$comp] : $comp;
				$key  = ! empty($prefix) ? "$prefix.$field" : $field;
				$par  = preg_replace('/(\[.*\]|[\.]+)/', '_', $key);

				switch($type) {
					default:
						switch($comp) {
							case 'like':
								$arrQuery[] = " UPPER($key) LIKE :f_{$par}_$k: ";
								$arrBinds["f_{$par}_$k"] = '%'.strtoupper($value).'%';
								break;
							default:
								$arrQuery[] = " $key $comp :f_{$par}_$k: ";
								$arrBinds["f_{$par}_$k"] = $value;
								break;
						}
						break;
				}

				$k++;

			}
		}

		if (count($arrQuery) > 0) {
			$result = array(
				'conditions' => '('. implode(' AND ', $arrQuery) .')',
				'bind' => $arrBinds
			);
		}
		
		return $result;
	}

	/**
	 * buildSearch()
	 *
	 * Membentuk native model parameter berdasarkan query dan fields
	 * Ex:
	 * 		Mencari value 'A' di kolom X, Y, Z
	 * 		Model::buildSearch(array('x', 'y', 'z'), 'A');
	 *
	 * @param  array $fields
	 * @param  mixed $query
	 * @return array
	 */
	public static function buildSearch($fields = null, $query = null, $token = null, $prefix = '') {

		$result   = array();

		$arrQuery = array();
		$arrBinds = array();
		$strQuery = '';

		if (is_string($fields)) {
			$fields = json_decode($fields);
		}

		if (count($fields) > 0) {
			$k = 0;
			foreach($fields as $key) {
				
				if ( ! self::hasAttribute($key)) continue;
				$key = ! empty($prefix) ? "$prefix.$key" : $key;
				$par = preg_replace('/(\[.*\]|[\.]+)/', '_', $key);

				if (empty($token)) {
					$arrQuery[] = " UPPER($key) LIKE :q_{$par}_$k: ";
					$arrBinds["q_{$par}_$k"] = "%" . strtoupper($query) . "%";
				} else {
					$arrQuery[] = " UPPER($key) LIKE $token ";
					$arrBinds[] = "%" . strtoupper($query) . "%";
				}

				$k++;
			}
		}

		if (count($arrQuery) > 0) {
			$strQuery = implode(' OR ', $arrQuery);
			$result   = array(
				"conditions" => "($strQuery)",
				"bind"       => $arrBinds
			);
		}

		return $result;
	}

	/**
	 * params()
	 *
	 * Membentuk native model parameter berdasarkan parameter inputan
	 * yang diinputkan
	 *
	 * Ex:
	 * 		$params = array(
	 * 			'name'   => 'Agus',
	 * 			'city'   => 'Purwokerto',
	 * 			'fields' => array('field1', 'field2'),
	 * 			'query'  => 'test',
	 * 			'limit'  => 5,
	 * 			'start'  => 0,
	 * 			'filter' => array()
	 * 		);
	 *
	 * 		Model::find(Model::params($params));
	 * 		Model::findFirst(Model::params($params));
	 *
	 *		// atau
	 * 
	 * 		Model::find(Model::params(array(
	 * 			'name' 	=> array('contains', '%budi%'),
	 * 			'date'	=> array('between', array('2013-01-01', '2014-01-01'))
	 * 		)));
	 *
	 * Output:
	 * 		array(
	 * 			'conditions' => '....',
	 * 			'bind' => array(),
	 * 			'limit' => 5,
	 * 			...
	 * 		);
	 *
	 *
	 * @param  array  $params
	 * @param  array  $excludes	Parameter yang tidak diikutsertakan
	 * @return array
	 */
	public static function params($params = array(), $excludes = NULL, $token = NULL, $prefix = NULL) {
		$__save__ = $params;
		
		$result   = array();
		$arrQuery = array();
		$arrBinds = array();

		if (count($params) > 0) {
			// filter params
			$excludes = is_null($excludes) ? array() : $excludes;
			$excludes = array_merge(array('_url', '_identity', '_service', '_output'), $excludes);

			foreach($params as $key => $val) {
				if (in_array($key, $excludes)) unset($params[$key]);
			}

			// check if params has query, fields keys
			if (isset($params['fields'], $params['query'])) {
				$search = self::buildSearch($params['fields'], $params['query'], $token, $prefix);
				if (count($search) > 0) {
					$arrQuery[] = $search['conditions'];
					$arrBinds   = array_merge($arrBinds, $search['bind']);
				}
				unset($params['fields'], $params['query']);
			}

			// check if params has filters
			if (isset($params['filters'])) {
				$filters = self::buildFilter($params['filters'], $token, $prefix);
				if (count($filters) > 0) {
					$arrQuery[] = $filters['conditions'];
					$arrBinds   = array_merge($arrBinds, $filters['bind']);
				}
				unset($params['filters']);
			}

			// check if params has columns key
			if (isset($params['columns'])) {
				if ( ! empty($params['columns'])) {
					$result['columns'] = is_array($params['columns']) ? implode(', ', $params['columns']) : $params['columns'];
				}
				unset($params['columns']);
			}

			// check if params has conditions key
			if (isset($params['conditions'])) {
				$conditions = $params['conditions'];
				if ( ! empty($conditions)) {
					if (is_array($conditions)) {
						$conditions = array_pad($conditions, 2, array());
						if (count($conditions[1]) > 0) {
							foreach($conditions[1] as $key => $val) {
								$arrBinds[$key] = $val;
							}
						}
						$arrQuery[] = $conditions[0];
					} else {
						$arrQuery[] = $conditions;
					}
				}
				unset($params['conditions']);
			}

			// check if params has bind key
			if (isset($params['bind'])) {
				$arrBinds = array_merge($arrBinds, $params['bind']);
				unset($params['bind']);
			}
			
			// check if params has group key
			if (isset($params['group'])) {
				if ( ! empty($params['group'])) {
					$result['group'] = is_array($params['group']) ? implode(', ', $params['group']) : $params['group'];
				}
				unset($params['group']);
			}
			
			// check if params has order
			if (isset($params['order'])) {
				if ( ! empty($params['order'])) {
					$result['order'] = is_array($params['order']) ? implode(', ', $params['order']) : $params['order'];	
				}
				unset($params['order']);
			}

			// check order by HTTP Request
			if (($sort = self::fetchSortRequest($prefix))) {
				$result['order'] = (isset($result['']) ? ', ' : '').$sort;
			}
			
			// check if params has limit
			if (isset($params['limit'])) {
				if (isset($params['start'])) {
					$limit = (int) $params['limit'];
					$start = (int) $params['start'];
					unset($params['start']);
				} else {
					if (is_array($params['limit'])) {
						$limit = (int) $params['limit']['number'];
						$start = (int) $params['limit']['offset'];
					} else {
						$limit = (int) $params['limit'];
						$start = 0;
					}
				}
				
				unset($params['limit']);

				$result['limit'] = array(
					'number' => $limit,
					'offset' => $start
				);
			}
			
			// check if param identifiers exists and perform Model::findIn
			if (isset($params['identifiers']) && count($params['identifiers']) > 0) {
				$pkf = self::getIdentityField();
				$ids = $params['identifiers'];
				if ( ! empty($pkf)) {
					$ids = is_string($ids) ? json_decode($ids) : $ids;
					$arrQuery[] = " $pkf IN(".implode(",", $ids).") ";
				}
				unset($params['identifiers']);
			}

			// check the rest of params
			if (count($params) > 0) {
				$k = 0;	// konter
				foreach($params as $key => $val) {
					if ( ! self::hasAttribute($key)) continue;
					$key = ! empty($prefix) ? "$prefix.$key" : $key;
					$par = preg_replace('/(\[.*\]|[\.]+)/', '_', $key);
					
					if (is_string($val)) {
						if (preg_match('/(^\[|^\{)/', $val)) {
							$val = (Array)json_decode($val);
							if (empty($val)) continue;
						}
					}

					// format value into array
					$val = ! is_array($val) ? array('=', $val) : $val;
					$val = array_pad($val, 2, null);

					switch(strtolower($val[0])) {
						
						case 'in':
						case 'not in':
							// $val[1] must be in array
							$idents = array();
							
							if ( ! is_array($val[1])) {
								throw new ModelException("Values of parameter 'in' must be in array");
							}

							// prevent user to get resultset when values is empty
							if (empty($val[1])) {
								$val[1] = array(NULL);
							}

							for ($j = 0; $j < count($val[1]); $j++) {
								if (empty($token)) {
									$idents[] = ":p_{$par}_{$k}_{$j}:";
									$arrBinds["p_{$par}_{$k}_{$j}"] = $val[1][$j];
								} else {
									$idents[]   = "$token";
									$arrBinds[] = $val[1];
								}
							}

							$arrQuery[] = "$key ".strtoupper($val[0])." (".implode(",", $idents).")";	

						break;

						case 'between':
							// $val[1] must be in array
							if ( ! is_array($val[1])) {
								throw new ModelException("Values of parameter 'between' must be in array");
							}

							if (empty($token)) {
								$arrQuery[] = " $key BETWEEN :p_{$par}_{$k}_1: AND :p_{$par}_{$k}_2: ";
								$arrBinds["p_{$par}_{$k}_1"] = $val[1][0];
								$arrBinds["p_{$par}_{$k}_2"] = $val[1][1];
							} else {
								$arrQuery[] = "$key BETWEEN $token AND $token ";
								$arrBinds[] = $val[1][0];
								$arrBinds[] = $val[1][1];
							}
						break;

						case 'contains':
							if (empty($token)) {
								$arrQuery[] = " $key LIKE :p_{$par}_$k: ";
								$arrBinds["p_{$par}_$k"] = $val[1];
							} else {
								$arrQuery[] = " $key LIKE $token ";
								$arrBinds[] = $val[1];
							}
						break;

						default:
							if (empty($token)) {
								$arrQuery[] = " $key {$val[0]} :p_{$par}_$k: ";
								$arrBinds["p_{$par}_$k"] = $val[1];
							} else {
								$arrQuery[] = " $key {$val[0]} $token ";
								$arrBinds[] = $val[1];
							}
						break;
					}

					$k++;
					
				}
			}

			if (count($arrQuery) > 0) {
				$result['conditions'] = (isset($result['conditions']) ? ' AND ' : '').'('.implode(' AND ', $arrQuery).')';
			}

			if (count($arrBinds) > 0) {
				$result['bind'] = isset($result['bind']) ? array_merge($result['bind'], $arrBinds) : $arrBinds;
			}

		}
		
		$params   = $__save__;
		
		return $result;
	}

	public static function fetchSortRequest($prefix = null) {
		$client = self::getClient();
		return self::_fetchSort($client, $prefix);
	}

	private static function _fetchSort($client = 'browser', $prefix = null) {
		
		$request = \Phalcon\DI::getDefault()->get('request', true);
		$sort    = $request->get('sort');
		$order   = '';
		$temp    = array();

		if ( ! empty($sort)) {

			switch($client) {
				case 'dojo':
					$sort = explode(',', $sort);
					for ($i = 0; $i < count($sort); $i++) {
						$dir 	= substr($sort[$i], 0, 1) == '-' ? 'DESC' : 'ASC';
						$prop 	= ( ! empty($prefix) ? $prefix.'.' : '').substr($sort[$i], 1);
						$temp[] = $prop.' '.$dir;
					}
				break;

				default:
					$sort = json_decode($sort);
					if (is_array($sort) && count($sort) > 0) {
						foreach($sort as $item) {
							$temp[] = ( ! empty($prefix) ? $prefix.'.' : '').$item->property.' '.$item->direction;
						}
					}
				break;
			}

			if (count($temp) > 0) {
				$order = implode(',', $temp);
			}
		}

		return $order;
	}

	public static function fetchLimitRequest() {
		$client = self::getClient();
		return self::_fetchLimit($client);
	}

	private static function _fetchLimit($client = 'browser') {
		$request = \Phalcon\DI::getDefault()->get('request', true);

		switch($client) {
			case 'dojo':
				if (($xrange = $request->getHeader('X_RANGE'))) {
					preg_match('/(\d+)\-(\d+)/', trim($xrange), $matches);
					if ($matches) {
						return array(
							'offset' => (int) $matches[1],
							'number' => (int) $matches[2] - (int) $matches[1] + 1
						);
					}
				}
			break;

			case 'extjs':
			case 'browser':
				$start = $request->get('start');
				$limit = $request->get('limit');
				if ($start != '' && $limit != '')
					return array(
						'offset' => $start,
						'number' => $limit
					);
			break;
		}

		return null;
	}

	public static function getClient() {
		$client  = 'browser';
		$request = \Phalcon\DI::getDefault()->get('request', true);

		if (($xclient = $request->getHeader('X_CLIENT'))) {
			$client = $xclient;
		}

		return $client;
	}

	/**
	 * buildSimpleParams()
	 *
	 * Fungsinya sama seperti params(), tetapi
	 * hanya untuk input parameter data (bukan meta seperti query, fields, limit, dll)
	 *
	 * @param  array  $params
	 * @return array
	 */
	public static function buildSimpleParams($params = array()) {

		return self::params($params, array(
			'identifiers',
			'query',
			'fields',
			'limit',
			'start',
			'sort',
			'filter'
		));

	}

	public static function mapResult(Resultset $result, $handler) {
		return array_map($handler, $result->filter(function($model){ return $model; }));
	}

	public function displayErrors($sep = '<br />') {
		$messages = array_map(function($msg){ return $msg; }, $this->getMessages());
		return implode($sep, $messages);
	}

	public function getErrors() {

		$result = array(
			'status'  => 500,
			'message' => '',
			'action'  => ''
		);

		$messages = [];

		foreach($this->getMessages() as $message) {
			$messages[] = $message;
		}

		$result['message'] = implode("\n", $messages);
		return $result;
	}

	public function debug($method = "toArray", $dump = false) {

		$debug = $this->$method();
		echo "<pre style=\"font: 13px/16px Consolas, Monaco, 'Courier New';\">";
		$dump ? var_dump($debug) : print_r($debug);
		echo "</pre>";

	}

	public static function exception($message) {
		throw new ModelException($message);
	}

}
