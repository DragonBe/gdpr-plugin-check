<?php

namespace In2it;

$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

$pdo = new \PDO('sqlite:plugins.db');

if (false === ($query = $pdo->query('SELECT pp.platform_id, pp.plugin_id, pa.platform, p.name, pd.website, pd.compliant FROM platform_plugin pp JOIN platform pa ON pp.platform_id = pa.id JOIN plugin p ON pp.plugin_id = p.id JOIN plugin_details pd ON pp.plugin_id = pd.plugin_id'))) {
    echo implode(',' , $pdo->errorInfo()) . PHP_EOL;
    die;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; Charset=utf-8"?>
        <title>Plugin Admin</title>
    </head>
    <body>
        <table>
            <tr>
                <th>Platform</th>
                <th>Plugin</th>
                <th>Website</th>
                <th>Compliant</th>
            </tr>
            <?php while ($entry = $query->fetch(\PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td><?php echo $entry['platform'] ?></td>
                    <td><?php echo $entry['name'] ?></td>
                    <td><?php echo $entry['website'] ?></td>
                    <td><?php echo (1 === (int) $entry['compliant']) ? 'Yes' : 'No' ?></td>
                </tr>
            <?php endwhile ?>
        </table>
    </body>
</html>
