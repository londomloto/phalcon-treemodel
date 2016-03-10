<?php
namespace Londo;

use Phalcon\Mvc\Model\Resultset\Simpel as Resultset,
    Londo\ITreeModel;

class TreeQuery {
    
    const QUERY_NODE = 'node';
    const QUERY_DESCENDANT = 'descendant';
    
    private $query;
    private $base;
    private $bind;
    private $link;
    
    private $where;
    private $type;
    private $data;
    private $table;
    
    public function __construct(ITreeModel $base) {
        $this->base = $base;
        $this->bind = array();
        $this->link = $base->getReadConnection();
        
        $this->where = array(
            'and' => array(),
            'or' => array()
        );
        
        $this->type = self::QUERY_NODE;
        $this->data = array();
        $this->table = $base->getSource();
    }
    
    public function setType($type = NULL) {
        $this->type = empty($type) ? self::QUERY_NODE : $type;
    }
    
    public function setData($key, $value = NULL) {
        if (is_array($key)) {
            foreach($key => $k => $v)
                $this->setData($k, $v);
        } else {
            $this->data[$key] = $val;
        }
    }
    
    public function execute() {
        
        $method = 'compile'.ucwords($this->type);
        $this->$method($this->data);
        
        $this->query = preg_replace('/:([^:]+):/', ':$1', $this->query);
        return new Resultset(NULL, $this->base, $this->link->query($this->query, $this->bind));
    }
    
    public function params(Array $params) {
        $where = array();
        $bind = array();
        
        foreach($params as $key => $val) {
            $where[] = "$key = :$key:";
            $bind[$key] = $val;
        }
        
        $this->bind = array_merge($this->bind, $bind);
        $this->where['and'][] = '('.implode(' AND ', $where).')';
    }
    
    public function __toString() {
        return $this->query;
    }
    
    private function compileNode($data) {
        $base = $this->base;
        
        $template = 
            "SELECT
                {columns}
            FROM
                $this->table n, 
                $this->table p 
            WHERE
                n.{$base->lft} BETWEEN p.{$base->lft} AND p.{$base->rgt}
            GROUP BY n.{$base->key} 
            ORDER BY n.{$base->lft}";
        
        $this->query = preg_replace_callback(
            '/\{([^\}]+)\}/',
            function($match) use ($data) {
                if (isset($data[$match[1]])) {
                    return $data[$match[1]];
                }
                return $match[0];
            },
            $template
        );
    }
    
    private function compileDescendant($data) {
        
    }
    
}
