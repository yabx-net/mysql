<?php

namespace Yabx\MySQL\Exceptions;

use Exception;
use Throwable;

class QueryException extends Exception {

    protected string $query;

    public function __construct(string $message, int $code, string $query) {
        parent::__construct($message, $code);
        $this->query = $query;
    }

    public function getQuery(): string {
        return $this->query;
    }

}
