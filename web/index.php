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

ini_set('xdebug.default_enable', '0');
ini_set('display_errors', 0);
error_reporting(E_WARNING|E_ERROR);

$pdo = new \PDO('sqlite:' . realpath(__DIR__ . '/../plugins.db'));

$app = new Application();
$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../templates',
));

$app->extend('twig', function ($twig, $app) {
    $identity = false;
    $tracking = true;
    if ([] !== $_SESSION && array_key_exists('username', $_SESSION)) {
        $identity = true;
    }
    if (1 === (int) $_SERVER['HTTP_DNT']) {
        $tracking = false;
    }
    $twig->addGlobal('identity', $identity);
    $twig->addGlobal('tracking', $tracking);
    return $twig;
});

$app['debug'] = true;
$app['pdo'] = $pdo;
$app['server_page'] = $_SERVER['PHP_SELF'];
$app['platformList'] = ['magento', 'prestashop', 'woocommerce'];

$app->get('/', function () use ($app) {

    $news = [];
    if (false === ($newsStmt = $app['pdo']->query('SELECT n.title, n.article, n.published, m.username AS author FROM news n JOIN manager m ON n.manager_id = m.id ORDER BY n.published DESC LIMIT 3'))) {
        return $app['twig']->render('home.twig', [
            'news' => $news,
        ]);
    }

    $news = $newsStmt->fetchAll(\PDO::FETCH_ASSOC);
    return $app['twig']->render('home.twig', [
        'news' => $news,
    ]);
});

$app->get('/info', function () use ($app) {

    return $app['twig']->render('info.twig', []);
});

$app->get('/privacy-declaration', function () use ($app) {
    return $app['twig']->render('privacy.twig');
});

$app->get('/terms-conditions', function () use ($app) {
    return $app['twig']->render('terms.twig');
});

$app->get('/plugin', function (Request $request) use ($app) {

    $term = $request->get('term', '');
    $platform = (int) $request->get('platform', 1);

    $pluginStmt = $app['pdo']->prepare('SELECT pa.platform, p.id, p.name FROM platform_plugin pp JOIN platform pa ON pp.platform_id = pa.id JOIN plugin p ON pp.plugin_id = p.id WHERE p.name like ? ORDER BY pa.platform, p.name');
    $pluginStmt->execute(['%' . $term . '%']);
    $pluginData = $pluginStmt->fetchAll(\PDO::FETCH_ASSOC);
    $fieldData = [];
    foreach ($pluginData as $pluginEntry) {
        $fieldData[$pluginEntry['id']] = $pluginEntry['name'] . ' (' . $pluginEntry['platform'] . ')';
    }
    return json_encode($fieldData);
});

$app->post('/plugin/check', function (Request $request) use ($app) {
    $pluginString = $request->get('plugin');

    $match = preg_match('/^(.*)\s\((\w+)\)$/', $pluginString, $matches);

    if (0 === (int) $match) {
        return $app['twig']->render('plugin/notfound.twig', ['plugin' => $pluginString]);
    }

    $plugin = trim($matches[1]);
    $platform = trim($matches[2]);

    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_BACKTICK);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);

    $plugin = str_replace(['=', '--', ';'], '', $plugin);
    $plugin = trim($plugin);

    if (!in_array(strtolower($platform), $app['platformList'])) {
        return $app['twig']->render('plugin/notfound.twig', ['plugin' => $plugin]);
    }

    $stmt = $app['pdo']->prepare('SELECT p.id, pa.platform, p.name, pd.website, pp.price, pd.compliant, pd.last_checked, pd.privacy_policy FROM platform_plugin pp JOIN platform pa ON pp.platform_id = pa.id JOIN plugin p ON pp.plugin_id = p.id JOIN plugin_details pd on pp.plugin_id = pd.plugin_id WHERE (pa.platform = ?) AND (p.name = ?)');
    $stmt->execute([$platform, $plugin]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (false === $result) {
        return $app['twig']->render('plugin/notfound.twig', ['plugin' => $plugin]);
    }

    $metaStmt = $app['pdo']->prepare('SELECT pm.label, pm.value FROM plugin_meta pm WHERE pm.plugin_id = ?');
    $metaStmt->bindValue(1, $result['id'], \PDO::PARAM_INT);
    $metaStmt->execute();

    $meta = $metaStmt->fetchAll(\PDO::FETCH_ASSOC);
    $labels = [
        'date_reviewed' => 'Last verified',
        'comment' => 'Review notes',
        'policy_hash' => 'Hash of privacy policy',
    ];

    return $app['twig']->render('plugin/check.twig', ['plugin' => $result, 'meta' => $meta, 'labels' => $labels]);
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

    $dataStmt = $app['pdo']->prepare('SELECT pp.platform_id, pp.plugin_id, pa.platform, p.name, pd.website, pp.price, pd.compliant, pd.privacy_policy, pa.search FROM platform_plugin pp JOIN platform pa ON pp.platform_id = pa.id JOIN plugin p ON pp.plugin_id = p.id JOIN plugin_details pd ON pp.plugin_id = pd.plugin_id WHERE (pp.platform_id = ?) AND (pp.plugin_id = ?)');
    $dataStmt->execute([$platformId, $pluginId]);
    $data = $dataStmt->fetch(\PDO::FETCH_ASSOC);

    $metaStmt = $app['pdo']->prepare('SELECT label, value FROM plugin_meta WHERE plugin_id = ?');
    $metaStmt->execute([$pluginId]);
    $meta = $metaStmt->fetchAll(\PDO::FETCH_ASSOC);

    $comment = '';
    if ([] !== $meta) {
        $commentList = array_values(array_filter($meta, function ($combo) {
            return 'comment' === $combo['label'];
        }, ARRAY_FILTER_USE_BOTH));
        $comment = $commentList[0]['value'];
    }

    return $app['twig']->render('admin/edit.twig', [
        'action' => 'edit',
        'platforms' => $platformList,
        'compliance' => [0 => 'No', 1 => 'Yes'],
        'data' => $data,
        'comment' => $comment,
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
    $privacyPolicy = $request->get('privacy_policy');
    $compliant = $request->get('compliant');
    $comment = $request->get('comment');
    $date = new \DateTime('now', new \DateTimeZone('Europe/Brussels'));
    $timeStamp = $date->format('Y-m-d H:i:s');

    $plugDetailStmt = $app['pdo']->prepare('UPDATE plugin_details SET website = ?, privacy_policy = ?, compliant = ?, last_checked = ? WHERE plugin_id = ?');
    $plugDetailStmt->execute([$website, $privacyPolicy, $compliant, $timeStamp, $pluginId]);

    $plugPriceStmt = $app['pdo']->prepare('UPDATE platform_plugin SET price = ? WHERE (platform_id = ?) AND (plugin_id = ?)');
    $plugPriceStmt->execute([$price, $platformId, $pluginId]);

    $hash = md5_file($privacyPolicy);
    $meta = [
        'date_reviewed' => $date->format('Y-m-d'),
        'comment' => $comment,
        'policy_hash' => $hash,
    ];

    $metaSelectStmt = $app['pdo']->prepare('SELECT * FROM plugin_meta WHERE plugin_id = ? AND label LIKE ?');
    $metaInStmt = $app['pdo']->prepare('INSERT INTO plugin_meta (plugin_id, label, value) VALUES (?, ?, ?)');
    $metaUpStmt = $app['pdo']->prepare('UPDATE plugin_meta SET value = ? WHERE plugin_id = ? AND label LIKE ?');

    foreach ($meta as $label => $value) {
        $metaSelectStmt->execute([$pluginId, $label]);
        $row = $metaSelectStmt->fetch(\PDO::FETCH_ASSOC);
        if (false === $row) {
            $metaInStmt->execute([$pluginId, $label, $value]);
        } else {
            $metaUpStmt->execute([$value, $pluginId, $label]);
        }
    }

    return $app->redirect('/admin?last_id=' . $pluginId . '#last');
});

$app->get('/admin/news', function () use ($app) {
    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin/news'));
    }
    $post = [
        'id' => 0,
        'author' => $_SESSION['username'],
        'title' => '',
        'article' => '',
    ];
    return $app['twig']->render('admin/news.twig', ['news' => $post]);
});

$app->post('/admin/news', function (Request $request) use ($app) {
    if ([] === $_SESSION || !array_key_exists('username', $_SESSION)) {
        return $app->redirect('/login?ref=' . urlencode('/admin/news'));
    }

    $data = [
        'id' => (int) $request->get('id', 0),
        'author' => $request->get('author', ''),
        'title' => $request->get('title', ''),
        'article' => $request->get('article', '')
    ];

    $managerLookupStmt = $app['pdo']->prepare('SELECT id, username FROM manager WHERE username = ?');
    $managerLookupStmt->execute([$data['author']]);
    $manager = $managerLookupStmt->fetch(\PDO::FETCH_ASSOC);

    unset ($data['author']);
    $data['manager_id'] = $manager['id'];

    $date = new \DateTime('now', new \DateTimeZone('Europe/Brussels'));

    if (0 === $data['id']) {
        $newData = [
            'manager_id' => $data['manager_id'],
            'title' => $data['title'],
            'article' => $data['article'],
            'created' => $date->format('Y-m-d H:i:s'),
            'published' => $date->format('Y-m-d H:i:s'),
        ];
        $query = 'INSERT INTO news (manager_id, title, article, created, published) VALUES (?, ?, ?, ?, ?)';
    } else {
        $newData = [
            'manager_id' => $data['manager_id'],
            'title' => $data['title'],
            'article' => $data['article'],
            'published' => $date->format('Y-m-d H:i:s'),
            'id' => $data['id'],
        ];
        $query = 'UPDATE news SET manager_id = ?, title = ?, article = ?, published = ? WHERE id = ?';
    }
    $postStmt = $app['pdo']->prepare($query);
    $postStmt->execute(array_values($newData));

    return $app->redirect('/admin/news');
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

$app->get('/api', function () use ($app) {
    return $app['twig']->render('api/home.twig', [
        'baseUrl' => 'https://plugin-check.in2it.be/api',
    ]);
});

$app->get('api/check/{searchTxt}', function ($searchTxt) use ($app) {

    $plugin = trim(urldecode($searchTxt));

    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_BACKTICK);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);

    $plugin = str_replace(['=', '--', ';'], '', $plugin);
    $plugin = trim($plugin);

    $pluginChk = $app['pdo']->prepare(
        'SELECT pa.platform, p.name, pd.website, (CASE WHEN pd.compliant = 1 THEN "true" ELSE "false" END), pd.last_checked, pp.price 
           FROM platform_plugin pp 
           JOIN platform pa ON pp.platform_id = pa.id
           JOIN plugin p ON pp.plugin_id = p.id
           JOIN plugin_details pd ON pp.plugin_id = pd.plugin_id
           WHERE p.name LIKE ?
           ORDER BY p.name
    ');
    $pluginChk->execute(['%' . $plugin . '%']);
    $data = $pluginChk->fetchAll(\PDO::FETCH_ASSOC);
    $totalResults = count($data);
    $success = 'No results found';
    if (0 < $totalResults) {
        $success = 'Successful finding plugins';
    }
    return $app->json(['result' => $success, 'count' => $totalResults, 'plugins' => $data]);
});

$app->get('api/check/{searchTxt}/{searchPlatform}', function ($searchTxt, $searchPlatform) use ($app) {

    $platform = trim(urldecode($searchPlatform));
    if (!in_array(strtolower($platform), $app['platformList'])) {
        return $app->json(['result' => 'Error looking up the chosen platform', 'count' => 0, 'plugins' => []]);
    }

    $plugin = trim(urldecode($searchTxt));

    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_BACKTICK);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW);
    $plugin = filter_var($plugin, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);

    $plugin = str_replace(['=', '--', ';'], '', $plugin);
    $plugin = trim($plugin);

    $pluginChk = $app['pdo']->prepare(
        'SELECT pa.platform, p.name, pd.website, (CASE WHEN pd.compliant = 1 THEN "true" ELSE "false" END) AS compliant, pd.last_checked, pp.price 
           FROM platform_plugin pp 
           JOIN platform pa ON pp.platform_id = pa.id
           JOIN plugin p ON pp.plugin_id = p.id
           JOIN plugin_details pd ON pp.plugin_id = pd.plugin_id
           WHERE (p.name LIKE ?) AND (pa.platform LIKE ?)
           ORDER BY p.name
    ');
    $pluginChk->execute(['%' . $plugin . '%', '%' . $platform . '%']);
    $data = $pluginChk->fetchAll(\PDO::FETCH_ASSOC);
    $totalResults = count($data);
    $success = 'No results found';
    if (0 < $totalResults) {
        $success = 'Successful finding plugins';
    }
    return $app->json(['result' => $success, 'count' => $totalResults, 'plugins' => $data]);
});

$app->run();
