<?php
namespace Cores;

use Phalcon\Mvc\Model\Resultset,
    Phalcon\Mvc\Model\Resultset\Simple as ResultSimple,
    Phalcon\Mvc\Model\Resultset\Complex as ResultComplex,
    Phalcon\Mvc\Model\Exception as ModelException;

class DataTable implements \JsonSerializable {

    public $success = TRUE;
    public $data = array();
    public $total = 0;
    public $count = 0;
    public $page = 0;
    public $pages = 0;
    public $output = NULL;
    
    public function __construct($options = array()) {
        if (count($options) > 0) {
            foreach($options as $key => $val) {
                $this->$key = $val;
            }
        }
    }
    
    public function __call($method, $args) {
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $args);
        } else if (preg_match('/^get(.*)/', $method, $matches)) {
            $prop = strtolower($matches[1]);
            if (property_exists($this, $prop)) {
                return $this->$prop;
            } else {
                throw new ModelException("Property $prop doesn't exists", 500);
            }
        }
    }

    public function jsonSerialize() {
        
        $data = $this->data;

        if ($this->data instanceof Resultset) {
            $data = $this->data->toArray();
        }

        return array(
            'success' => $this->success,
            'data' => $data,
            'total' => $this->total,
            'count' => $this->count,
            'page' => $this->page,
            'pages' => $this->pages 
        );
    }

    public function filter($callback) {
        if (is_callable($callback)) {
            switch(TRUE) {
                case ($this->data instanceof ResultSimple):
                    $this->data = $this->data->filter($callback);
                    break;
                
                case ($this->data instanceof ResultComplex):
                    $temp = array();
                    
                    foreach($this->data as $row) {
                        $node = call_user_func_array($callback, array($row));
                        if ($node) {
                            $temp[] = $node;
                        }
                    }
                    
                    $this->data = $temp;
                    break;
                
                default:
                    $temp = array();
                    $data = $this->data;
                    for($i = 0; $i < count($data); $i++) {
                        $node = call_user_func_array($callback, array($data[$i]));
                        if ($node) {
                            $temp[] = $node;
                        }
                    }
                    $this->data = $temp;
                    break;
            }
        }
        return $this;
    }
    
    public function toArray() {
        $result = array();

        if ($this->data instanceof Resultset) {
            $result = $this->data->toArray();
        } else if ($this->output == 'object') {
            foreach($this->data as $row) {
                $result[] = is_scalar($row) ? $row : (Array)$row;
            }
        } else if ($this->output == 'array') {
            $result = $this->data;
        }

        return $result;
    }

}