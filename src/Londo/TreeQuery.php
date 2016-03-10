<?php
namespace Londo;

use Phalcon\Mvc\Model\Resultset\Simpel as Resultset,
    Londo\ITreeModel;

class TreeQuery {
    
    private $query;
    private $base;
    
    public function __construct(ITreeModel $base) {
        $this->base = $base;
    }
    
    public function execute() {
        $link = $this->base->getReadConnection();
        return new Resultset(NULL, $this->base, $link->query($this->query));
    }
    
}
