<?php
namespace In2it;

require_once __DIR__ . '/woocommerce-slurper.php';
require_once __DIR__ . '/prestashop-slurper.php';
require_once __DIR__ . '/magento-slurper.php';

$pdo = new \PDO('sqlite:' . realpath(__DIR__ . '/../plugins.db'));
$wcs = new WooCommerceSlurper($pdo);
//$wcs->slurp();
$pss = new PrestaShopSlurper($pdo);
$pss->slurp();
/*$ms = new MagentoSlurper($pdo);
$ms->slurp();*/