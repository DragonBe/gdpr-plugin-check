<?php

namespace In2it;

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class WooCommerceSlurper
{
    const BASE_URL = 'https://woocommerce.com/product-category/woocommerce-extensions';
    const PLATFORM_WOOCOMMERCE = 3;

    /**
     * @var array
     */
    protected $categories;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var int
     */
    protected $platformId;

    public function __construct(\PDO $pdo)
    {
        $this->init();
        $this->pdo = $pdo;
        $this->platformId = self::PLATFORM_WOOCOMMERCE;
    }

    private function init()
    {
        $this->categories = [
            'payment-gateways',
            'shipping-methods',
        ];
    }

    public function slurp()
    {
        foreach ($this->categories as $category) {
            $page = 1;
            do {
                $url = sprintf('%s/%s/page/%d/', self::BASE_URL, $category, $page);
                echo 'Processing page ' . $page . ' for ' . $category . ' on WooCommerce' . PHP_EOL;
                $result = $this->process($url);
                $page++;
            } while (true === $result);
        }
    }

    protected function process(string $url): bool
    {
        try {
            $domdoc = $this->loadDomDocument($url);
        } catch (\DomainException $e) {
            return false;
        }

        $listings = $domdoc->getElementsByTagName('li');
        foreach ($listings as $listing) {
            $entry = ['url' => '', 'name' => '', 'price' => '', 'privacy_policy' => ''];
            if ($listing->hasAttribute('class') && 'product' === substr($listing->getAttribute('class'), 0, 7)) {
                $links = $listing->getElementsByTagName('a');
                foreach ($links as $link) {
                    $entry['url'] = $link->getAttribute('href');
                    $entry['privacy_policy'] = $this->retrieveMetaData($entry['url']);
                }
                $pluginNames = $listing->getElementsByTagName('h3');
                foreach ($pluginNames as $pluginName) {
                    $entry['name'] = $pluginName->nodeValue;
                }
                $spans = $listing->getElementsByTagName('span');
                foreach ($spans as $span) {
                    $class = $span->getAttribute('class');
                    if (false !== strstr($class, 'amount')) {
                        $currency = $span->getElementsByTagName('span')->item(0);
                        $span->removeChild($currency);
                        $entry['price'] = (float) $span->nodeValue;
                    }
                }
                $this->save($entry);
            }
        }
        return true;
    }

    /**
     * Loads the DOM of a given URL or throws a Domain
     * Exception if something goes wrong
     *
     * @param string $url
     * @return \DOMDocument
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function loadDomDocument(string $url): \DOMDocument
    {
        $hash = md5($url);
        if (!file_exists(__DIR__ . '/cache/' . $hash)) {
            $client = new Client();
            try {
                $request = $client->request('GET', $url);
            } catch (ClientException $clientException) {
                throw new \DomainException('Can not make a successful request: ' . $clientException->getMessage());
            }

            if (404 === $request->getStatusCode()) {
                throw new \DomainException('Can not find a result for ' . $url);
            }

            $body = $request->getBody();
            $page = (string)$body;
            file_put_contents(__DIR__ . '/cache/' . $hash, $page);
        }
        $page = file_get_contents(__DIR__ . '/cache/' . $hash);

        $domdoc = new \DOMDocument();
        $domdoc->loadHTML($page, LIBXML_NOWARNING | LIBXML_NOERROR);
        return $domdoc;
    }

    public function save(array $entry)
    {
        $date = new \DateTime('now', new \DateTimeZone('Europe/Brussels'));
        $timeStamp = $date->format('Y-m-d H:i:s');

        $lookupStmt = $this->pdo->prepare('SELECT plugin_id, website FROM plugin_details WHERE (website = ?)');
        $lookupStmt->execute([$entry['url']]);

        $result = $lookupStmt->fetch(\PDO::FETCH_ASSOC);

        if (false === $result) {
            $insertPluginStmt = $this->pdo->prepare('INSERT INTO plugin (name) VALUES (?)');
            $insertPluginStmt->execute([$entry['name']]);
            $pluginId = $this->pdo->lastInsertId();

            $insertPluginDetailsStmt = $this->pdo->prepare('INSERT INTO plugin_details (plugin_id, website, compliant, last_checked, privacy_policy) VALUES (?, ?, ?, ?, ?)');
            $insertPluginDetailsStmt->execute([$pluginId, $entry['url'], 0, $timeStamp, $entry['privacy_policy']]);

            $insertPlatformPluginStmt = $this->pdo->prepare('INSERT INTO platform_plugin (platform_id, plugin_id, price) VALUES (?, ?, ?)');
            $insertPlatformPluginStmt->execute([$this->platformId, $pluginId, $entry['price']]);
        } else {
            $pluginId = (int) $result['plugin_id'];

            $updatePluginStmt = $this->pdo->prepare('UPDATE plugin SET name = ? WHERE id = ?');
            $updatePluginStmt->execute([$entry['name'], $pluginId]);

            $updatePluginDetailsStmt = $this->pdo->prepare('UPDATE plugin_details SET website = ?, last_checked = ?, privacy_policy = ? WHERE plugin_id = ?');
            $updatePluginDetailsStmt->execute([$entry['url'], $timeStamp, $entry['privacy_policy'], $pluginId]);

            $updatePlatformPluginStmt = $this->pdo->prepare('UPDATE platform_plugin SET price = ? WHERE (platform_id = ?) AND (plugin_id = ?)');
            $updatePlatformPluginStmt->execute([$entry['price'], $this->platformId, $pluginId]);
        }
    }

    public function retrieveMetaData($url): string
    {
        try {
            $domdoc = $this->loadDomDocument($url);
        } catch (\DomainException $e) {
            echo 'Cannot load meta information: ' . $e->getMessage() . PHP_EOL;
            return '';
        }
        echo 'Retrieving meta data for ' . $url . PHP_EOL;
        $divs = $domdoc->getElementsByTagName('div');
        foreach ($divs as $div) {
            if ($div->hasAttribute('class') && 'get_support_privacy_policy' === (string) $div->getAttribute('class')) {
                $privacyLinks = $div->getElementsByTagName('a');
                foreach ($privacyLinks as $privacyLink) {
                    if ($privacyLink->hasAttribute('href')) {
                        return $privacyLink->getAttribute('href');
                    }
                }
            }
        }
        return '';
    }
}
