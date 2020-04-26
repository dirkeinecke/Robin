<?php

  include_once 'php/configuration.php';

  $redis = new Redis();
  $redis_connected = (bool) false;

  try {
    $redis->connect($robin_configuration['host'], $robin_configuration['port']);
  } catch(RedisException $ex) {
    $redis_connect_exception_message = $ex->getMessage();
  }

  if ($redis->isConnected()) {
    $redis_connected = true;
  }

  $redis->select(1);

  // Set the value of a key
  $key = 'product';
  $redis->set($key, 'MAMP PRO 5');

  // Store data in redis list
  $redis->lPush('list', 'MAMP PRO');
  $redis->lPush('list', 'Apache');
  $redis->lPush('list', 'MySQL');
  $redis->lPush('list', 'Redis');

  $redis->select(2);

  // Set the value of a key
  $key = 'product';
  $redis->set($key, 'MAMP PRO 5');

  // Store data in redis list
  $redis->lPush('list', 'MAMP PRO');
  $redis->lPush('list', 'Apache');
  $redis->lPush('list', 'MySQL');
  $redis->lPush('list', 'Redis');


?>