-- // 8 - Adding platform search field

ALTER TABLE platform
    ADD COLUMN search TEXT NOT NULL DEFAULT '';

-- //@UNDO

-- Cannot remove the column from a table in SQLite

-- //