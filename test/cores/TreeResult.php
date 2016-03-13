<?php
namespace Cores;

use Phalcon\Mvc\Model\Resultset\Simple as Resultset;

class TreeResult extends Resultset {

    private $_fields = array();

    public function __construct(
        $colmap, 
        $model, 
        $result, 
        $cache = NULL, 
        $snapshoot = NULL, 
        $fields = NULL){

        parent::__construct($colmap, $model, $result, $cache, $snapshoot);

        $this->_fields = $fields;
    }

    /**
     * We have param fields now
     */
    public function filter($callback) {
        if (empty($this->_fields)) {
            return parent::filter($callback);
        }

        $records = array();

        foreach($this as $rec) {
            $params = array($rec, $this->_fields);
            $result = call_user_func_array($callback, $params);

            if ( ! is_array($result) && ! is_object($result)) 
                continue;

            $records[] = $result;
        }

        return $records;
    }

}