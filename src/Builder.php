<?php

namespace Yabx\MySQL;

use DateTime;
use ReflectionClass;
use ReflectionNamedType;

class Builder {

    /**
     * @template T
     * @psalm-param class-string<T> $class
     * @param array $data
     * @return T
     */
    public static function build(string $class, array $data) {
        $rc = new ReflectionClass($class);
        $object = $rc->newInstanceWithoutConstructor();
        print_r($data);
        foreach($data as $key => $value) {
            $key = Utils::snakeToCamel($key);
            if($rc->hasProperty($key)) {
                $rp = $rc->getProperty($key);
                $type = $rp->getType();
                $rp->setValue($object, self::cast($value, $type));
            }
        }
        return $object;
    }


    public static function cast(mixed $value, ReflectionNamedType $type): mixed {
        $name = $type->getName();
        print "=== {$name} \n";
        if($value === null) return null;
        elseif($name === 'int') return (int)$value;
        elseif($name === 'float') return (float)$value;
        elseif($name === 'string') return (string)$value;
        elseif($name === 'bool') return (bool)$value;
        elseif($name === DateTime::class) return new DateTime($value);
        elseif($name === 'array')  {
            return json_decode($value, true) ?? [];
        }
        print "{$name}\n";
    }

}
