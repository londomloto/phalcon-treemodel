<?php
namespace Londo;

use Phalcon\Mvc\Model\Exception as ModelException,
    Londo\ITreeBuilder,
    Londo\ITreeModel;

class TreeResult implements \jsonSerializable {
    
    public function __construct(ITreeBuilder $builder, ITreeModel $root) {
        if ( ! $root->isRoot()) {
            throw new ModelException('TreeResult required valid root as second argument!');
        }
    }
    
    public function jsonSerialize() {
        
    }
    
    public function __toString() {
        
    }
    
}
