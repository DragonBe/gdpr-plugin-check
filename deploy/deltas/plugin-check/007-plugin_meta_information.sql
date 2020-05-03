-- // 7 - Plugin meta information

ALTER TABLE plugin_details
    ADD COLUMN privacy_policy TEXT NOT NULL DEFAULT '';

CREATE TABLE plugin_meta (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    plugin_id INTEGER NOT NULL DEFAULT 0,
    label TEXT DEFAULT '',
    value TEXT DEFAULT '',
    FOREIGN KEY (plugin_id) REFERENCES plugin(id)
);

-- //@UNDO

-- SQLite doesn't support dropping a column.
DROP TABLE plugin_meta;

-- //