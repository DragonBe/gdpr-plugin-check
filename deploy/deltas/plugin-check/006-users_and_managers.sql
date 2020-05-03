-- // 6 - Users and managers

CREATE TABLE manager (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL DEFAULT '',
    password TEXT NOT NULL DEFAULT ''
);

INSERT INTO manager (username, password) VALUES ('FooBar', '');

-- //@UNDO

DROP TABLE manager;

-- //