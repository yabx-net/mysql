<?php

namespace Yabx\MySQL\Exceptions;

class DuplicateEntryException extends QueryException {

    private string $entry;
    private string $key;

    public function __construct(string $message, string $query) {
        preg_match('/Duplicate entry \'(.*)\' for key \'(.*)\'/', $message, $m);
        $this->entry = $m[1];
        $this->key = $m[2];
        parent::__construct($message, 1062, $query);
    }

    public function getEntry(): mixed {
        return $this->entry;
    }

    public function getKey(): mixed {
        return $this->key;
    }

}
