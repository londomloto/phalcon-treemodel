<?php
namespace Cores;

use Phalcon\Mvc\Model\Resultset\Simple as Resultset;

class TreeTable implements \JsonSerializable {

    public $success = TRUE;
    public $data = NULL;
    public $total = 0;
    public $count = 0;

    public function __construct(Resultset $data, $total) {
        $this->data  = $data;
        $this->total = $total;
        $this->count = $data->count();
    }

    public function jsonSerialize() {
        $data = $this->data;

        if ($data instanceof Resultset) {
            $data = $data->toArray();
        }

        return array(
            'success' => $this->success,
            'data' => $data,
            'count' => $this->count,
            'total' => $this->total
        );
    }

    public function filter($callback = NULL) {
        if (is_callable($callback)) {
            $this->data = $this->data->filter($callback);
        }
        return $this;
    }

    public function toArray() {
        $this->data = $this->data->toArray();
        return $this;
    }
}