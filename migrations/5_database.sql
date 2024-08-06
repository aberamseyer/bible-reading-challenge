-- Queries executed on database brc (bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-03 16:59:06
PRAGMA foreign_keys = 0;
CREATE TABLE sqlitestudio_temp_table AS SELECT * FROM users;
DROP TABLE users;
CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER, uuid TEXT, name TEXT, trans_pref TEXT, staff INTEGER DEFAULT (0), email TEXT, password TEXT, last_seen TEXT, date_created TEXT, email_verified INTEGER, email_verses INTEGER, streak INTEGER DEFAULT (0), max_streak INTEGER DEFAULT (0), emoji TEXT);
INSERT INTO users (id, site_id, uuid, name, trans_pref, staff, email, password, last_seen, date_created, email_verified, email_verses, streak, max_streak, emoji) SELECT id, site_id, uuid, name, trans_pref, staff, email, password, last_seen, date_created, email_verified, email_verses, streak, max_streak, emoji FROM sqlitestudio_temp_table;
DROP TABLE sqlitestudio_temp_table;
CREATE INDEX idx_site_id ON users (site_id);
PRAGMA foreign_keys = 1;

-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-05 09:57:18
CREATE INDEX idx_schedule_dates_date ON schedule_dates (date);

-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-05 09:59:42
CREATE INDEX idx_users_uuid ON users (uuid);

-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-05 10:00:00
CREATE INDEX idx_users_last_seen ON users (last_seen);