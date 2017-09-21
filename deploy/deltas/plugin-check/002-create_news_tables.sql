-- // 002 - Creating table for news updates and notifications

CREATE TABLE news (
  id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL DEFAULT "",
  article TEXT NOT NULL DEFAULT "",
  created TEXT NOT NULL DEFAULT "",
  published TEXT NOT NULL DEFAULT ""
);

-- //@UNDO

DROP TABLE news;

-- //