<?php
  function set_page() {
    $page = (string) '';

    if (isset($_GET['page']) === true) {
      switch ($_GET['page']) {
        case 'start':
          $page = 'start';
          break;
        case 'databases':
          $page = 'databases';
          break;
        case 'database':
          $page = 'database';
          if (isset($_GET['database']) === false) { // ToDo: Check if number
            header('Location: ./?page=databases');
            exit();
          }
          break;
        case 'configuration':
          $page = 'configuration';
          break;
        case 'info':
          $page = 'info';
          break;
        case 'logfile':
          $page = 'logfile';
          break;
      }
    }

    if ($page === '') {
      header('Location: ./?page=start');
      exit();
    }

    return $page;
  }

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