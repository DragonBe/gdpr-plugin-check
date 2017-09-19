<?php

namespace In2it;

require_once __DIR__ . '/../vendor/autoload.php';
session_save_path(__DIR__ . '/../sessions');
session_name('GPCSID');
session_start();

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;

$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

$pdo = new \PDO('sqlite:' . realpath(__DIR__ . '/../plugins.db'));

$app = new Application();
$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../templates',
));

$app->extend('twig', function ($twig, $app) {
    $identity = false;
    if ([] !== $_SESSION && array_key_exists('username', $_SESSION)) {
        $identity = true;
    }
    $twig->addGlobal('identity', $identity);
    return $twig;
});

$app['debug'] = true;
$app['pdo'] = $pdo;
$app['server_page'] = $_SERVER['PHP_SELF'];

$app->get('/', function () use ($app) {

    $platformStmt = $app['pdo']->query('SELECT * FROM platform ORDER BY platform');
    $platformData = $platformStmt->fetchAll(\PDO::FETCH_ASSOC);
    return $app['twig']->render('home.twig', ['platforms' => $platformData]);
});

$app->get('/info', function () use ($app) {

    return $app['twig']->render('info.twig', []);
});

$app->get('/plugin', function (Request $request) use ($app) {
    
    $pluginStmt = $app['pdo']->prepare('SELECT id, name FROM plugin WHERE name like ? ORDER BY name');
    $pluginStmt->execute(['%' . $request->get('term') . '%']);
    $pluginData = $pluginStmt->fetchAll(\PDO::FETCH_ASSOC);
    $fieldData = [];
    foreach ($pluginData as $pluginEntry) {
        $fieldData[$pluginEntry['id']] = $pluginEntry['name'];
    }
    return json_encode($fieldData);
});

$app->post('/plugin/check', function (Request $request) use ($app) {
    $plugin = $request->get('plugin');

    $stmt = $app['pdo']->prepare('SELECT p.name, pd.website, pp.price, pd.compliant, pd.last_checked FROM platform_plugin pp JOIN plugin p ON pp.plugin_id = p.id JOIN plugin_details pd on pp.plugin_id = pd.plugin_id WHERE p.name = ?');
    $stmt->execute([$plugin]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (false === $result) {
        return $app['twig']->render('plugin/notfound.twig', ['plugin' => $plugin]);
    }
    return $app['twig']->render('plugin/check.twig', ['plugin' => $result]);
});

$app->get('/admin', function (Request $request) use ($app) {
    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin'));
    }
    $lastId = (int) $request->get('last_id', 0);
    $stmt = $app['pdo']->query('SELECT pp.platform_id, pp.plugin_id, pa.platform, p.name, pd.website, pp.price, pd.compliant, pa.search, pd.last_checked FROM platform_plugin pp JOIN platform pa ON pp.platform_id = pa.id JOIN plugin p ON pp.plugin_id = p.id JOIN plugin_details pd ON pp.plugin_id = pd.plugin_id');
    $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    return $app['twig']->render('admin/plugins.twig', [
        'plugins' => $data,
        'last_id' => $lastId,
    ]);
});

$app->get('/admin/add', function (Request $request) use ($app) {
    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin/add'));
    }
    $platformStmt = $app['pdo']->query('SELECT id, platform FROM platform ORDER BY platform');
    $platformList = $platformStmt->fetchAll(\PDO::FETCH_ASSOC);

    $lastPlatform = (int) $request->get('last_platform', 0);

    $data = [
        'platform_id' => $lastPlatform,
        'plugin_id' => 0,
        'platform' => '',
        'name' => '',
        'website' => '',
        'price' => 0.0,
        'compliant' => 0,
        'last_checked' => '',
        'search' => '',
    ];
    return $app['twig']->render('admin/edit.twig', [
        'action' => 'add',
        'platforms' => $platformList,
        'compliance' => [0 => 'No', 1 => 'Yes'],
        'data' => $data,
    ]);
});

$app->post('/admin/add', function (Request $request) use ($app) {
    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin/add'));
    }
    $platformId = $request->get('platform');
    $plugin = $request->get('plugin');
    $website = $request->get('website');
    $price = (float) $request->get('price', 0);
    $compliant = (int) $request->get('compliant', 0);
    $date = new \DateTime('now', new \DateTimeZone('Europe/Brussels'));
    $timeStamp = $date->format('Y-m-d H:i:s');

    $pluginStmt = $app['pdo']->prepare('INSERT INTO plugin (name) VALUES (?)');
    $pluginStmt->execute([$plugin]);
    $pluginId = (int) $app['pdo']->lastInsertId();

    $plugDetailStmt = $app['pdo']->prepare('INSERT INTO plugin_details (plugin_id, website, compliant, last_checked) VALUES (?,?,?,?)');
    $plugDetailStmt->execute([$pluginId, $website, $compliant, $timeStamp]);

    $platPlugStmt = $app['pdo']->prepare('INSERT INTO platform_plugin (platform_id, plugin_id, price) VALUES (?,?,?)');
    $platPlugStmt->execute([$platformId, $pluginId, $price]);

    return $app->redirect('/admin?last_id=' . $pluginId . '#last');
});

$app->get('/admin/edit/{platformId}/{pluginId}', function ($platformId, $pluginId) use ($app) {

    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin/edit/' . $platformId . '/' . $pluginId));
    }

    $platformStmt = $app['pdo']->query('SELECT id, platform FROM platform ORDER BY platform');
    $platformList = $platformStmt->fetchAll(\PDO::FETCH_ASSOC);

    $dataStmt = $app['pdo']->prepare('SELECT pp.platform_id, pp.plugin_id, pa.platform, p.name, pd.website, pp.price, pd.compliant, pa.search FROM platform_plugin pp JOIN platform pa ON pp.platform_id = pa.id JOIN plugin p ON pp.plugin_id = p.id JOIN plugin_details pd ON pp.plugin_id = pd.plugin_id WHERE (pp.platform_id = ?) AND (pp.plugin_id = ?)');
    $dataStmt->execute([$platformId, $pluginId]);
    $data = $dataStmt->fetch(\PDO::FETCH_ASSOC);
    return $app['twig']->render('admin/edit.twig', [
        'action' => 'edit',
        'platforms' => $platformList,
        'compliance' => [0 => 'No', 1 => 'Yes'],
        'data' => $data,
    ]);
});

$app->get('/admin/delete/{platformId}/{pluginId}', function ($platformId, $pluginId) use ($app) {
    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin'));
    }
    $plugStmt = $app['pdo']->prepare('DELETE FROM platform_plugin WHERE platform_id = ? AND plugin_id = ?');
    $plugStmt->execute([$platformId, $pluginId]);
    return $app->redirect('/admin?last_id=' . ($pluginId - 1) . '#last');
});

$app->post('/admin/edit', function (Request $request) use ($app) {
    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin'));
    }
    $platformId = $request->get('platform');
    $pluginId = $request->get('plugin');
    $website = $request->get('website');
    $price = $request->get('price');
    $compliant = $request->get('compliant');
    $date = new \DateTime('now', new \DateTimeZone('Europe/Brussels'));
    $timeStamp = $date->format('Y-m-d H:i:s');

    $plugDetailStmt = $app['pdo']->prepare('UPDATE plugin_details SET website = ?, compliant = ?, last_checked = ? WHERE plugin_id = ?');
    $plugDetailStmt->execute([$website, $compliant, $timeStamp, $pluginId]);

    $plugPriceStmt = $app['pdo']->prepare('UPDATE platform_plugin SET price = ? WHERE (platform_id = ?) AND (plugin_id = ?)');
    $plugPriceStmt->execute([$price, $platformId, $pluginId]);
    return $app->redirect('/admin?last_id=' . $pluginId . '#last');
});

$app->get('/login', function (Request $request) use ($app) {
    $csrf = sha1('Just one more thing...' . microtime() . md5(date('U')));
    $ref = $request->get('ref', '/');
    $_SESSION['csrf'] = $csrf;
    $error = '';
    if (array_key_exists('error', $_SESSION)) {
        $error = $_SESSION['error'];
    }
    return $app['twig']->render('admin/login.twig', [
        'csrf' => $csrf,
        'error' => $error,
        'ref' => urldecode($ref),
    ]);
});

$app->post('/login', function (Request $request) use ($app) {
    $csrf = $request->get('anti-spam', '');
    if (0 !== strcmp($csrf, $_SESSION['csrf'])) {
        $_SESSION['error'] = 'Something went wrong, please try again';
        return $app->redirect('/login');
    }
    $username = $request->get('username', '');
    if ('' === $username) {
        $_SESSION['error'] = 'Please use your registered user credentials';
        return $app->redirect('/login');
    }
    $password = $request->get('password', '');
    if ('' === $password) {
        $_SESSION['error'] = 'We cannot use an empty password';
        return $app->redirect('/login');
    }

    $stmt = $app['pdo']->query('SELECT username, password FROM manager WHERE username = ?');
    $stmt->execute([$username]);
    $data = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (false === $data) {
        $_SESSION['error'] = 'Wrong credentials provided, please try again.';
        return $app->redirect('/login');
    }
    if (!password_verify($password, $data['password'])) {
        $_SESSION['error'] = 'Wrong credentials provided, please try again.';
        return $app->redirect('/login');
    }
    $_SESSION['username'] = $username;
    $ref = $request->get('ref', '/');
    return $app->redirect($ref);
});

$app->get('/logout', function () use ($app) {
    unset ($_SESSION['username']);
    session_destroy();
    return $app->redirect('/');
});

$app->run();
