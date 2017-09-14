<?php

namespace In2it;

$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

$platform = '';
$availablePlatforms = ['magento' => 'Magento', 'prestashop' => 'PrestaShop', 'woocommerce' => 'WooCommerce'];
$plugin = '';
$compliant = '';
$data = false;

if ([] !== $_GET) {
    if (false === ($pdo = new \PDO('sqlite:plugins.db'))) {
        echo implode(', ', $pdo->errorInfo());
        die;
    }
    if (array_key_exists('term', $_GET)) {
        if (false === ($searchStmt = $pdo->prepare('SELECT `name` FROM `plugin` WHERE `name` LIKE ?'))) {
            echo implode(', ', $pdo->errorInfo());
            die;
        }
        $search = $_GET['term'];
        $search = filter_var($search, FILTER_SANITIZE_STRING);
        $searchStmt->execute(['%' . $search . '%']);
    
        $data = $searchStmt->fetchAll(\PDO::FETCH_ASSOC);
        $searchStmt = null;
        header('Content-Type: application/json; Charset=utf-8');
        $result = array_map(function ($element) {
            return $element['name'];
        }, $data);
        echo json_encode($result);
        die;
    }
    if (false === ($checkStmt = $pdo->prepare('SELECT `p`.`id`, `pa`.`platform`, `p`.`name`, `p`.`price`, `p`.`compliant` FROM `plugin` `p` INNER JOIN `platform` `pa` ON `p`.`platform_id` = `pa`.`id` WHERE `p`.`name` LIKE ?'))) {
        echo 'Error creating check statement: ' . implode(', ', $pdo->errorInfo());
        die;
    }
    if (array_key_exists('platform', $_GET)) {
         $platformData = filter_var($_GET['platform'], FILTER_SANITIZE_STRING);
         if (array_key_exists($platformData, $availablePlatforms)) {
             $platform = $platformData;
             unset ($platformData);
         }
    }
    if (array_key_exists('plugin', $_GET)) {
        $pluginData = urldecode($_GET['plugin']);
        $pluginData = filter_var($pluginData, FILTER_SANITIZE_STRING);
        $plugin = $pluginData;
        unset ($pluginData);

        if (false === ($checkStmt->execute([$plugin]))) {
            echo 'Error executing query: ' . implode(', ', $checkStmt->errorInfo());
            die;
        }
        if (false === ($data = $checkStmt->fetch(\PDO::FETCH_ASSOC))) {
            echo 'Error fetching all data' . implode(', ', $checkStmt->errorInfo());
            die;
        }
        $checkStmt = null;
    }
    $pdo = null;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="jquery/jquery-ui.min.css">
    <style type="text/css">
    .footer { margin-top: 350px; border-top: 1px solid black; }
    </style>
  </head>
  <body>
      <div class="jumbotron">
          <div class="container">
              <h1 class="h1">Are your plugins GDPR compliant?</h1>
              <p>We now offer you a quick and easy way to verify that your plugin is GDPR compliant.</p>
          </div>
      </div>

    <div class="container">
        <div class="row">
            <div class="col-sm-3"> </div>
            <div class="col-sm-6">
                <form class="form" method="get" action="<?php echo $_SERVER['PHP_SELF'] ?>">
                    <div class="form-group">
                        <label for="check-platform">Platform</label>
                        <select name="platform" id="check-platform" class="form-control">
                        <?php foreach ($availablePlatforms as $value => $label): ?>
                            <?php if (0 === strcmp($platform, $value)): ?>
                                <option value="<?php echo htmlentities($value, ENT_QUOTES, 'utf-8') ?>" selected="selected"><?php echo htmlentities($label, ENT_QUOTES, 'utf-8') ?></option>
                            <?php else: ?>
                                <option value="<?php echo htmlentities($value, ENT_QUOTES, 'utf-8') ?>"><?php echo htmlentities($label, ENT_QUOTES, 'utf-8') ?></option>
                            <?php endif ?>
                        <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="check-plugin">Plugin</label>
                        <input type="text" name="plugin" id="check-plugin" value="<?php echo htmlentities($plugin, ENT_QUOTES, 'utf-8') ?>">
                    </div>
                    <input type="submit" value="Check plugin" class="btn btn-success"> <a href="<?php echo $_SERVER['PHP_SELF'] ?>" class="btn btn-default">Cancel</a>
                </form>
            </div>
            <div class="col-sm-3"> </div>
        </div>
    </div>

    <?php if (false !== $data): ?>
    <hr>

    <div class="container">
        <div class="row">
            <dl class="list-group">
               <dt class="list-group-item"><strong><?php echo htmlentities($data['platform'], ENT_QUOTES, 'utf-8') ?></strong></dt>
               <dd class="list-group-item"><?php echo htmlentities($data['name'], ENT_QUOTES, 'utf-8') ?></dd>
               <dd class="list-group-item">$<?php echo sprintf('%01.2f', $data['price']) ?></dd>
               <dd class="list-group-item"><strong><?php echo (0 === (int) $data['compliant'] ? 'NOT COMPLIANT' : 'COMPLIANT') ?></strong></dd>
            </dl>
        </div>
    </div>
    <?php endif ?>


    <div class="container footer">
        <div class="row">
            <div class="col-sm-4">
                <p>Copyright <?php echo date('Y') ?> &copy; <a href="https://www.in2it.be" title="In2it, professional PHP services" rel="nofollow" target="_blank">In2it</a>. All rights reserved.</p>
            </div>
            <div class="col-sm-4">&nbsp;</div>
            <div class="col-sm-4">
                <p>GDPR - General Data Protection Regulation</p>
            </div>
        </div>
    </div>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script type="application/javascript" src="jquery/jquery.min.js"></script>
    <script type="application/javascript" src="jquery/jquery-ui.min.js"></script>
    <script type="application/javascript" src="popper/dist/umd/popper.min.js"></script>
    <script type="application/javascript" src="bootstrap/js/bootstrap.min.js"></script>
    <script type="application/javascript">
        $(document).ready(function () {
           $('#check-plugin').autocomplete({
               minLength: 3,
               source: '<?php echo $_SERVER['PHP_SELF'] ?>'
           });
        });
    </script>
  </body>
</html>
