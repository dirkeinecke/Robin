<?php
  session_start();

  include_once 'php/configuration.php';
  include_once 'php/functions.php';
  include_once 'php/strings/'.$robin_configuration['language'].'.php';

  $page = (string) set_page();
  
  if ($page === 'database') {
    $database = intval($_GET['database']);
  }

  $redis = new Redis();
  $redis_connected = (bool) false;

  try {
    $redis->connect($robin_configuration['host'], $robin_configuration['port']);
  } catch(RedisException $ex) {
    $redis_connect_exception_message = $ex->getMessage();
  }

  if ($redis->isConnected()) {
    $redis_connected = true;

    /*
    https://github.com/phpredis/phpredis#flushdb
    $result = $redis->flushDb();
    var_dump($result);
    */

    $redis_configuration = $redis->config('GET', '*');
    if (isset($redis_configuration['databases']) === true) {
      $redis_configuration['databases'] = intval($redis_configuration['databases']);
    }
    $redis_info = $redis->info();

    /* ---------- DELETE ALL KEYS FROM ALL DATABASES ---------- */
    if ($page === 'databases' && isset($_GET['action']) === true && $_GET['action'] === 'empty') {
      $redis->flushAll();
      header('Location: ./?page=databases');
      exit();
    }
    /* ---------- /DELETE ALL KEYS FROM ALL DATABASES ---------- */

    /* ---------- DELETE ALL KEYS FROM THE CURRENT DATABASE ---------- */
    if ($page === 'database' && isset($_GET['action']) === true && $_GET['action'] === 'empty' && redis_database_exists($redis_configuration['databases'], $database) === true) {
      $redis->select($database);
      $redis->flushDb();
      header('Location: ./?page=database&database='.$database);
      exit();
    }
    /* ---------- /DELETE ALL KEYS FROM THE CURRENT DATABASE ---------- */

    /* ---------- RENAME KEY ---------- */ // ToDo: Check parameters better/ more completely.
    if ($page === 'database' && isset($_GET['database']) === true && isset($_GET['key']) === true && isset($_GET['target-key']) === true && isset($redis_configuration['databases']) === true) {
      $_GET['database'] = intval($_GET['database']);

      if (redis_database_exists($redis_configuration['databases'], $_GET['database']) === true) {
        $redis->select($_GET['database']);
        $result = (bool) $redis->renameNx($_GET['key'], $_GET['target-key']);

        if ($result === false) {
          $_SESSION['message'] = (array) [
            'type' => (string) 'Error',
            'text' => (string) '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> could not be renamed.</p>',
          ];

          if ($redis->exists($_GET['key']) === 0) {
            $_SESSION['message']['text'] .= '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> does not exist.</p>';
          }
          
          if ($redis->exists($_GET['target-key']) === 0) {
            $_SESSION['message']['text'] .= '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['target-key'].'</span> (new name) already exist.</p>';
          }
        }
        header('Location: ./?page=database&database='.$_GET['database']);
        exit();
      }
    }
    /* ---------- /RENAME KEY ---------- */

    /* ---------- MOVE KEY TO DATABASE ---------- */ // ToDo: Check parameters better/ more completely.
    if ($page === 'database' && isset($_GET['database']) === true && isset($_GET['target-database']) === true && isset($_GET['key']) === true && isset($redis_configuration['databases']) === true) {
      $_GET['database'] = intval($_GET['database']);
      $_GET['target-database'] = intval($_GET['target-database']);

      if (redis_database_exists($redis_configuration['databases'], $_GET['database']) === true) {
        if (redis_database_exists($redis_configuration['databases'], $_GET['target-database']) === true) {
          $redis->select($_GET['database']);
          $result = (bool) $redis->move($_GET['key'], $_GET['target-database']);
          if ($result === false) {
            $_SESSION['message'] = (array) [
              'type' => (string) 'Error',
              'text' => (string) '<p class="mb-0">Moving the key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> to database <span class="text-monospace font-weight-bold">'.$_GET['target-database'].'</span> failed.</p>',
            ];
            
            if ($redis->exists($_GET['key']) === 0) {
              $_SESSION['message']['text'] .= '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> does not exist in database <span class="text-monospace font-weight-bold">'.$_GET['database'].'</span>.</p>';
            }

            $redis->select($_GET['target-database']);
            if ($redis->exists($_GET['key']) !== 0) {
              $_SESSION['message']['text'] .= '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> already exists in database <span class="text-monospace font-weight-bold">'.$_GET['target-database'].'</span>.</p>';
            }
          }
          header('Location: ./?page=database&database='.$_GET['database']);
          exit();
        }
      }
    }
    /* ---------- /MOVE KEY TO DATABASE ---------- */

    /* ---------- DELETE KEY FROM DATABASE ---------- */ // ToDo: Check parameters better/ more completely.
    if ($page === 'database' && isset($_GET['database']) === true && isset($_GET['key']) === true && isset($_GET['action']) === true && $_GET['action'] === 'delete' && isset($redis_configuration['databases']) === true) {
      $_GET['database'] = intval($_GET['database']);

      if (redis_database_exists($redis_configuration['databases'], $_GET['database']) === true) {
        $redis->select($_GET['database']);
        $result = (int) $redis->del($_GET['key']);

        if ($result !== 1) {
          $_SESSION['message'] = (array) [
            'type' => (string) 'Error',
            'text' => (string) '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> could not be deleted.</p>',
          ];
          
          if ($redis->exists($_GET['key']) === 0) {
            $_SESSION['message']['text'] .= '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> does not exist in database <span class="text-monospace font-weight-bold">'.$_GET['database'].'</span>.</p>';
          }
        }
        header('Location: ./?page=database&database='.$_GET['database']);
        exit();
      }
    }
    /* ---------- /DELETE KEY FROM DATABASE ---------- */

    /* ---------- EMPTY THE REDIS LOG FILE ---------- */
    if ($page === 'logfile' && isset($_GET['action']) === true && $_GET['action'] === 'empty') {
      if (isset($redis_configuration['logfile']) === true && $redis_configuration['logfile'] !== '' && file_exists($redis_configuration['logfile']) === true && is_readable($redis_configuration['logfile']) === true && is_writeable($redis_configuration['logfile']) === true) {
        fclose(fopen($redis_configuration['logfile'], 'w'));
        header('Location: ./?page=logfile');
        exit();
      }
    }
    /* ---------- /EMPTY THE REDIS LOG FILE ---------- */
  }
?>

<!doctype html>
<html lang="<?php echo $robin_configuration['language']; ?>" class="h-100">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <script src="js/jquery-3.4.1.slim.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/fontawesome.js"></script>
    <script src="js/functions.js"></script>
    <title>ROBIN - Redis database administration</title>
  </head>
  <body class="h-100">
    <div class="container-fluid m-0 p-0 h-100" style="">
      <div class="row h-100 m-0 p-0">
        <div class="col-2 pt-3 pl-4 pr-4 bg-dark">
          <div class="position-fixed">
            <h1 class="mt-2 mb-0 text-uppercase text-white">Robin</h1>
            <p class="font-weight-light text-white">Redis database administration</p>
            <hr>
            <ul class="nav flex-column">
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=start"><?php echo ($page === 'start' ? '<i class="fas fa-angle-right"></i> ' : ''); ?>Start</a>
              </li>
              <li class="nav-item">
                 <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=databases"><?php echo (($page === 'databases' || $page === 'database') ? '<i class="fas fa-angle-right"></i> ' : ''); ?>Databases</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=configuration"><?php echo ($page === 'configuration' ? '<i class="fas fa-angle-right"></i> ' : ''); ?>Configuration</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=info"><?php echo ($page === 'info' ? '<i class="fas fa-angle-right"></i> ' : ''); ?>Information and statistics</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=logfile"><?php echo ($page === 'logfile' ? '<i class="fas fa-angle-right"></i> ' : ''); ?>Log file</a>
              </li>
            </ul>
          </div>
        </div>
        <div class="col-10 pt-4 pl-4 pr-4">
          <?php if ($redis_connected === false): ?>
            <?php
              if (isset($redis_connect_exception_message) === true) {
                echo '<div class="alert alert-danger" role="alert">';
                echo 'Error: ', htmlentities($redis_connect_exception_message, ENT_QUOTES);
                echo '</div>';
              }
            ?>
          <?php else: ?>
            <?php
              $out = (string) '';

              if ($page === 'start') {
                $out .= '<h2>Start</h2>';
              }

              if ($page === 'databases') {
                $out .= '<h2>Databases</h2>';
                $out .= '<div class="text-right mb-2">';
                $out .= '<a href="#" class="btn btn-link btn-sm pt-0 pb-0" role="button" data-messagetype="Warning" data-messagetext="Do you want to delete all keys from all databases?<br>This cannot be undone." data-url="./?page=databases&amp;action=empty" onclick="openMessageModalQuestion(this)">Empty databases</a>';
                $out .= '</div>';
                $out .= '<table class="table table-bordered table-sm table-hover">';
                $out .= '<thead>';
                $out .= '<tr>';
                $out .= '<th class="border-top-0" scope="col">Database</th>';
                $out .= '<th class="border-top-0" scope="col">Number of keys</th>';
                $out .= '<th class="border-top-0" scope="col">Action</th>';
                $out .= '</tr>';
                $out .= '</thead>';
                $out .= '<tbody>';
                if (isset($redis_configuration['databases']) === true) {
                  for ($i = (int) 0; $i <= intval($redis_configuration['databases'])-1; $i++) {
                    $redis->select($i);
                    $out .= '<tr>';
                    $out .= '<td class="nowrap">'.$i.'</td>';
                    $out .= '<td class="nowrap">'.$redis->dbSize().'</td>';
                    $out .= '<td class="nowrap"><a href="./?page=database&amp;database='.$i.'">Browse</a></td>';
                    $out .= '</tr>';
                  }
                }
                $out .= '</tbody>';
                $out .= '</table>';
              }

              if ($page === 'database') {
                $out .= '<h2>Database: '.$database.'</h2>';
                if (redis_database_exists($redis_configuration['databases'], $database) === false) {
                  $out .= '<div class="alert alert-info" role="alert">';
                  $out .= 'The database you specified does not exist.';
                  $out .= '<br>';
                  $out .= '<a href="./?page=databases" class="alert-link"><i class="fas fa-angle-left"></i> Back to the database overview</a>';
                  $out .= '</div>';
                } else {
                  $redis->select($database);
                  $keys = $redis->keys('*');

                  $out .= '<h3>Keys</h3>';
                  if (count($keys) !== 0) {
                    $out .= '<div class="text-right mb-2">';
                    $out .= '<a href="#" class="btn btn-link btn-sm pt-0 pb-0" role="button" data-messagetype="Warning" data-messagetext="Do you want to delete all keys from the current database?<br>This cannot be undone." data-url="./?page=database&amp;database='.$database.'&amp;action=empty" onclick="openMessageModalQuestion(this)">Empty database</a>';
                    $out .= '</div>';

                    $out .= '<table class="table table-bordered table-sm table-hover">';
                    $out .= '<thead>';
                    $out .= '<tr>';
                    $out .= '<th class="border-top-0" scope="col">Index</th>';
                    $out .= '<th class="border-top-0" scope="col">Key</th>';
                    $out .= '<th class="border-top-0" scope="col">Type</th>';
                    $out .= '<th class="border-top-0" scope="col">Time to live <small>(ttl / pttl)</small></th>';
                    $out .= '<th class="border-top-0" scope="col" colspan="4">Actions</th>';
                    $out .= '</tr>';
                    $out .= '</thead>';
                    $out .= '<tbody>';
                    for ($ki = (int) 0; $ki < count($keys); $ki++) {
                      $key = (string) $keys[$ki];
                      $ttl = $redis->ttl($key);
                      $pttl = $redis->pttl($key);

                      $out .= '<tr>';
                      $out .= '<td class="nowrap text-monospace">'.$ki.'</td>';
                      $out .= '<td class="nowrap text-monospace">'.htmlentities($keys[$ki], ENT_QUOTES).'</td>';
                      $out .= '<td class="nowrap text-monospace">'.htmlentities(redis_key_type_as_string($redis->type($key)), ENT_QUOTES).'</td>';
                      $out .= '<td class="nowrap text-monospace">'.(($ttl === -1 || $ttl === -2) ? '' : $ttl.' / '.$pttl).'</td>';
                      $out .= '<td class="nowrap">';
                      $out .= '<div class="dropdown">';
                      $out .= '<a href="#" class="btn btn-link btn-sm pt-0 pb-0 dropdown-toggle" role="button" id="dropdown-e-'.$ki.'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Edit</a>';
                      $out .= '<div class="dropdown-menu" aria-labelledby="dropdown-e-'.$ki.'">';
                      $out .= '<a class="dropdown-item" href="#">Value</a>';
                      $out .= '</div>';
                      $out .= '</div>';
                      $out .= '</td>';
                      $out .= '<td class="nowrap">';
                      $out .= '<div class="dropdown">';
                      $out .= '<a href="#" class="btn btn-link btn-sm pt-0 pb-0 dropdown-toggle" role="button" id="dropdown-rt-'.$ki.'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Rename to:</a>';
                      $out .= '<div class="dropdown-menu" aria-labelledby="dropdown-rt-'.$ki.'">';
                      $out .= '<form method="get" action="./" class="px-3 py-1">';
                      $out .= '<input type="hidden" name="page" value="database">';
                      $out .= '<input type="hidden" name="database" value="'.$database.'">';
                      $out .= '<input type="hidden" name="key" value="'.$keys[$ki].'">';
                      $out .= '<div class="form-group">';
                      $out .= '<label for="input-rt-'.$ki.'">New name</label>';
                      $out .= '<input type="text" class="form-control form-control-sm" name="target-key" id="input-rt-'.$ki.'" placeholder="New name" required>';
                      $out .= '</div>';
                      $out .= '<button type="submit" class="btn btn-secondary btn-sm">Rename</button>';
                      $out .= '</form>';
                      $out .= '</div>';
                      $out .= '</div>';
                      $out .= '</td>';
                      $out .= '<td class="nowrap">';
                      $out .= '<div class="dropdown">';
                      $out .= '<a href="#" class="btn btn-link btn-sm pt-0 pb-0 dropdown-toggle" role="button" id="dropdown-mtd-'.$ki.'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Move to database:</a>';
                      $out .= '<div class="dropdown-menu" aria-labelledby="dropdown-mtd-'.$ki.'">';
                      if (isset($redis_configuration['databases']) === true) {
                        for ($di = (int) 0; $di <= intval($redis_configuration['databases'])-1; $di++) {
                          if ($di !== $database) {
                            $out .= '<a class="dropdown-item" href="./?page=database&database='.$database.'&amp;target-database='.$di.'&amp;key='.urlencode($keys[$ki]).'">'.$di.'</a>';
                          }
                        }
                      }
                      $out .= '</div>';
                      $out .= '</div>';
                      $out .= '</td>';
                      $out .= '<td class="nowrap">';
                      $out .= '<a href="#" class="btn btn-link btn-sm pt-0 pb-0" role="button" data-messagetype="Warning" data-messagetext="Do you really want to delete the key <span class=\'text-monospace\'>'.htmlentities($keys[$ki], ENT_QUOTES).'</span>?<br>This cannot be undone." data-url="./?page=database&database='.$database.'&amp;key='.urlencode($keys[$ki]).'&amp;action=delete" onclick="openMessageModalQuestion(this)">Delete</a>';
                      $out .= '</td>';
                      $out .= '</tr>';
                    }
                    $out .= '</tbody>';
                    $out .= '</table>';
                  } else {
                    $out .= '<div class="alert alert-info" role="alert">';
                    $out .= 'This database is empty.';
                    $out .= '<br>';
                    $out .= '<a href="./?page=database&amp;database='.$database.'&amp;action=add-key" class="alert-link"><i class="fas fa-plus"></i> Add new key</a>';
                    $out .= '</div>';
                  }
                }
              }

              if ($page === 'configuration') {
                $out .= '<h2>Configuration</h2>';
                $out .= '<table class="table table-bordered table-sm table-hover">';
                $out .= '<thead>';
                $out .= '<tr>';
                $out .= '<th class="border-top-0" scope="col">Key</th>';
                $out .= '<th class="border-top-0" scope="col">Value</th>';
                $out .= '</tr>';
                $out .= '</thead>';
                $out .= '<tbody>';
                foreach ($redis_configuration as $key => $value) {
                  $out .= '<tr>';
                  $out .= '<td class="nowrap">'.htmlentities($key, ENT_QUOTES).'</td>';
                  $out .= '<td class="nowrap text-monospace">'.htmlentities($value, ENT_QUOTES).'</td>';
                  $out .= '</tr>';
                }
                $out .= '</tbody>';
                $out .= '</table>';
              }

              if ($page === 'info') {
                $out .= '<h2>Information and statistics</h2>';
                $out .= '<table class="table table-bordered table-sm table-hover">';
                $out .= '<thead>';
                $out .= '<tr>';
                $out .= '<th class="border-top-0" scope="col">Key</th>';
                $out .= '<th class="border-top-0" scope="col">Value</th>';
                $out .= '</tr>';
                $out .= '</thead>';
                $out .= '<tbody>';
                foreach ($redis_info as $key => $value) {
                  $out .= '<tr>';
                  $out .= '<td class="nowrap">'.htmlentities($key, ENT_QUOTES).'</td>';
                  $out .= '<td class="nowrap text-monospace">'.htmlentities($value, ENT_QUOTES).'</td>';
                  $out .= '</tr>';
                }
                $out .= '</tbody>';
                $out .= '</table>';
              }

              if ($page === 'logfile') {
                $out .= '<h2>Log file</h2>';
                $error = (string) '';
                if (isset($redis_configuration['logfile']) === true && $redis_configuration['logfile'] !== '') {
                  if (file_exists($redis_configuration['logfile']) === true) {
                    if (is_readable($redis_configuration['logfile']) === true) {
                      $content = file_get_contents($redis_configuration['logfile']);
                      if ($content === '') {
                        $out .= '<div class="alert alert-info" role="alert">';
                        $out .= 'The log file is empty.';
                        $out .= '</div>';
                      } else {
                        if (is_writeable($redis_configuration['logfile']) === true) {
                          $out .= '<div class="text-right mb-2">';
                          $out .= '<a href="./?page=logfile&amp;action=empty" class="btn btn-secondary btn-sm pt-0 pb-0" role="button">Empty</a>';
                          $out .= '</div>';
                        }
                        $out .= '<pre class="border bg-light p-2"><code>';
                        $out .= htmlentities($content, ENT_QUOTES);
                        $out .= '</code></pre>';
                      }
                    } else {
                      $error = 'The log file cannot be read.';
                    }
                  } else {
                    $error = 'There is no log file available.';
                  }
                } else {
                  $error = 'No log file was configured.';
                }

                if ($error !== '') {
                  $out .= '<div class="alert alert-info" role="alert">';
                  $out .= htmlentities($error, ENT_QUOTES);
                  $out .= '</div>';
                }
              }

              echo $out;
            ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="modal" id="messageModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><?php echo (isset($_SESSION['message']['type']) === true ? htmlentities($_SESSION['message']['type'], ENT_QUOTES) : ''); ?></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <?php echo (isset($_SESSION['message']['text']) === true ? $_SESSION['message']['text'] : ''); ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary action-no d-none" data-dismiss="modal">No</button>
            <a href="#" class="btn btn-secondary action-yes d-none" role="button">Yes</a>
            <button type="button" class="btn btn-secondary action-close" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <?php if (isset($_SESSION['message']) === true): ?>
      <script>
        if (document.readyState === 'complete' || (document.readyState !== 'loading' && !document.documentElement.doScroll)) {
          openMessageModal();
        } else {
          document.addEventListener('DOMContentLoaded', openMessageModal);
        }
      </script>
      <?php unset($_SESSION['message']); ?>); ?>
    <?php endif; ?>

  </body>
</html>