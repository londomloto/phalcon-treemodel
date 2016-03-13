<?php
namespace Cores;

use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray,
    Phalcon\Paginator\Adapter\QueryBuilder as PaginatorBuilder,
    Phalcon\Paginator\Adapter\Model as PaginatorModel,
    Phalcon\Mvc\Model\Resultset,
    Phalcon\Mvc\Model\Resultset\Simple as ResultSimple,
    Phalcon\Mvc\Model\Resultset\Complex as ResultComplex,
    Phalcon\Mvc\Model\Query\Builder as QueryBuilder,
    Cores\Model,
    Cores\DataTable;

class DataQuery {
    
    public $immediate = false;
    public $provider = NULL;
    
    private $_autoLimit = TRUE;
    private $_autoSearch = TRUE;
    private $_autoFilter = TRUE;
    private $_limit = NULL;
    private $_offset = 0;
    private $_output = 'default';
    private $_enabled = TRUE;
    private $_froms = array();
    private $_joins = array();

    public function __construct(array $options = array()) {
        if (count($options) > 0) {
            foreach($options as $key => $val) {
                if ( ! in_array($key, array('immediate', 'provider'))) {
                    $this->{"_$key"} = $val;    
                } else {
                    $this->$key = $val;
                }
            }
        }

        if ($this->immediate) {
            $this->execute();   
        }
    }

    public function getDI() {
        return \Phalcon\DI::getDefault();
    }
    
    public function useBuilder() {
        return $this->provider instanceof QueryBuilder;
    }
    
    public function addFrom($model, $alias) {
        if ($this->useBuilder()) {
            $this->provider->addFrom($model, $alias);

            if ( ! in_array($model, $this->_froms)) {
                $this->_froms[] = $model;
            }
        }
        return $this;
    }

    public function columns($columns) {
        if ($this->useBuilder()) {
            $this->provider->columns($columns);
        }
        return $this;
    }

    public function where($conds, $bind = array()) {
        if ($this->useBuilder()) {
            $this->provider->where($conds, $bind);
        }
        return $this;
    }

    public function andWhere($conds, $bind = array()) {
        if ($this->useBuilder()) {
            $this->provider->andWhere($conds, $bind);
        }
        return $this;
    }

    public function orWhere($conds, $bind = array()) {
        if ($this->useBuilder()) {
            $this->provider->orWhere($conds, $bind);
        }
        return $this;   
    }

    public function join($model, $conditions = '', $alias = '', $type = '') {
        if ($this->useBuilder()) {
            $this->provider->join($model, $conditions, $alias, $type);
            if ( ! in_array($model, $this->_joins)) {
                $this->_joins[] = $model;
            }
        }
        return $this;
    }
    
    public function groupBy($group) {
        if ($this->useBuilder()) {
            $this->provider->groupBy($group);
        }
        return $this;
    }

    public function orderBy($order) {
        if ($this->useBuilder()) {
            $this->provider->orderBy($order);
        }
        return $this;
    }
    
    /**
     * Setup parameter ke dalam query builder.
     * 
     * Parameter ini kemudian akan ditransformasikan ke dalam fungsi - fungsi yang ada di
     * \Phalcon\Mvc\Model\Criteria seperti condition, bind, where, orderBy, limit.
     * 
     * Contoh Pencarian:
     *      
     *      Model::fetchQuery()->params(array(
     *          'customer' => array('like', 'john'),
     *          'balance' => array('between', array(5000, 10000))
     *      ));
     * 
     * Contoh Paging:
     *      
     *      Model::fetchQuery()->params(array(
     *          'active' => 1,
     *          'limit' => array('number' => 25, 'offset' => 0) // atau 'limit' => 5
     *      ));
     * 
     * Contoh Sorting:
     *      
     *      Model::fetchQuery()->params(array(
     *          'orderBy' => 'id ASC'
     *      ));
     * 
     * @param Array $params
     * @return \Libraries\DataPager
     */
    public function params($params) {
        if ($this->useBuilder()) {
            
            $retval = array(
                'conditions' => array(), 
                'bind'  => array(),
                'order' => array()
            );

            if (isset($params['limit'], $params['start'])) {
                $this->_limit  = (int) $params['limit'];
                $this->_offset = (int) $params['start'];
                unset($params['start'], $params['limit']);
            }

            $models = array();
            $parsed = $this->provider->getQuery()->parse();

            if (isset($parsed['models'], $parsed['tables'])) {
                for ($i = 0; $i < count($parsed['models']); $i++) {
                    $alias = '['.$parsed['models'][$i].']';
                    if (isset($parsed['tables'][$i])) {
                        if (is_array($parsed['tables'][$i])) {
                            $alias = $parsed['tables'][$i][2];
                        }
                    }
                    $models[] = array(
                        'name'  => $parsed['models'][$i],
                        'alias' => $alias
                    );
                }
            }

            $aliases = array();

            if (isset($parsed['joins'])) {
                for ($i = 0; $i < count($parsed['joins']); $i++) {
                    $source = $parsed['joins'][$i]['source'];
                    $aliases[$source[0]] = isset($source[2]) ? $source[2] : NULL;
                }
            }

            if (count($this->_joins) > 0) {
                for ($i = 0; $i < count($this->_joins); $i++) {
                    $class = $this->_joins[$i];
                    if (class_exists($class)) {
                        $dummy  = new $class();
                        $source = $dummy->getSource();
                        $alias  = '['.$this->_joins[$i].']';
                        if (isset($aliases[$source])) {
                            $alias = $aliases[$source];
                        }
                        $models[] = array(
                            'name'  => $this->_joins[$i],
                            'alias' => $alias
                        );
                        unset($dummy);
                    }
                }
            }
            
            if (count($models) > 0) {
                foreach($models as $model) {
                    $args = array($params, NULL, NULL, $model['alias']);
                    $call = call_user_func_array(array($model['name'], 'params'), $args);

                    if (isset($call['conditions'], $call['bind'])) {
                        $retval['conditions'] = array_merge($retval['conditions'], array($call['conditions']));
                        $retval['bind'] = array_merge($retval['bind'], $call['bind']);
                    }

                    if (isset($call['order'])) {
                        array_push($retval['order'], $call['order']);
                    }
                }
            }

            $retval['conditions'] = ! empty($retval['conditions']) ?  '('.implode(' AND ', $retval['conditions']).')' : '';
            $retval['order'] = implode(', ', $retval['order']);

            if ( ! empty($retval['conditions'])) {
                $this->provider->andWhere($retval['conditions'], $retval['bind']);
            }

            if ( ! empty($retval['order'])) {
                $this->provider->orderBy($retval['order']);
            }

        }

        return $this;
    }
    
    public function pagination($enabled = TRUE) {
        $this->_enabled = $enabled;
        return $this;
    }

    public function limit($limit, $offset = NULL) {
        if (is_array($limit)) {
            $this->_limit  = isset($limit['number']) ? $limit['number'] : NULL;
            $this->_offset = isset($limit['offset']) ? $limit['offset'] : NULL;
        } else {
            $this->_limit = $limit;
            if ( ! is_null($offset)) {
                $this->_offset = $offset;
            }
        }
        return $this;
    }

    public function autoLimit($enabled = TRUE) {
        $this->_autoLimit = $enabled;
        return $this;
    }

    public function autoSearch($enabled = TRUE) {
        $this->_autoSearch = $enabled;
        return $this;
    }

    public function autoFilter($enabled = TRUE) {
        $this->_autoFilter = $enabled;
        return $this;
    }

    public function output($mode = 'object') {
        $this->_output = $mode;
    }
    
    public function getPhql() {
        if ($this->useBuilder()) {
            return $this->provider->getPhql();
        }
        return NULL;
    }

    public function execute() {
        $result = $this->_fetchResult();
        $result['output']  = $this->_output;
        $result['enabled'] = $this->_enabled;

        // always send content range;
        $start = $this->_offset;
        $end   = $this->_offset + $this->_limit - 1; // ($start + $result['count'] - 1);
        $total = $result['total'];

        header("Content-Range: items ".$start."-".$end."/".$total);
        return new DataTable($result);
    }

    private function _prepareLimit() {
        if ($this->_autoLimit) {
            if (($limit = Model::fetchLimitRequest())) {
                $this->_limit  = $limit['number'];
                $this->_offset = $limit['offset'];
            }
        }
    }

    private function _parseResult($result) {

        $output      = $this->_output;
        $retval      = array();
        $isComplex   = $result instanceof Resultset\Complex;
        $isResultset = $result instanceof Resultset;

        switch($output) {
            case 'object':

                if ($isResultset)
                    $result->setHydrateMode(Resultset::HYDRATE_OBJECTS);
                
                if ($isComplex) {
                    foreach($result as $item) {
                        $temp = array();
                        foreach($item as $key => $val) {
                            if (is_object($val)) {
                                $temp = array_merge($temp, (Array)$val);
                            } else {
                                $temp = array_merge($temp, array($key => $val));
                            }
                        }
                        $retval[] = json_decode(json_encode($temp));
                        $temp = NULL;
                    }

                } else {
                    foreach($result as $item) {
                        if ($item instanceof Model) {
                            $retval[] = json_decode(json_encode($item->toArray()));
                        } else {
                            $retval[] = $item;  
                        }
                    }
                }

                $result = $retval;

            break;
            case 'array':

                if ($isResultset)
                    $result->setHydrateMode(Resultset::HYDRATE_ARRAYS);
                
                if ($isComplex) {
                    foreach($result as $item) {
                        $temp = array();
                        foreach($item as $key => $val) {
                            if (is_array($val)) {
                                $temp = array_merge($temp, $val);
                            } else {
                                $temp = array_merge($temp, array($key => $val));
                            }
                        }
                        $retval[] = $temp;
                        $temp = NULL;
                    }
                } else {
                    foreach($result as $item) {
                        if ($item instanceof Model) {
                            $retval[] = $item->toArray();
                        } else {
                            $retval[] = $item;  
                        }
                    }
                }

                $result = $retval;
            break;
        }   

        $retval = NULL;
        return $result;
    }

    private function _fetchResult() {

        $provider = $this->provider;
        $enabled  = $this->_enabled;

        $pager    = NULL;
        $result   = NULL;

        $data     = array();
        $total    = 0;
        $count    = 0;
        $page     = 0;
        $pages    = 0;
        
        // prepare limit
        $this->_prepareLimit();
        
        $limit    = (int) $this->_limit;
        $offset   = (int) $this->_offset;

        if ( ! $limit) {
            
            $this->_enabled = $enabled = false;
        }

        switch(TRUE) {
            case (is_array($provider)):
                if ($enabled) {

                    $page     = floor($offset / $limit) + 1;
                    $total    = count($provider);
                    $pages    = ceil($total / $limit);
                    $provider = array_slice($provider, $offset);
                    
                    $pager    = new PaginatorArray(array(
                        'data'  => $provider,
                        'limit' => $limit
                    ));

                    $result   = $pager->getPaginate();
                    $data     = $result->items;
                    $count    = count($data);

                } else {
                    $data     = $this->provider;
                }
            break;

            case ($provider instanceof QueryBuilder):

                if ($enabled) {
                    
                    $provider->offset($offset);

                    $pager = new PaginatorBuilder(array(
                        'builder' => $provider,
                        'limit'   => $limit
                    ));

                    $result = $pager->getPaginate();
                    $data   = $this->_parseResult($result->items);

                    $total  = $result->total_items;
                    $pages  = ceil($total / $limit);
                    $page   = floor($offset / $limit) + 1;
                    $count  = count($data);

                } else {
                    $data = $this->_parseResult($provider->getQuery()->execute());
                }

            break;

            case ($provider instanceof Resultset):

                if ($enabled) {
                    
                    $page     = floor($offset / $limit) + 1;

                    $provider->rewind();
                    $provider->seek($offset);

                    $pager = new PaginatorModel(array(
                        'data' => $provider,
                        'limit'=> $limit
                    ));

                    $result = $pager->getPaginate();

                    $data   = $this->_parseResult($result->items);
                    $total  = $result->total_items;
                    $pages  = ceil($total / $limit);
                    $count  = count($data);

                } else {
                    $data = $this->_parseResult($provider);
                }

            break;
        }

        if (count($data) == 0) {
            $total = 0;
            $page  = 0;
            $pages = 0;
            $count = 0;
        }

        if ($enabled == false) {
            $count = count($data);
            $total = $count;
            $page  = 1;
            $pages = 1;
        }

        return array(
            'data'  => $data,
            'count' => $count,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages
        );
    }

    public function getLastQuery() {
        $profiler = $this->getDI()->getShared('profiler');
        if ($profiler && ($last = $profiler->getLastProfile())) {
            return $last->getSQLStatement();
        }
        return '';
    }


}