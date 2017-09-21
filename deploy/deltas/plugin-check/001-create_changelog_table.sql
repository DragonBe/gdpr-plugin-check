-- // 001 - Creating changelog table for automated DB mirgrations

CREATE TABLE changelog
(
  change_number INTEGER DEFAULT 0 NOT NULL,
  delta_set     TEXT    DEFAULT "plugin-check" NOT NULL,
  start_dt      TEXT    DEFAULT "" NOT NULL,
  complete_dt   TEXT    DEFAULT "" NOT NULL,
  applied_by    TEXT    DEFAULT "" NOT NULL,
  description   TEXT    DEFAULT "" NOT NULL
);

INSERT INTO changelog
VALUES (1, "plugin-check", "1505988768", "1505988768", "DB Admin",
        "Creation of automated database change migrations");

-- //@UNDO

DROP TABLE changelog;

-- //