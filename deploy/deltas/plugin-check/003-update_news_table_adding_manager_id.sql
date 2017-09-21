-- // 003 - Updating news table to include manager reference

ALTER TABLE news RENAME TO news_bak;

CREATE TABLE news (
  id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  manager_id INTEGER NOT NULL DEFAULT 0,
  title TEXT NOT NULL DEFAULT "",
  article TEXT NOT NULL DEFAULT "",
  created TEXT NOT NULL DEFAULT "",
  published TEXT NOT NULL DEFAULT ""
);

INSERT INTO news (id, title, article, created, published) SELECT * FROM news_bak;

DROP TABLE news_bak;

-- //@UNDO

ALTER TABLE news RENAME TO news_bak;

CREATE TABLE news (
  id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL DEFAULT "",
  article TEXT NOT NULL DEFAULT "",
  created TEXT NOT NULL DEFAULT "",
  published TEXT NOT NULL DEFAULT ""
);

INSERT INTO news SELECT (id, title, article, created, published) FROM news_bak;

DROP TABLE news_bak;

-- //