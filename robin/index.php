<?php
  session_start();

  include_once 'php/configuration.php';
  include_once 'php/functions.php';
  include_once 'php/strings/'.$robin_configuration['language'].'.php';

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
        if (isset($_GET['database']) === true) { // ToDo: Check if number
          $database = (int) $_GET['database'];
        } else {
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

  $redis = new Redis();
  $redis_connected = (bool) false;

  try {
    $redis->connect($robin_configuration['host'], $robin_configuration['port']);
  } catch(RedisException $ex) {
    $redis_connect_exception_message = $ex->getMessage();
  }

  if ($redis->isConnected()) {
    $redis_connected = true;

    // Get all Redis server configuration parameters.
    // See https://github.com/phpredis/phpredis#config
    $redis_configuration = $redis->config('GET', '*');
    if (isset($redis_configuration['databases']) === true) {
      $redis_configuration['databases'] = intval($redis_configuration['databases']);
    }
    $redis_info = $redis->info();

    /* ---------- MOVE KEY TO DATABASE ---------- */
    if ($page === 'database' && isset($_GET['database']) === true && isset($_GET['target-database']) === true && isset($_GET['key']) === true && isset($redis_configuration['databases']) === true) {
      $_GET['database'] = intval($_GET['database']);
      $_GET['target-database'] = intval($_GET['target-database']);

      if (redis_database_exists($redis_configuration['databases'], $_GET['database']) === true) {
        if (redis_database_exists($redis_configuration['databases'], $_GET['target-database']) === true) {
          $redis->select($_GET['database']);
          $result = (bool) $redis->move($_GET['key'], $_GET['target-database']);
          if ($result === false) {
            $redis->select($_GET['target-database']);
            $keys = $redis->keys($_GET['key']);

            $_SESSION['message'] = (array) [
              'type' => (string) 'Error',
              'text' => (string) '<p class="mb-0">Moving the key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> to database <span class="text-monospace font-weight-bold">'.$_GET['target-database'].'</span> failed.</p>',
            ];
            if (count($keys) !== 0) {
              $_SESSION['message']['text'] .= '<p class="mb-0">The key <span class="text-monospace font-weight-bold">'.$_GET['key'].'</span> already exists in database <span class="text-monospace font-weight-bold">'.$_GET['target-database'].'</span>.</p>';
            }
            unset($keys);
          }
          header('Location: ./?page=database&database='.$_GET['database']);
          exit();
        }
      }
    }
    /* ---------- /MOVE KEY TO DATABASE ---------- */

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
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=start">Start</a>
              </li>
              <li class="nav-item">
                 <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=databases">Databases</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=configuration">Configuration</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=info">Information and statistics</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0 text-white" href="./?page=logfile">Log file</a>
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
                  for ((int) $i = 0; $i <= intval($redis_configuration['databases'])-1; $i++) {
                    $redis->select($i);
                    $out .= '<tr>';
                    $out .= '<td class="nowrap">'.$i.'</td>';
                    $out .= '<td class="nowrap">'.$redis->dbSize().'</td>';
                    $out .= '<td class="nowrap"><a href="./?page=database&amp;database='.$i.'">Show</a></td>';
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
                      $key = $keys[$ki];
                      $ttl = $redis->ttl($key);
                      $pttl = $redis->pttl($key);

                      $out .= '<tr>';
                      $out .= '<td class="nowrap text-monospace">'.$ki.'</td>';
                      $out .= '<td class="nowrap text-monospace">'.htmlentities($keys[$ki], ENT_QUOTES).'</td>';
                      $out .= '<td class="nowrap text-monospace">'.htmlentities(redis_key_type_as_string($redis->type($key)), ENT_QUOTES).'</td>';
                      $out .= '<td class="nowrap text-monospace">'.(($ttl === -1 || $ttl === -2) ? '' : $ttl.' / '.$pttl).'</td>';
                      $out .= '<td class="nowrap"><a href="#" class="btn btn-secondary btn-sm pt-0 pb-0" role="button">Edit</a></td>';
                      $out .= '<td class="nowrap"><a href="#" class="btn btn-secondary btn-sm pt-0 pb-0" role="button">Rename to:</a></td>';
                      $out .= '<td class="nowrap">';
                      $out .= '<div class="dropdown">';
                      $out .= '<a href="#" class="btn btn-warning btn-sm pt-0 pb-0 dropdown-toggle" role="button" id="dropdown-mtd-'.$ki.'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Move to database:</a>';
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
                      $out .= '<td class="nowrap"><a href="#" class="btn btn-danger btn-sm pt-0 pb-0" role="button">Delete</a></td>';
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
                $out .= '<h2>Info</h2>';
                $out .= '<p>Information and statistics about the server</p>';
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

    <?php if (isset($_SESSION['message']) === true): ?>
      <div class="modal" id="messageModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><?php echo htmlentities($_SESSION['message']['type'], ENT_QUOTES); ?></h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <?php echo $_SESSION['message']['text']; ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
      <script>
        const openMessageModal = function(){
          $('#messageModal').modal('show')
        };

        if (document.readyState === 'complete' || (document.readyState !== 'loading' && !document.documentElement.doScroll)) {
          openMessageModal();
        } else {
          document.addEventListener('DOMContentLoaded', openMessageModal);
        }
      </script>
      <?php unset($_SESSION['message']); ?>); ?>
    <?php endif; ?>

    <script src="js/jquery-3.4.1.slim.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/fontawesome.js"></script>
  </body>
</html>