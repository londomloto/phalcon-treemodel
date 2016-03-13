<?php
/**
 * Nested Set Model
 *
 * @author PT. Kreasindo Cipta Teknologi
 * @author Roso Sasongko <roso@kct.co.id>
 */

namespace Cores;

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\Mvc\Model\Message,
    Interfaces\ITreeModel,
    Cores\Model,
    Cores\Config,
    Cores\TreeQuery;

abstract class TreeModel extends Model implements ITreeModel {
    
    const ACTION_INSERT_APPEND = 'append';
    const ACTION_INSERT_PREPEND = 'prepend';
    const ACTION_INSERT_BEFORE = 'before';
    const ACTION_INSERT_AFTER = 'after';
    
    const ACTION_MOVE_APPEND = 'moveAppend';
    const ACTION_MOVE_PREPEND = 'movePrepend';
    const ACTION_MOVE_BEFORE = 'moveBefore';
    const ACTION_MOVE_AFTER = 'moveAfer';

    private static $_instance;

    private $_config = null;
    private $_index = null;
    
    public $root = null;
    public $parent = null;

    public $after = null;
    public $before = null;
    public $action = null;
    public $children = array();

    private static function _getInstance() {
        if ( ! self::$_instance) {
            self::$_instance = new static();
        }
        return self::$_instance;
    }

    /**
     * Internal function of `Model::findFirst()`
     */
    private static function _findFirst($id, $columns = '*') {
        $base  = self::_getInstance();
        $link  = $base->getReadConnection();
        $table = $base->getSource();
        
        $bind = array();

        if (is_array($columns)) {
            $columns = implode(',', $columns);
        }

        $sql = "SELECT $columns FROM $table WHERE 1 = 1 ";

        if (is_numeric($id)) {
            $fieldKey = $base->getParamKey();
            $bind[$fieldKey] = $id;
        } else {
            $bind = $id;
        }

        $where = array();

        foreach($bind as $k => $v) 
            $where[] = "$k = :$k";
            
        if ( ! empty($where)) 
            $sql .= ' AND ('.implode(' AND ', $where).') ';

        $sql .= 'LIMIT 1';
        return $link->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC, $bind);
    }

    /**
     * Internal function get root value from node id
     */
    private static function _getRootValue($id) {
        $base = self::_getInstance();
        $fieldRoot = $base->getParamRoot();
        $data  = self::_findFirst($id, $fieldRoot);
        
        return $data ? $data[$fieldRoot] : FALSE;
    }

    public function initialize() {
        parent::initialize();

        if ( ! $this->_index) {
            $link = $this->getReadConnection();
            $table = $this->getSource();
            $index = $table.'_tree';

            $base = self::_getInstance();
            
            $idn = $base->getParamKey();
            $rdn = $base->getParamRoot();
            $lvl = $base->getParamLevel();

            $found = $link->fetchOne(
                "SHOW INDEX FROM $table WHERE Key_name = '$index'", 
                \Phalcon\Db::FETCH_ASSOC
            );

            if ( ! $found) {
                $link->query("CREATE INDEX $index ON $table ($rdn, $lvl, $idn) USING BTREE");
            }
        }
    }

    public function getIndexName() {
        return $this->getSource().'_tree';
    }

    public function setupNode(Array $config) {
        // prepare config 'fields'
        if ( ! isset($config['fields'])) {
            $this->exception('TreeModel::setupNode(): Parameter `fields` is required!');
        }

        if ( ! isset($config['fields']['root'])) {
            $this->exception('TreeModel::setupNode(): Parameter `fields[root]` is required!');
        }

        if ( ! isset($config['fields']['level'])) {
            $this->exception('TreeModel::setupNode(): Parameter `fields[level]` is required!');
        }

        if ( ! isset($config['fields']['left'])) {
            $this->exception('TreeModel::setupNode(): Parameter `fields[left]` is required!');
        }

        if ( ! isset($config['fields']['right'])) {
            $this->exception('TreeModel::setupNode(): Parameter `fields[right]` is required!');
        }   

        if ( ! isset($config['fields']['parent'])) {
            $config['fields']['parent'] = 'parent_id';
        }

        // prepare config 'alias'
        if ( ! isset($config['alias'])) {
            $config['alias'] = 'tree';
        }

        $this->_config = new Config($config);
        $this->_bm = $this->getDI()->get('benchmark', true);
    }

    public function getNodeConfig($key = NULL) {
        $config = $this->_config;
        if ( ! empty($key)) {
            if ($config->offsetExists($key)) {
                return $config->$key;
            }
            return NULL;
        }
        return $config;
    }

    public function getParamRoot() {
        return $this->_config->fields->root;
    }

    public function getParamLevel() {
        return $this->_config->fields->level;
    }

    public function getParamLeft() {
        return $this->_config->fields->left;
    }

    public function getParamRight() {
        return $this->_config->fields->right;
    }

    public function getParamKey() {
        return $this->getModelsMetadata()->getIdentityField($this);
    }

    public function getParamParent() {
        return $this->_config->fields->parent;
    }

    public function getAlias() {
        return $this->_config->alias;
    }

    public function getRootValue() {
        $field = $this->getParamRoot();
        return (int) $this->$field;
    }

    public function getLevelValue() {
        $field = $this->getParamLevel();
        return (int) $this->$field;
    }

    public function getLeftValue() {
        $field = $this->getParamLeft();
        return (int) $this->$field;
    }

    public function getRightValue() {
        $field = $this->getParamRight();
        return (int) $this->$field;
    }

    public function getKeyValue() {
        $field = $this->getParamKey();
        return $field ? (int) $this->$field : null;
    }

    public function getParentValue() {
        if ($this->isRoot())
            return -1;

        $path = $this->getPathValue();

        if ( ! empty($path)) {
            $part = explode('/', $path);
            array_pop($part);
            return (int) array_pop($part);
        }

        return $this->{$this->getParamParent()};
    }

    /**
     * @deprecated instead use `Model::getPrevValue()`
     */
    public function getPreviousValue() {
        return $this->getPrevValue();
    }

    public function getPrevValue() {
        $prev = $this->getPrev();
        return $prev ? $prev->getKeyValue() : NULL;
    }

    public function getNextValue() {
        $next = $this->getNext();
        return $next ? $next->getKeyValue() : NULL;
    }

    public function getDepthValue() {
        return isset($this->depth) ? (int) $this->depth : -1;
    }

    public function getPathValue() {
        return isset($this->path) ? $this->path : NULL;
    }

    public function getPathBy($field = NULL, $separator = '/') {
        if ($field == $this->getParamKey()) {
            return $this->getPathValue();
        }

        $path = explode('/', $this->getPathValue());

        if ( ! empty($path)) {
            // build `where in`;
            $bind = array();
            $where = array_map(
                function($id) use (&$bind) {
                    $token = "path_{$id}";
                    $bind[$token] = $id;
                    return ":{$token}:";
                },
                $path
            );

            $where = $this->getParamKey().' IN ('.implode(', ', $where).') ';

            $query = (new TreeQuery($this, FALSE))
                ->data('root', $this->getRootValue())
                ->where($where, $bind)
                ->orderBy($this->getParamLeft());

            $nodes = $query->execute();

            if ($field == '*') {
                return $nodes;
            } else {
                $array = array();
                foreach($nodes as $node) {
                    $array[] = trim($node->$field);
                }
                return implode($separator, $array);
            }
        }
        return FALSE;
    }

    public function isRoot() {
        return $this->getLevelValue() === -1;
    }

    public function isParent() {
        return $this->hasChildren();
    }

    public function isLeaf() {
        return ($this->getRightValue() - $this->getLeftValue()) == 1;
    }

    public function isLeftMost() {

    }

    public function isRightMost() {
        
    }

    public function hasChildren() {
        return ($this->getRightValue() - $this->getLeftValue()) > 1;
    }

    public function hasParent() {
        return $this->getDepthValue() != 0;
    }

    public function isPhantom() {
        return $this->getDirtyState() != Model::DIRTY_STATE_PERSISTENT;
    }

    // @Override
    public function toArray($columns = NULL) {
        $array = parent::toArray($columns);
        /*
        foreach($this as $key => $val) {
            var_dump($key);
        }
*/
        $array['depth'] = $this->getDepthValue();
        $array['path']  = $this->getPathValue();
        return $array;
    }

    // @Override
    public function toScalar($related = TRUE) {
        $scalar = parent::toScalar($related);

        $scalar->depth = $this->getDepthValue();
        $scalar->path  = $this->getPathValue();
        return $scalar;
    }

    public function createRoot($data = array()) {
        $fieldRoot = $this->getParamRoot();
        $fieldLevel = $this->getParamLevel();
        $fieldLeft = $this->getParamLeft();
        $fieldRight = $this->getParamRight();
        $fieldParent = $this->getParamParent();

        if (method_exists($this, 'getDefaultValues')) {
            $defaults = $this->getDefaultValues();
            foreach($defaults as $key => $val) {
                $this->$key = $val;
            }
        }

        if (count($data) > 0) {
            foreach($data as $key => $val) {
                $this->$key = $val;
            }
        }

        if (method_exists($this, 'onBeforeCreateRoot')) {
            $this->onBeforeCreateRoot();
        }

        $this->$fieldParent = -1;
        $this->$fieldLevel = -1;
        $this->$fieldLeft = 1;
        $this->$fieldRight = 2;

        if ($this->save()) {
            // update root field
            $this->$fieldRoot = $this->getKeyValue();
            $this->save();

            // update with depth and path
            $this->depth = -1;
            $this->path  = NULL;

            return TRUE;
        }

        return FALSE;
    }
    
    public function append(Model $node) {
        $node->root   = $this->isRoot() ? $this : $this->getRoot();
        $node->parent = $this;

        if ($node->isPhantom()) {
            $node->action = self::ACTION_INSERT_APPEND;
            return $node->createNode();
        } else {
            $node->action = self::ACTION_MOVE_APPEND;
            return $node->moveNode();
        }

    }

    public function prepend(Model $node) {
        $node->root   = $this->isRoot() ? $this : $this->getRoot();
        $node->parent = $this;

        if ($node->isPhantom()) {
            $node->action = self::ACTION_INSERT_PREPEND;
            return $node->createNode();
        } else {
            $node->action = self::ACTION_MOVE_PREPEND;
            return $node->moveNode();
        }

    }   

    public function appendTo(Model $parent, $data = array()) {
        $this->root   = $parent->isRoot() ? $parent : $parent->getRoot();
        $this->parent = $parent;

        if ($this->isPhantom()) {
            $this->action = self::ACTION_INSERT_APPEND;
            return $this->createNode($data);    
        } else {
            $this->action = self::ACTION_MOVE_APPEND;
            return $this->moveNode();
        }
    }

    public function prependTo(Model $parent, $data = array()) {
        $this->root   = $parent->isRoot() ? $parent : $parent->getRoot();
        $this->parent = $parent;

        if ($this->isPhantom()) {
            $this->action = self::ACTION_INSERT_PREPEND;
            return $this->createNode($data);    
        } else {
            $this->action = self::ACTION_MOVE_PREPEND;
            return $this->moveNode();
        }
        
    }

    public function insertBefore(Model $before) {
        if ( ! $before) {
            $this->addMessage("Target node paramater is required!");
            return false;
        }

        if ($before->isPhantom()) {
            $this->addMessage("Can't create node when target node is new!");
            return false;
        }
        
        $this->root   = $before->getRoot();
        $this->parent = $before->getParent();
        $this->before = $before;

        if ($this->isPhantom()) {
            $this->action = self::ACTION_INSERT_BEFORE;
            return $this->createNode(); 
        } else {
            $this->action = self::ACTION_MOVE_BEFORE;
            return $this->moveNode();
        }

    }

    public function insertAfter(Model $after) {
        if ( ! $after) {
            $this->addMessage("Target node paramater is required!");
            return false;
        }

        if ($after->isPhantom()) {
            $this->addMessage("Can't create node when target node is new!");
            return false;
        }

        $this->root = $after->getRoot();
        $this->parent = $after->getParent();
        $this->after = $after;

        if ($this->isPhantom()) {
            $this->action = self::ACTION_INSERT_AFTER;
            return $this->createNode(); 
        } else {
            $this->action = self::ACTION_MOVE_AFTER;
            return $this->moveNode();
        }
    }

    public function createNode($data = array()) {
        // validate root
        if ( ! $this->root) {
            $this->addMessage('Root node parameter is required!');
            return false;
        }

        if ($this->root->isPhantom()) {
            $this->addMessage("Can't create node when root node is new!");
            return false;
        }

        $root   = $this->root;
        $parent = $this->parent ? $this->parent : $root;
        
        // refresh root & parent (get actual data)
        if ( ! $parent->isRoot()) {
            $root->refreshNode();
            $parent->refreshNode(); 
        } else {
            $parent->refreshNode();
        }

        $action = $this->action;

        // validate parent
        if ($parent->isPhantom()) {
            $this->addMessage("Can't create node when parent node is new!");
            return false;
        }

        // validate action
        if ( ! $action) {
            $action = self::ACTION_INSERT_APPEND;
        }

        $link  = $this->getWriteConnection();
        $table = $this->getSource();

        $fieldLeft   = $this->getParamLeft();
        $fieldRight  = $this->getParamRight();
        $fieldRoot   = $this->getParamRoot();
        $fieldLevel  = $this->getParamLevel();
        $fieldId     = $this->getParamKey();
        $fieldPid    = $this->getParamParent();
        
        $rootValue   = $root->getKeyValue();
        
        $pidValue    = $parent->getKeyValue();

        if ($parent->isRoot()) {
            $pidValue = 0;
        }

        $parentDepth = $parent->getDepthValue();
        $parentPath  = $parent->getPathValue();

        $position = null;
        $level    = null;

        switch($action) {
            case self::ACTION_INSERT_APPEND:
                $position = $parent->getRightValue();
                $level    = $parent->getLevelValue() + 1;
                break;
            case self::ACTION_INSERT_PREPEND:
                $position = $parent->getLeftValue() + 1;
                $level    = $parent->getLevelValue() + 1;
                break;
            case self::ACTION_INSERT_BEFORE:
                $before   = $this->before;
                $before->refreshNode();

                $position = $before->getLeftValue();
                $level    = $before->getLevelValue();
                break;
            case self::ACTION_INSERT_AFTER:
                $after    = $this->after;
                $after->refreshNode();
                $position = $after->getRightValue() + 1;
                $level    = $after->getLevelValue();
                break;
            default:
                $this->addMessage("Action $action is not supported!");
                return false;
        }

        $result = false;
        
        try {
            $link->begin();

            // Create new space for node
            $sql = "UPDATE $table SET $fieldLeft = $fieldLeft + 2 
                    WHERE $fieldLeft >= $position AND $fieldRoot = $rootValue";

            $link->execute($sql);

            $sql = "UPDATE $table SET $fieldRight = $fieldRight + 2 
                    WHERE $fieldRight >= $position AND $fieldRoot = $rootValue";

            $link->execute($sql);

            if (method_exists($this, 'getDefaultValues')) {
                $defaults = $this->getDefaultValues();
                if (is_array($defaults)) {
                    foreach($defaults as $key => $val) {
                        $this->$key = $val;
                    }
                }
            }

            if (is_array($data)) {
                foreach($data as $key => $val) {
                    $this->$key = $val;
                }
            }

            $this->$fieldRoot  = $rootValue;
            $this->$fieldPid   = $pidValue;
            $this->$fieldLeft  = $position;
            $this->$fieldRight = $position + 1; 
            $this->$fieldLevel = $level;

            if ($this->create()) {
                $link->commit();

                $idValue     = $this->getKeyValue();
                $this->depth = $parentDepth + 1;
                $this->path  = $parentPath ? ($parentPath . '/' . $idValue) : $idValue;

                $result = true;
            } else {
                $link->rollback();
            }

        } catch(\Exception $e) {
            $result = false;

            $link->rollback();
            $this->addMessage($e->getMessage());
        }

        // invalidate operation
        $this->action = null;
        $this->root   = null;
        $this->parent = null;
        $this->before = null;
        $this->after  = null;

        return $result;
    }

    public function updateNode($data = array()) {
        // restrict update
        $excludes = array_values($this->_config->fields->toArray());
        
        // refresh node
        $this->refreshNode();

        if (is_array($data)) {
            foreach($data as $key => $val) {
                if (in_array($key, $excludes)) continue;
                $this->$key = $val;
            }
        }

        return $this->update();
    }

    /**
     * Delete current node and his children if needed
     *
     * @param  boolean $cascade TRUE to delete children
     *
     * @return boolean
     */
    public function deleteNode($cascade = true) {

        if ($this->isRoot()) {
            $this->addMessage("Can't delete root node!");
            return false;
        }

        // refresh node
        $this->refreshNode();

        $link  = $this->getWriteConnection();
        $table = $this->getSource();

        $fieldLeft  = $this->getParamLeft();
        $fieldRight = $this->getParamRight();
        $fieldRoot  = $this->getParamRoot();
        $fieldLevel = $this->getParamLevel();
        
        $leftValue  = $this->getLeftValue();
        $rightValue = $this->getRightValue();
        $rootValue  = $this->getRootValue();

        $result = false;

        try {
            $link->begin();

            if ($cascade) {
                // delete node and children
                $sql = "DELETE FROM $table 
                        WHERE 
                            ($fieldLeft >= $leftValue AND $fieldRight <= $rightValue) 
                            AND $fieldRoot = $rootValue";

                $link->execute($sql);

                // fix hole after deletion
                $offset = $rightValue + 1;
                $delta  = $leftValue - $rightValue - 1;

                $sql = sprintf(
                    "UPDATE $table SET $fieldLeft = $fieldLeft %+d 
                     WHERE $fieldLeft >= $offset AND $fieldRoot = $rootValue",
                     $delta
                );

                $link->execute($sql);

                $sql = sprintf(
                    "UPDATE $table SET $fieldRight = $fieldRight %+d 
                    WHERE $fieldRight >= $offset AND $fieldRoot = $rootValue",
                    $delta
                );

                $link->execute($sql);

                $link->commit();
                $result = true;

            } else {

                if ($this->delete()) {
                    // move children to existing parent
                    $sql = "UPDATE $table SET 
                                $fieldLeft = $fieldLeft - 1,
                                $fieldRight = $fieldRight - 1,
                                $fieldLevel = $fieldLevel - 1
                            WHERE 
                                ($fieldLeft >= $leftValue AND $fieldRight <= $rightValue)
                                AND $fieldRoot = $rootValue";

                    $link->execute($sql);

                    // fix hole
                    $offset = $rightValue + 1;
                    $delta  = -2;

                    $sql = sprintf(
                        "UPDATE $table SET $fieldLeft = $fieldLeft %+d 
                         WHERE $fieldLeft >= $offset AND $fieldRoot = $rootValue",
                        $delta
                    );

                    $link->execute($sql);

                    $sql = sprintf(
                        "UPDATE $table SET $fieldRight = $fieldRight %+d 
                         WHERE $fieldRight >= $offset AND $fieldRoot = $rootValue", 
                         $delta
                    );
                    
                    $link->execute($sql);
                    $link->commit();

                    $result = true;
                } else {

                    $link->rollback();
                    $result = false;

                }

            }
            
        } catch (\Exception $e) {
            $link->rollback();
            $this->addMessage($e->getMessage());

            $result = false;
        }
        
        return $result;
    }

    public static function move(Model $node, $npos) {
        $result = FALSE;

        $link  = $node->getWriteConnection();
        $table = $node->getSource();

        $lft = $node->getParamLeft();
        $rgt = $node->getParamRight();
        $rdn = $node->getParamRoot();

        // short var for readibilty
        $p = $npos;
        $l = (int) $node->$lft;
        $r = (int) $node->$rgt;
        $n = (int) $node->$rdn;

        try {
            $link->begin();

            $sql = "UPDATE $table
                    SET 
                        $lft = $lft + IF ($p > $r,
                            IF ($r < $lft AND $lft < $p,
                                $l - $r - 1,
                                IF ($l <= $lft AND $lft < $r,
                                    $p - $r - 1,
                                    0
                                )
                            ),
                            IF ($p <= $lft AND $lft < $l,
                                $r - $l + 1,
                                IF ($l <= $lft AND $lft < $r,
                                    $p - $l,
                                    0
                                )
                            )
                        ),
                        $rgt = $rgt + IF ($p > $r,
                            IF ($r < $rgt AND $rgt < $p,
                                $l - $r - 1,
                                IF ($l < $rgt AND $rgt <= $r,
                                    $p - $r - 1,
                                    0
                                )
                            ),
                            IF ($p <= $rgt AND $rgt < $l,
                                $r - $l + 1,
                                IF ($l < $rgt AND $rgt <= $r,
                                    $p - $l,
                                    0
                                )
                            )
                        )
                    WHERE ($r < $p OR $p < $l) AND $rdn = $n";
            
            $link->execute($sql);
            $link->commit();

            $result = TRUE;
        } catch(\Exception $e) {
            $link->rollback();
        }

        return $result;
    }

    public function moveNode() {
        
        if ($this->isRoot()) {
            $this->addMessage("Can't move root node!");
            return false;
        }

        if ( ! $this->root) {
            $this->addMessage("Root node parameter is required!");
            return false;
        }

        if ($this->root->isPhantom()) {
            $this->addMessage("Can't move node when root node is new!");
            return false;
        }

        $link   = $this->getWriteConnection();
        $table  = $this->getSource();

        $root   = $this->root;
        $parent = $this->parent ? $this->parent : $root;

        if ($parent->isPhantom()) {
            $this->addMessage("Can't move node when parent node is new!");
            return false;
        }

        // refresh root & parent (get actual data)
        if ( ! $parent->isRoot()) {
            // $root->refreshNode();
            // $parent->refreshNode();  
        } else {
            // $parent->refreshNode();
        }
        
       // $this->refreshNode();

        $action = $this->action;

        if ( ! $action) {
            $action = self::ACTION_MOVE_APPEND;
        }

        $fieldLeft  = $this->getParamLeft();
        $fieldRight = $this->getParamRight();
        $fieldRoot  = $this->getParamRoot();
        $fieldLevel = $this->getParamLevel();
        $fieldPid   = $this->getParamParent();
        
        $position = null;
        $depth    = null;
        $domain   = null;

        $leftValue  = $this->getLeftValue();
        $rightValue = $this->getRightValue();
        $levelValue = $this->getLevelValue();
        $rootValue  = $this->getRootValue();

        $pidValue   = $parent->getKeyValue();

        if ($parent->isRoot()) {
            $pidValue = 0;
        }

        switch($action) {
            case self::ACTION_MOVE_APPEND:
                $position = $parent->getRightValue();
                $depth    = $parent->getLevelValue() - $levelValue + 1;
                $domain   = $parent->getRootValue();
                break;
            case self::ACTION_MOVE_PREPEND:
                $position = $parent->getLeftValue() + 1;
                $depth    = $parent->getLevelValue() - $levelValue + 1;
                $domain   = $parent->getRootValue();
                break;
            case self::ACTION_MOVE_BEFORE:
                $before = $this->before;
                // $before->refreshNode();
                
                $position = $before->getLeftValue();
                $depth    = $before->getLevelValue() - $levelValue + 0;
                $domain   = $before->getRootValue();
                break;
            case self::ACTION_MOVE_AFTER:
                $after = $this->after;
                // $after->refreshNode();

                $position = $after->getRightValue() + 1;
                $depth    = $after->getLevelValue() - $levelValue + 0;
                $domain   = $after->getRootValue();
                break;
            default:
                $this->addMessage("Action $action is not supported!");
                return false;
        }

        $result = false;

        try {
            $link->begin();

            $size = $rightValue - $leftValue + 1;

            $sql = sprintf(
                "UPDATE $table SET $fieldLeft = $fieldLeft %+d 
                 WHERE $fieldLeft >= $position AND $fieldRoot = $domain",
                $size
            );

            $link->execute($sql);

            $sql = sprintf(
                "UPDATE $table SET $fieldRight = $fieldRight %+d 
                WHERE $fieldRight >= $position AND $fieldRoot = $domain",
                $size
            );

            $link->execute($sql);
            
            if ($leftValue >= $position) {
                $leftValue  += $size;
                $rightValue += $size;
            }

            $sql = sprintf(
                "UPDATE $table 
                 SET 
                    $fieldLevel = $fieldLevel %+d,
                    $fieldPid   = $pidValue
                 WHERE 
                    $fieldLeft >= $leftValue AND $fieldRight <= $rightValue 
                    AND $fieldRoot = $domain",
                $depth
            );
            
            $link->execute($sql);

            $sql = sprintf(
                "UPDATE $table SET 
                    $fieldLeft  = $fieldLeft %+d,
                    $fieldRight = $fieldRight %+d
                 WHERE 
                    ($fieldLeft >= $leftValue AND $fieldRight <= $rightValue) 
                    AND $fieldRoot = $domain",
                $position - $leftValue,
                $position - $leftValue 
            );
            
            $link->execute($sql);

            $fixgap = $rightValue + 1;

            $sql = sprintf(
                "UPDATE $table SET $fieldLeft = $fieldLeft %+d 
                 WHERE $fieldLeft >= $fixgap AND $fieldRoot = $domain",
                -$size
            );

            $link->execute($sql);

            $sql = sprintf(
                "UPDATE $table SET $fieldRight = $fieldRight %+d 
                 WHERE $fieldRight >= $fixgap AND $fieldRoot = $domain",
                -$size
            );

            $link->execute($sql);

            $link->commit();

            $result = true;
            
            // refresh current node
            
            $this->refreshNode();
            
            // update phantoms props
            
        } catch (\Exception $e) {
            $link->rollback();
            $this->addMessage($e->getMessage());
            $result = false;
        }

        // invalidate operation
        $this->action = null;
        $this->root   = null;
        $this->parent = null;
        $this->before = null;
        $this->after  = null;

        return $result;
    }

    public function getRoot() {
        return self::findRoot($this->getRootValue(), FALSE);
    }

    public function getParent() {
        return self::findNodeById($this->getParentValue());
    }

    public function getAncestors($params = array(), $reverse = FALSE) {
        $path = explode('/', $this->getPathValue());
        
        array_pop($path);

        if ( ! empty($path)) {

            $fieldKey = $this->getParamKey();
            $fieldLevel = $this->getParamLevel();
            $fieldLeft = $this->getParamLeft();
            $fieldRight = $this->getParamRight();

            // construct `where in`
            $bind = array();

            $where = array_map(
                function($id) use (&$bind){
                    $token = "path_{$id}";
                    $bind[$token] = $id;
                    return ":{$token}:";
                },
                $path
            );

            $where = "$fieldKey IN (".implode(', ', $where).")";

            $query = (new TreeQuery($this, FALSE))
                ->data('root', $this->getRootValue())
                ->params($params)
                ->where($where, $bind)
                ->orderBy($reverse ? "$fieldRight - $fieldLeft" : "$fieldLeft");

            $nodes = $query->execute();
            unset($query);

            return $nodes;
        }

        return self::emptyNodes();
    }
    
    public function getDescendants($params = array()) {
        $query = (new TreeQuery($this, FALSE))
            ->data('root', $this->getRootValue())
            ->data('node', $this->getKeyValue())
            ->compile(TreeQuery::COMPILE_DESCENDANT)
            ->params($params);

        $nodes = $query->execute();
        unset($query);

        return $nodes;
    }

    public function getChildren($params = array()) {
        $query = (new TreeQuery($this, FALSE))
            ->data('root', $this->getRootValue())
            ->data('node', $this->getKeyValue())
            ->data('filter', 'HAVING depth = '.($this->getDepthValue() + 1))
            ->compile(TreeQuery::COMPILE_DESCENDANT)
            ->params($params);

        $nodes = $query->execute();
        unset($query);

        return $nodes;
    }

    public function getSiblings($params = array()) {
        $parent = $this->getParent();
        if ( ! $parent) $parent = $this->getRoot();

        if ($parent) {
            
            $fieldKey = $this->getParamKey();
            $keyValue = $this->getKeyValue();

            $query = (new TreeQuery($this, FALSE))
                ->data('root', $this->getRootValue())
                ->data('node', $parent->getKeyValue())
                ->data('filter', 'HAVING depth = '.($parent->getDepthValue() + 1))
                ->compile(TreeQuery::COMPILE_DESCENDANT)
                ->where("$fieldKey <> $keyValue")
                ->params($params);

            $nodes = $query->execute();
            unset($query);

            return $nodes;
        }
        return FALSE;
    }

    /**
     * Get previous sibling
     *
     * @deprecated use `Model::getPrev()`
     */
    public function getPrevious() {
        return $this->getPrev();
    }

    public function getPrev() {
        $fieldRight = $this->getParamRight();

        $query = (new TreeQuery($this, FALSE))
            ->data('root', $this->getRootValue())
            ->data('filter', 'HAVING depth = '.$this->getDepthValue())
            ->where("$fieldRight < ".($this->getLeftValue()));

        $node = $query->execute()->getLast();
        return $node;
    }

    public function getNext() {
        $fieldLeft = $this->getParamLeft();

        $query = (new TreeQuery($this, FALSE))
            ->data('root', $this->getRootValue())
            ->data('filter', 'HAVING depth = '.$this->getDepthValue())
            ->where("$fieldLeft > ".$this->getRightValue());

        $node = $query->execute()->getFirst();
        unset($query);

        return $node;
    }

    public function addMessage($message) {
        $this->appendMessage(new Message($message));
    }

    public function refreshNode() {
        if ( ! $this->isPhantom()) {
            $node = self::findNodeById($this->getKeyValue());
            $this->path  = $node->path;
            $this->depth = $node->depth;
        }
        return $this;
    }

    public function nodify() {
        if ( ! $this->isPhantom()) {
            $node = self::findNodeById($this->getKeyValue());
            $this->path  = $node->path;
            $this->depth = $node->depth;
        }
        return $this;
    }

    /**
     * Added by elka
     */
    public static function findRoots($params = array(), $object = FALSE) {
        $base = self::_getInstance();
        $fieldLevel = $base->getParamLevel();

        if (is_null($params)) $params = array();
        $params[$fieldLevel] = -1;

        $result = self::find(self::params($params));
        $array = array();
        
        foreach($result as $root) {
            $root->depth = -1;
            $root->path = NULL;
            $array[] = $root->toArray();
        }

        return $array;
    }

    public static function findRoot($params = array(), $force = FALSE) {
        $base = self::_getInstance();
        $fieldLevel = $base->getParamLevel();
        
        if (is_numeric($params)) {
            $identity = $params;
            $fieldKey = $base->getParamKey();

            $params = array();
            $params[$fieldKey] = $identity;
        }

        $params[$fieldLevel] = -1;
        $root = self::findFirst(self::params($params));
        
        if ($root) {
            $root->depth = -1;
            $root->path = NULL;
        } else {
            if ($force) {
                 $root = new static();
                if ( ! $root->createRoot($params)) {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }
        return $root;
    }

    public static function findNodes(Model $root, $params = array()) {
        if ($root) {
            $query = (new TreeQuery($root, FALSE))
                ->data('root', $root->getKeyValue())
                ->params($params);

            $result = $query->execute();
            unset($query);

            return $result;
        }
        return self::emptyNodes();
    }

    public static function findNodesIn($ids) {
        $root = self::_getRootValue($ids[0]);
        $base = self::_getInstance();

        if ($root) {
            $field = $base->getParamKey();
            
            $params = array();
            $params[$field] = array('IN', $ids);

            $query = (new TreeQuery($base, FALSE))
                ->data('root', $root)
                ->params($params);

            $result = $query->execute();
            unset($query);

            return $result;
        }
        return self::emptyNodes();
    }

    public static function findNode(Model $root, $params = array()) {
        if ($root) {
            $query = (new TreeQuery($root, FALSE))
                ->data('root', $root->getKeyValue())
                ->params($params)
                ->limit(1);

            $node = $query->execute()->getFirst();
            unset($query);

            return $node;
        }
        return FALSE;
    }

    public static function findNodeById($id) {
        $base = self::_getInstance();
        
        $root = self::_getRootValue($id);
        

        if ($root) {
            $params = array();
            $field  = $base->getParamKey();
            $params[$field] = $id;
            // $base->_bm->start('x');
            $query = (new TreeQuery($base, FALSE))
                ->data('root', $root)
                ->params($params)
                ->limit(1);

            $node = $query->execute()->getFirst();
            unset($query);
            // $base->_bm->stop('x');

            return $node;
        }
        return FALSE;
    }

    public static function emptyNodes() {
        $base = self::_getInstance();
        return new Resultset(NULL, $base, NULL);
    }

    public static function findTree(Model $root) {
        return (new TreeQuery($root))->data('root', $root->getKeyValue());
    }

    /**
     * Use Model::findTree() instead
     * @deprecated
     */
    public static function fetchTree(Model $root) {
        return self::findTree($root);
    }

    public static function findInvalidNodes(Model $root) {
        $base = self::_getInstance();
        $link = $base->getReadConnection();
        $table = $base->getSource();
        $index = $base->getIndexName();

        $fieldKey = $base->getParamKey();
        $fieldLeft = $base->getParamLeft();
        $fieldRoot = $base->getParamRoot();

        $rootValue = $root->$fieldKey;

        $inner  = "SELECT ";
        $inner .= "\n\tMAX($fieldKey) AS max_id, $fieldLeft ";
        $inner .= "\nFROM $table FORCE INDEX($index) ";
        $inner .= "\nWHERE $fieldRoot = $rootValue ";
        $inner .= "\nGROUP BY $fieldLeft";

        $outer  = "SELECT a.* ";
        $outer .= "\nFROM $table a FORCE INDEX ($index) ";
        $outer .= "\nLEFT JOIN($inner) b ON (a.$fieldKey = b.max_id AND a.$fieldLeft = b.$fieldLeft) ";
        $outer .= "\nWHERE a.$fieldRoot = $rootValue AND b.max_id IS NULL";

        return new Resultset(NULL, $base, $link->query($outer));
    }

    public static function relocateInvalidNodes(Model $root, $callback = null) {
        $invalid  = self::findInvalidNodes($root);
        $affected = 0;

        if ($invalid->count() > 0) {
            $fieldLeft  = $root->getParamLeft();
            $fieldRight = $root->getParamRight();

            foreach($invalid as $node) {
                $node->delete();

                $raw = $node->toArray();
                unset($raw[$fieldLeft], $raw[$fieldRight]);

                $new = new static();

                foreach($raw as $key => $val) {
                    $new->$key = $val;
                }

                if ($new->prependTo($root)) {
                    $affected++;

                    if (is_callable($callback)) {
                        call_user_func_array($callback, array($new));
                    }
                }
            }
        }

        return $affected;
    }
    
    public static function makeTree(Resultset $nodes) {
        $tree = array();
        $size = 0;

        if ($nodes->count() > 0) {
            
            $stack = array();
            
            foreach($nodes as $node) {
                $item = $node->toArray();
                $size = count($stack);

                while($size > 0 AND $stack[$size - 1]['depth'] >= $item['depth']) {
                    array_pop($stack);
                    $size--;
                }

                if ($size == 0) {
                    $n = count($tree);
                    $tree[$n] = $item;
                    $stack[] =& $tree[$n];
                } else {
                    if ( ! isset($stack[$size - 1]['children'])) {
                        $stack[$size - 1]['children'] = array();
                    }
                    $n = count($stack[$size - 1]['children']);
                    $stack[$size - 1]['children'][$n] = $item;
                    $stack[] =& $stack[$size - 1]['children'][$n];
                }

            }

        }
        return $tree;
    }

    public function compileTemplate($template) {
        $node = $this;
        $compiled = preg_replace_callback(
            '/\{(\w+)\}/', 
            function($matches) use ($node) {
                $field = $matches[1];
                return $node->$field;
            }, 
            $template
        );
        return $compiled;
    }

    public static function plotTree(Resultset $nodes, $template = null) {
        $list = '';

        if (empty($template)) {
            $base = self::_getInstance();
            $template = '<span>Node - {' . $base->getParamKey() . '}</span>';
        }

        if ($nodes->count() > 0) {

            $first = $nodes->getFirst();
            $currDepth = $first->getDepthValue();
            $delta = $currDepth - 0;

            $counter = 0;
            $list = '<ul>';

            foreach($nodes as $node) {
                $nodeDepth = $node->depth;

                if ($nodeDepth == $currDepth) {
                    if ($counter > 0) {
                        $list .= '</li>';
                    }
                } else if ($nodeDepth > $currDepth) {
                    $list .= '<ul>';
                    $currDepth = $currDepth + ($nodeDepth - $currDepth);
                } else if ($nodeDepth < $currDepth) {
                    $list .= str_repeat('</li></ul>', $currDepth - $nodeDepth) . '</li>';
                    $currDepth = $currDepth - ($currDepth - $nodeDepth);
                }

                $content = '';

                if (is_callable($template)) {
                    $content = call_user_func_array($template, array($node));
                } else {
                    $content = $node->compileTemplate($template);
                }

                $list .= '<li>' . $content;
                ++$counter;
            }
            $list .= str_repeat('</li></ul>', $nodeDepth - $delta).'</li>';
            $list .= '</ul>';
        }

        return $list;
    }

}

/** Add */
/*
-- moves a subtree before the specified position
-- if the position is the rgt of a node, the subtree will be its last child
-- if the position is the lft of a node, the subtree will be inserted before
-- @param l the lft of the subtree to move
-- @param r the rgt of the subtree to move
-- @param p the position to move the subtree before
update tree
set
    lft = lft + if (:p > :r,
        if (:r < lft and lft < :p,
            :l - :r - 1,
            if (:l <= lft and lft < :r,
                :p - :r - 1,
                0
            )
        ),
        if (:p <= lft and lft < :l,
            :r - :l + 1,
            if (:l <= lft and lft < :r,
                :p - :l,
                0
            )
        )
    ),
    rgt = rgt + if (:p > :r,
        if (:r < rgt and rgt < :p,
            :l - :r - 1,
            if (:l < rgt and rgt <= :r,
                :p - :r - 1,
                0
            )
        ),
        if (:p <= rgt and rgt < :l,
            :r - :l + 1,
            if (:l < rgt and rgt <= :r,
                :p - :l,
                0
            )
        )
    )
where :r < :p or :p < :l;

-- swaps two subtrees, where A is the subtree having the lower lgt/rgt values
-- and B is the subtree having the higher ones
-- @param al the lft of subtree A
-- @param ar the rgt of subtree A, must be lower than bl
-- @param bl the lft of subtree B, must be higher than ar
-- @param br the rgt of subtree B
update tree
set
    lft = lft + @offset := if (lft > :ar and rgt < :bl,
        :br - :bl - :ar + :al,
        if (lft < :bl, :br - :ar, :al - :bl)
    ),
    rgt = rgt + @offset
where lft >= :al and lft <= :br and :ar < :bl;

*/