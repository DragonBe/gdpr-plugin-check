<?php

if (false === ($pdo = new PDO('sqlite:plugins.db'))) {
    echo 'Cannot connect to DB';
    exit(1);
}

$urls = [
    'https://woocommerce.com/privacy-policy/' => 'This is a generic WooCommerce privacy policy describing good practices but is not specific for the plugin or creator directly.',
    'https://www.skyverge.com/privacy-policy/' => 'This is a generic Automattic privacy policy adopted by SkyVerge but is not detailing the behaviour of individual plugins or shared services used by a plugin.',
];
$metaData = [
    'date_reviewed' => '2018-06-14',
    'comment' => '',
    'policy_hash' => ''
];
$pluginIdList = [];

$pluginQry = 'SELECT p.id from plugin p INNER JOIN plugin_details pd on p.id = pd.plugin_id WHERE pd.privacy_policy LIKE ?';
$pluginMetaInsertQry = 'INSERT INTO plugin_meta (plugin_id, label, value) VALUES (?, ?, ?)';
if (false === ($pluginIdStmt = $pdo->prepare($pluginQry))) {
    echo 'Cannot prepare query for plugins: ' . implode(', ', $pdo->errorInfo());
    exit(1);
}
if (false === ($pluginMetaStmt = $pdo->prepare($pluginMetaInsertQry))) {
    echo 'Cannot prepare query for inserting meta info of plugins: ' . implode(', ', $pdo->errorInfo());
    exit(1);
}
foreach ($urls as $url => $comment) {
    $hash = md5_file($url);
    $metaData['comment'] = $comment;
    $metaData['policy_hash'] = $hash;
    $pluginIdStmt->bindValue(1, $url, PDO::PARAM_STR);
    if (false === $pluginIdStmt->execute()) {
        echo 'Cannot execute statement for ' . $url;
        exit(1);
    }
    while ($pluginId = $pluginIdStmt->fetchColumn(0)) {
        foreach ($metaData as $key => $value) {
            $pluginMetaStmt->bindValue(1, $pluginId, PDO::PARAM_INT);
            $pluginMetaStmt->bindValue(2, $key, PDO::PARAM_STR);
            $pluginMetaStmt->bindValue(3, $value, PDO::PARAM_STR);
            $pluginMetaStmt->execute();
        }
    }
}
