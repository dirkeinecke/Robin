<?php
  function redis_key_type_as_string($type): string {
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
        $type_as_string = 'type not found';
        break;
    }

    return $type_as_string;
  }

  function redis_database_exists(int $databases, int $database): bool {
    return in_array($database, range(0, $databases-1));
  }
?>