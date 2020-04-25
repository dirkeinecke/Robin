<?php
  function redis_key_type_as_string($type) {
    $type_as_string = (string) '';

    switch ($type) {
      case Redis::REDIS_STRING:
        $type_as_string = 'String';
        break;
      case Redis::REDIS_SET:
        $type_as_string = 'Set';
        break;              
      case Redis::REDIS_LIST:
        $type_as_string = 'List';
        break;              
      case Redis::REDIS_ZSET:
        $type_as_string = 'Sorted Set';
        break;  
      case Redis::REDIS_HASH :
        $type_as_string = 'Hash';
        break;
      case Redis::REDIS_NOT_FOUND :
        $type_as_string = 'this Type Not Found in Redis';
        break;
    }

    return $type_as_string;
  }
?>