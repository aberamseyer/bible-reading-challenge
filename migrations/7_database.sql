-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-14 07:01:40
PRAGMA foreign_keys = 0;
CREATE TABLE sqlitestudio_temp_table AS SELECT * FROM push_subscriptions;
DROP TABLE push_subscriptions;
CREATE TABLE push_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, last_sent TEXT, endpoint TEXT, publicKey TEXT, authToken TEXT, contentEncoding TEXT);
INSERT INTO push_subscriptions (id, user_id, last_sent) SELECT id, user_id, last_sent FROM sqlitestudio_temp_table;
DROP TABLE sqlitestudio_temp_table;
CREATE INDEX idx_push_subscriptions_user_id ON push_subscriptions (user_id);
PRAGMA foreign_keys = 1;

-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-14 07:04:57
CREATE INDEX idx_push_subscriptions_endpoint ON push_subscriptions (endpoint);

-- Queries executed on database brc (/Users/user/Developer/bible-reading-challenge/brc.db)
-- Date and time of execution: 2024-08-14 18:18:01
PRAGMA foreign_keys = 0;
CREATE TABLE sqlitestudio_temp_table AS SELECT * FROM push_subscriptions;
DROP TABLE push_subscriptions;
CREATE TABLE push_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, last_sent TEXT, endpoint TEXT, subscription TEXT);
INSERT INTO push_subscriptions (id, user_id, last_sent, endpoint, subscription) SELECT id, user_id, last_sent, endpoint, contentEncoding FROM sqlitestudio_temp_table;
DROP TABLE sqlitestudio_temp_table;
CREATE INDEX idx_push_subscriptions_endpoint ON push_subscriptions (endpoint);
CREATE INDEX idx_push_subscriptions_user_id ON push_subscriptions (user_id);
PRAGMA foreign_keys = 1;