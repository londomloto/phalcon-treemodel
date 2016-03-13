<?php
namespace Cores;

use Phalcon\Mvc\Model\Exception as ModelException,
    Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Interfaces\ITreeModel,
    Interfaces\ITreeQuery,
    Cores\TreeTable;

class TreeQuery implements ITreeQuery {

    const COMPILE_NODE = 'node';
    const COMPILE_PARENT = 'parent';
    const COMPILE_DESCENDANT = 'descendant';
    const COMPILE_ANCESTOR = 'ancestor';

    private $_base;
    private $_data;
    private $_pagination;
    private $_compile;
    
    private $_alias;
    private $_join;
    private $_columns;
    private $_params;
    private $_where;
    private $_group;
    private $_order;
    private $_bind;
    private $_limit;
    private $_start;
    private $_query;

    private $_fields;

    public function __construct(ITreeModel $base, $pagination = TRUE) {

        $this->_base = $base;
        $this->_data = array();
        $this->_compile = self::COMPILE_NODE;
        $this->_pagination = $pagination;

        $this->_alias = 'tree';
        $this->_join = array();
        $this->_columns = array();
        $this->_params = array();
        $this->_where = array('and' => array(), 'or' => array());
        $this->_group = array();
        $this->_order = array();
        $this->_bind = array();
        $this->_limit = FALSE;
        $this->_start = 0;
        $this->_query = '';

        $this->_fields = array_map(
            function($field){ return $field->name; },
            $this->_base->fieldData()
        );
        
    }

    public function compile($type = NULL) {
        $this->_compile = empty($type) ? self::COMPILE_NODE : $type;
        return $this;
    }

    public function data($key, $value = NULL) {
        if (is_array($key)) {
            foreach($key as $k => $v) {
                $this->_data[$k] = $v;
            }
        } else {
            $this->_data[$key] = $value;    
        }
        return $this;
    }

    public function alias($alias) {
        $this->_alias = $alias;
        return $this;
    }

    public function columns($columns) {
        $trim = '/(?:(?<=,)|^)\s+|\s+$/';

        if ( ! is_array($columns)) {
            $columns = explode(',', preg_replace($trim, '', $columns));
        } else {
            $temp = array();
            foreach($columns as $col) {
                $part = explode(',', preg_replace($trim, '', $col));
                $temp = array_merge($temp, $part);
            }
            $columns = $temp;
        }

        $this->_columns = $columns;
        return $this;
    }

    public function join($model, $conditions = '', $alias = '', $type = '') {
        if ( ! class_exists($model)) {
            throw new ModelException("Join model doesn't exists!");
        }
        $dummy = new $model();
        $table = $dummy->getSource();
        unset($dummy);

        $type = $type ? strtoupper($type).' ': '';
        $join = "{$type}JOIN $table $alias";

        if ( ! empty($conditions)) {
            $join .= " ON ($conditions)";
        }

        $this->_join[] = $join;
        return $this;
    }

    public function params($params) {
        $vars = $this->_base->params($params);
        
        if (isset($vars['conditions'])) {
            $this->_where['and'][] = $vars['conditions'];
        }

        if (isset($vars['group'])) {
            $this->_group[] = $vars['group'];
        }

        if (isset($vars['order'])) {
            $this->_order[] = $vars['order'];
        }

        if (isset($vars['limit'])) {
            $this->_limit = $vars['limit']['number'];
            $this->_start = $vars['limit']['offset'];
        }

        if (isset($vars['bind'])) {
            $this->_bind = array_merge($this->_bind, $vars['bind']);
        }

        return $this;
    }

    /**
     * Add where condition
     * Example:
     *     $query->where(1);
     *     $query->where('id = 1');
     *     $query->where('user = :user: and status = :status:', array('operator', 1))
     */
    public function where($conditions, $bind = array()) {
        if (is_numeric($conditions)) {
            $field = $this->_base->getParamKey();
            $this->_where['and'][] = "$field = $conditions";
        } else {
            $this->_where['and'][] = $conditions;
            $this->_bind = array_merge($this->_bind, $bind);
        }
        return $this;
    }

    public function andWhere($conditions, $bind = array()) {
        $this->_where['and'][] = $conditions;
        $this->_bind = array_merge($this->_bind, $bind);
        return $this;
    }

    public function orWhere($conditions, $bind = array()) {
        $this->_where['or'][] = $conditions;
        $this->_bind = array_merge($this->_bind, $bind);
        return $this;
    }

    public function groupBy($group) {
        if (is_array($group)) {
            $group = implode(' ', $group);
        }
        $this->_group[] = $group;
        return $this;
    }

    public function orderBy($order) {
        if (is_array($order)) {
            $order = implode(' ', $order);
        }
        $this->_order[] = $order;
        return $this;
    }

    public function limit($limit, $start = 0) {
        $this->_limit = $limit;
        $this->_start = $start;
        return $this;
    }

    public function pagination($enabled = TRUE) {
        $this->_pagination = $enabled;
        return $this;
    }

    private function _getInnerColumns($prefix = 'n') {
        return $prefix.'.*';
        $columns = array();
        if ( ! empty($this->_columns)) {
            $fields = $this->_fields;
            $alias  = $this->_alias;
            $string = '@'.implode('@', $this->_columns).'@';

            foreach($fields as $f) {
                $pattern = '/(?:(?<='.$alias.'\.)|@)('.$f.'|\*)/';
                if (preg_match($pattern, $string, $matches)) {
                    $columns[] = $prefix.'.'.$matches[1];
                    if ($matches[1] == '*') break;
                }
            }
        }
        return ! empty($columns) ? implode(', ', $columns) : "{$prefix}.*";
    }

    private function _getOuterColumns() {
        return ! empty($this->_columns)
            ? "\n\t".implode(", \n\t", array_unique(array_merge($this->_columns, array('path', 'depth'))))
            : $this->_alias.'.*';
    }

    private function _listFields() {
        $fields = array();
        foreach($this->_columns as $col) {
            if ($col == '*' || preg_match('/^'.$this->_alias.'\.*/i', $col)) {
                $fields = array_merge($fields, $this->_fields);
            } else if (preg_match('/^([^.]+)$/', $col, $matches)) {
                $fields[] = $matches[1];
            } else if (preg_match('/\s+AS\s+(.*)/i', $col, $matches)) {
                $fields[] = $matches[1];
            }
        }
        // always add `depth` and `path'
        $fields[] = 'depth';
        $fields[] = 'path';

        return $fields;
    }

    private function _getConditions($prefix = '') {
        $conditions = '';

        if ( ! empty($this->_where['and'])) {
            $conditions .= "AND (".implode(' AND ', $this->_where['and']).')';
        }

        if ( ! empty($this->_where['or'])) {
            $conditions .= "OR (".implode(' AND ', $this->_where['or']).')';
        }

        if ( ! empty($conditions) && ! empty($prefix)) {
            $fields = $this->_fields;
            $conditions = preg_replace_callback(
                '/([a-z_`]+\.)?([a-z_`]+)\s+/is',
                function($match) use ($fields, $prefix){
                    if (empty($match[1])) {
                        $name = str_replace('`', '', $match[2]);
                        if (in_array($name, $fields)) {
                            return $prefix.'.'.$match[2].' ';
                        }
                    }
                    return $match[0];
                },
                $conditions
            );
        }

        return $conditions;
    }

    private function _getJoin() {
        if ( ! empty($this->_join)) {
            return "\n\t".implode("\n\t", $this->_join);    
        }
        return '';
    }

    private function _getGroup() {
        if ( ! empty($this->_group)) {
            return "\nGROUP BY ".implode(', ', $this->_group);    
        }
        return '';
    }

    private function _getOrder() {
        if ( ! empty($this->_order)) {
            return "\nORDER BY ".implode(', ', $this->_order);    
        }
        return '';
    }

    private function _getLimit() {
        return ! empty($this->_limit) 
            ? "\nLIMIT $this->_start, $this->_limit" 
            : "";
    }

    private function _parse($template, $data) {
        return preg_replace_callback(
            '/\{([^\}]+)\}/', 
            function($match) use ($data){
                if (isset($data[$match[1]])) {
                    return $data[$match[1]];
                }
                return $match[0];
            },
            $template
        );
    }

    private function _templateNode($data) {
        $table = $this->_base->getSource();

        $fdkey = $this->_base->getParamKey();
        $fdrdn = $this->_base->getParamRoot();
        $fdlft = $this->_base->getParamLeft();
        $fdrgt = $this->_base->getParamRight();
        $fdlvl = $this->_base->getParamLevel();

        $template = 
            "SELECT {sql_calc} 
                {columns}, 
                (COUNT(p.$fdkey) - 1) AS depth, 
                (GROUP_CONCAT(p.$fdkey ORDER BY p.$fdlft SEPARATOR '/')) AS path 
             FROM 
                $table n, 
                $table p
             WHERE 
                (n.$fdlft BETWEEN p.$fdlft AND p.$fdrgt) 
                AND (n.$fdrdn = {root} AND p.$fdrdn = {root}) 
                AND (p.$fdlvl > -1) {conditions} 
             GROUP BY n.$fdkey {filter}
             ORDER BY n.$fdlft 
            ";

        return $this->_parse($template, $data);
    }

    private function _templateParent($data) {
        $table = $this->_base->getSource();

        $fdkey = $this->_base->getParamKey();
        $fdrdn = $this->_base->getParamRoot();
        $fdlft = $this->_base->getParamLeft();
        $fdrgt = $this->_base->getParamRight();
        $fdlvl = $this->_base->getParamLevel();

        $template = 
            "SELECT 
                {columns} 
             FROM 
                $table n, 
                $table p
             WHERE 
                (n.$fdlft BETWEEN p.$fdlft AND p.$fdrgt) 
                AND (n.$fdrdn = {root} AND p.$fdrdn = {root}) 
                AND (p.$fdlvl > -1) 
                AND (n.$fdkey = {node})  {conditions}  
             ORDER BY p.$fdrgt - p.$fdlft 
             LIMIT 1, 1
            ";

        return $this->_parse($template, $data);
    }

    private function _templateAncestor($data) {
        $table = $this->_base->getSource();

        $fdkey = $this->_base->getParamKey();
        $fdrdn = $this->_base->getParamRoot();
        $fdlft = $this->_base->getParamLeft();
        $fdrgt = $this->_base->getParamRight();
        $fdlvl = $this->_base->getParamLevel();

        $template = 
            "SELECT 
                {columns}
             FROM 
                $table n, 
                $table p
             WHERE 
                (n.$fdlft BETWEEN p.$fdlft AND p.$fdrgt) 
                AND (n.$fdrdn = {root} AND p.$fdrdn = {root}) 
                AND (p.$fdlvl > -1)
                AND (n.$fdkey = {node}) 
                AND (p.$fdkey <> {node}) {conditions} 
            ";

        return $this->_parse($template, $data);
    }

    private function _templateDescendant($data) {
        $table = $this->_base->getSource();

        $fdkey = $this->_base->getParamKey();
        $fdrdn = $this->_base->getParamRoot();
        $fdlft = $this->_base->getParamLeft();
        $fdrgt = $this->_base->getParamRight();
        $fdlvl = $this->_base->getParamLevel();

        $template = 
            "SELECT 
                    {columns},
                    (COUNT(p.$fdkey) - 1) as depth,
                    (GROUP_CONCAT(p.$fdkey ORDER BY p.$fdlft SEPARATOR '/')) as path
                FROM 
                    $table n,
                    $table p,
                    $table sp
                WHERE 
                    (n.$fdlft BETWEEN p.$fdlft AND p.$fdrgt) 
                    AND (n.$fdlft BETWEEN sp.$fdlft AND sp.$fdrgt) 
                    AND (p.$fdrdn = {root} AND n.$fdrdn = {root} AND sp.$fdrdn = {root})
                    AND (p.$fdlvl > -1)
                    AND (sp.$fdlvl > -1) 
                    AND (sp.$fdkey = {node})
                    AND (n.$fdkey <> {node}) {conditions} 
                GROUP BY n.$fdkey {filter} 
                ORDER BY n.$fdlft";

        return $this->_parse($template, $data);
    }

    private function _subquery() {
        return ! empty($this->_join) || ! empty($this->_group) || ! empty($this->_order);
    }

    private function _compileNode($data) {
        if ($this->_subquery()) {
            $query  = "SELECT SQL_CALC_FOUND_ROWS ";
            $query .= $this->_getOuterColumns()." ";
            $query .= "\nFROM (";
            
            $inner = $this->_templateNode(array(
                'sql_calc' => '',
                'columns' => $this->_getInnerColumns(),
                'root' => $data['root'],
                'conditions' => '',
                'filter' => isset($data['filter']) ? $data['filter'] : ''
            ));

            $query .= $inner;
            $query .= "\n) ".$this->_alias;
            $query .= $this->_getJoin();
            $query .= "\nWHERE 1 = 1 ";
            $query .= $this->_getConditions();
            $query .= $this->_getGroup();
            $query .= $this->_getOrder();
            $query .= $this->_getLimit();
        } else {
            $query = $this->_templateNode(array(
                'sql_calc' => 'SQL_CALC_FOUND_ROWS',
                'columns' => $this->_getInnerColumns(),
                'root' => $data['root'],
                'conditions' => $this->_getConditions('n'),
                'filter' => isset($data['filter']) ? $data['filter'] : ''
            ));
            $query .= $this->_getLimit();
        }

        $this->_query = $query;
    }

    private function _compileParent($data) {
        $alias = $this->_alias;

        if ($this->_subquery()) {
            $query  = "SELECT ";
            $query .= "\n\t{$alias}.* ";
            $query .= "\nFROM (";
            $inner  = $this->_templateParent(array(
                'columns' => $this->_getInnerColumns('p'),
                'root' => $data['root'],
                'node' => $data['node'],
                'conditions' => ''
            ));
            $query .= $inner;
            $query .= ") ".$this->_alias;
            $query .= $this->_getJoin();
            $query .= "\nWHERE 1 = 1 ";
            $query .= $this->_getConditions();
        } else {
            $query = $this->_templateParent(array(
                'columns' => $this->_getInnerColumns('p'),
                'root' => $data['root'],
                'node' => $data['node'],
                'conditions' => $this->_getConditions('p')
            ));    
        }

        $this->_query = $query;
    }

    private function _compileDescendant($data) {
        $alias = $this->_alias;
        if ($this->_subquery()) {
            $query  = "SELECT ";
            $query .= "\n\t{$alias}.* ";
            $query .= "\nFROM (";
            $inner  = $this->_templateDescendant(array(
                'columns' => $this->_getInnerColumns('n'),
                'root' => $data['root'],
                'node' => $data['node'],
                'filter' => $data['filter'],
                'conditions' => ''
            ));
            $query .= $inner;
            $query .= ") ".$this->_alias;
            $query .= $this->_getJoin();
            $query .= "\nWHERE 1 = 1 ";
            $query .= $this->_getConditions();

        } else {
            $query = $this->_templateDescendant(array(
                'columns' => $this->_getInnerColumns('n'),
                'root' => $data['root'],
                'node' => $data['node'],
                'filter' => $data['filter'],
                'conditions' => $this->_getConditions('n')
            ));
            $query .= $this->_getLimit();
        }
        $this->_query = $query;
    }

    private function _compileAncestor($data) {
        $alias = $this->_alias;

        if ($this->_subquery()) {
            $query  = "SELECT ";
            $query .= "\n\t{$alias}.* ";
            $query .= "\nFROM (";
            $inner  = $this->_templateAncestor(array(
                'columns' => $this->_getInnerColumns('p'),
                'root' => $data['root'],
                'node' => $data['node'],
                'conditions' => ''
            ));
            $query .= $inner;
            $query .= ") $alias ";
            $query .= $this->_getJoin();
            $query .= "\nWHERE 1 = 1 ";
            $query .= $this->_getConditions();
        } else {
            $query = $this->_templateAncestor(array(
                'columns' => $this->_getInnerColumns('p'),
                'root' => $data['root'],
                'node' => $data['node'],
                'conditions' => $this->_getConditions('p')
            ));
            $query .= $this->_getLimit();
        }
        $this->_query = $query;
    }

    public function __toString() {
        return $this->_query;
    }

    public function execute() {
        // compile first
        $method = "_compile".ucwords($this->_compile);
        if (method_exists($this, $method)) {
            $this->$method($this->_data);
        }

        $base  = $this->_base;
        $bind  = $this->_bind;
        $link  = $this->_base->getReadConnection();
        $query = preg_replace('/:([^:]+):/', ':$1', $this->_query);

        $data  = new TreeResult(
            NULL,
            $base, 
            $link->query($query, $bind),
            NULL,
            NULL,
            $this->_listFields()
        );
        
        if ($this->_pagination) {
            $total = $link->fetchOne("SELECT FOUND_ROWS() as total");
            $total = (int) $total['total'];
            return new TreeTable($data, $total);
        }

        return $data;
    }

}

class TreeResult extends Resultset {

    private $_fields = array();

    public function __construct(
        $colmap,
        $model, 
        $result, 
        $cache = NULL, 
        $snapshoot = NULL, 
        $fields = NULL
    ){
        parent::__construct($colmap, $model, $result, $cache, $snapshoot);
        $this->_fields = $fields;
    }

    public function filter($callback) {
        if (empty($this->_fields)) {
            return parent::filter($callback);
        }

        $fields  = $this->_fields;
        $records = array();

        foreach($this as $rec) {
            $params = array($rec, $fields);
            $result = call_user_func_array($callback, $params);

            if ( ! is_array($result) && ! is_object($result)) 
                continue;

            $records[] = $result;
        }

        return $records;
    }

}