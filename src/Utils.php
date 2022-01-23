<?php

namespace Yabx\MySQL;

class Utils {

    public static function camelToSnake(string $input): string {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    public static function snakeToCamel(string $input): string {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

}
