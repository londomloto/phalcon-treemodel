<?php
namespace Londo;

use Phalcon\Mvc\Model,
    Londo\ITreeModel,
    Londo\TreeQuery;

abstract class TreeModel extends Model implements ITreeModel {
    
    public function setup(Array $maps) {
        $this->lft = $maps['lft'];
        $this->rgt = $maps['rgt'];
        $this->key = $maps['key'];
    }
    
    public function parent() {
        $path = explode('/', $this->path);
        array_pop($path);
        if ( ! empty($path))
            return self::findNode(array_pop($path));
        return FALSE;
    }
    
    public function prev() {
        return (new TreeQuery($this))
            ->where("$this->rgt = $this->{$this->lft} - 1")
            ->execute()
            ->getFirst();
    }
    
    public function next() {
        return (new TreeQuery($this))
            ->where("$this->lft = $this->{$this->rgt} + 1")
            ->execute()
            ->getFirst();
    }
    
    public function ancestors() {
        $path = explode('/', $this->path);
        $bind = array();
        $where = array_map(
            function($id) use (&$bind) {
                $token = "path_{$id}";
                $bind[$token] = $id;
                return ":{$token}:";
            },
            $path
        );
        
        return (new TreeQuery())
            ->where("$this->key IN (".implode(", ", $where).")", $bind)
            ->orderBy("$this->rgt - $this->lft")
            ->execute();
    }
    
    public function descendants() {
    
    }
    
    public function children() {
    
    }
    
    public function siblings() {
    
    }
    
    public function cascade($callback) {
    
    }
    
    public function bubble($callback) {
        
    }
    
    public function append(Model $node) {
    
    }
    
    public function prepend(Model $node) {
    
    }
    
    public function before(Model $node) {
    
    }
    
    public function after(Model $node) {
    
    }
    
    public function insertBefore(Model $node) {
    
    }
    
    public function insertAfter(Model $node) {
    
    }
    
    public static function findNode($params) {
        if (is_numeric($params)) {
            $identity = $params;
            $params = array();
            $params[$this->key] = $identity;
        }
        
        return (new TreeQuery())
            ->compile(TreeQuery::COMPILE_NODE)
            ->params($params)
            ->execute()
            ->getFirst();
    }
    
}
