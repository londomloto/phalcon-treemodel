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
    
    }
    
    public function prev() {
    
    }
    
    public function next() {
    
    }
    
    public function ancestors() {
    
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
