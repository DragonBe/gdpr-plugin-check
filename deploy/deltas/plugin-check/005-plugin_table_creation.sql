-- // 5 - Plugin table creation

CREATE TABLE plugin (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL DEFAULT ''
);

CREATE TABLE plugin_details (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    plugin_id INTEGER NOT NULL DEFAULT 0,
    website TEXT NOT NULL DEFAULT '',
    compliant INTEGER NOT NULL DEFAULT 0,
    last_checked TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (plugin_id) REFERENCES plugin(id)
);

CREATE TABLE platform (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    platform TEXT NOT NULL DEFAULT ''
);
INSERT INTO platform (platform) VALUES ('magento'), ('prestashop'), ('woocommerce');

CREATE TABLE platform_plugin (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    platform_id INTEGER NOT NULL DEFAULT 0,
    plugin_id INTEGER NOT NULL DEFAULT 0,
    price REAL NOT NULL DEFAULT 0,
    FOREIGN KEY (plugin_id) REFERENCES plugin(id),
    FOREIGN KEY (platform_id) REFERENCES platform(id)
);

-- //@UNDO

DROP TABLE platform_plugin;
DROP TABLE plugin_details;
DROP TABLE platform;
DROP TABLE plugin;

-- //