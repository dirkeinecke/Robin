<?php
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
        }
        break;
      case 'configuration':
        $page = 'configuration';
        break;
      case 'configuration':
        $page = 'configuration';
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

  $redis= new Redis();
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
  }
?>

<!doctype html>
<html lang="<?php echo $redis_configuration['language']; ?>">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <title>Robin</title>
  </head>
  <body>
    <header class="bg-dark text-white p-3">
      <h1 class="mb-0">Robin</h1>
      <p class="lead mb-0">Redis database administration</p>
    </header>
    <div class="container-fluid mt-3 mb-3">
      <?php if ($redis_connected === false): ?>
        <?php
          if (isset($redis_connect_exception_message) === true) {
            echo '<div class="alert alert-danger" role="alert">';
            echo 'Error: ', htmlentities($redis_connect_exception_message, ENT_QUOTES);
            echo '</div>';
          }
        ?>
      <?php else: ?>
        <div class="row">
          <div class="col-1">
            <ul class="nav flex-column">
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0" href="./?page=start">Start</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0" href="./?page=databases">Databases</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0" href="./?page=configuration">Configuration</a>
              </li>
              <li class="nav-item">
                <a class="nav-link pl-0 pr-0 pb-0" href="./?page=logfile">Log file</a>
              </li>
            </ul>
          </div>
          <div class="col-11">
            <?php
              $out = (string) '';

              if ($page === 'start') {
                $out .= '<h2>Start</h2>';
              }

              if ($page === 'databases') {
                $out .= '<h2>Databases</h2>';
                $out .= '<table class="table table-sm table-hover">';
                $out .= '<thead>';
                $out .= '<tr>';
                $out .= '<th class="border-top-0" scope="col">Database</th>';
                $out .= '<th class="border-top-0" scope="col">Action</th>';
                $out .= '</tr>';
                $out .= '</thead>';
                $out .= '<tbody>';
                if (isset($redis_configuration['databases']) === true) {
                  for ((int) $i = 1; $i <= $redis_configuration['databases']; $i++) {
                    $out .= '<tr>';
                    $out .= '<td class="nowrap">'.$i.'</td>';
                    $out .= '<td class="nowrap"><a href="./?page=database&amp;database='.$i.'">Show</a></td>';
                    $out .= '</tr>';
                  }
                }
                $out .= '</tbody>';
                $out .= '</table>';
              }

              if ($page === 'database') {
                $out .= '<h2>Database '.$database.'</h2>';
                if (in_array(range(1, intval($redis_configuration['databases'])), $database) === false) {
                  $out .= '<div class="alert alert-info" role="alert">';
                  $out .= 'The database you specified does not exist.';
                  $out .= '<br>';
                  $out .= '<a href="./?page=databases" class="alert-link"><i class="fas fa-angle-left"></i> Back to the database overview</a>';
                  $out .= '</div>';
                } else {
                  $out .= '<p>...</p>';
                }
              }

              if ($page === 'configuration') {
                $out .= '<h2>Configuration</h2>';
                $out .= '<table class="table table-sm table-hover">';
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

              if ($page === 'logfile') {
                $out .= '<h2>Log file</h2>';
                
                $error = (string) '';
                if (isset($redis_configuration['logfile']) === true && $redis_configuration['logfile'] !== '') {
                  if (file_exists($redis_configuration['logfile']) === true) {
                    if (is_readable($redis_configuration['logfile']) === true) {
                      $out .= '<pre class="border bg-light p-3"><code>';
                      $out .= htmlentities(file_get_contents($redis_configuration['logfile']), ENT_QUOTES);
                      $out .= '</code></pre>';
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
          </div>
        </div>
      <?php endif; ?>
    </div>
    <script src="js/jquery-3.4.1.slim.min.js"></script>
    <script src="js/fontawesome.js"></script>
  </body>
</html>