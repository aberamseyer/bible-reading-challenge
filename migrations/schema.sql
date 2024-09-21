CREATE TABLE sessions(
  id TEXT PRIMARY KEY,
  data TEXT,
  last_updated DATETIME DEFAULT(CURRENT_TIMESTAMP)
);
CREATE TABLE read_dates(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  schedule_date_id INTEGER,
  timestamp TEXT
);
CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE chapters(
  id INTEGER NOT NULL
  PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL,
  number INTEGER NOT NULL,
  verses INTEGER NOT NULL,
  word_count INTEGER
);
CREATE TABLE images(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER,
  uploaded_by_id INTEGER,
  uploaded_by_name TEXT,
  uploaded_name TEXT,
  md5 TEXT,
  uploads_dir_filename TEXT,
  extension TEXT,
  mime_type TEXT,
  date_uploaded TEXT DEFAULT(CURRENT_TIMESTAMP)
);
CREATE TABLE schedules(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER,
  user_id INTEGER,
  name TEXT,
  start_date TEXT,
  end_date TEXT,
  active INTEGER DEFAULT(0)
);
CREATE TABLE verses(
  id INTEGER NOT NULL
  PRIMARY KEY AUTOINCREMENT,
  chapter_id INTEGER NOT NULL,
  number INTEGER NOT NULL,
  rcv TEXT NOT NULL,
  kjv TEXT NOT NULL,
  niv TEXT NOT NULL,
  esv TEXT NOT NULL,
  nlt TEXT NOT NULL,
  orig TEXT NOT NULL,
  asv TEXT,
  word_count INTEGER
);
CREATE TABLE books(
  id INTEGER NOT NULL
  PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(32) NOT NULL,
  chapters INTEGER NOT NULL,
  testament INTEGER NOT NULL,
  abbreviations LONGTEXT NOT NULL,
  word_count INTEGER
);
CREATE TABLE schedule_dates(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  schedule_id INTEGER,
  date TEXT,
  passage INTEGER,
  passage_chapter_readings TEXT,
  complete_key TEXT,
  word_count INTEGER
);
CREATE TABLE users(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER,
  uuid TEXT,
  name TEXT,
  trans_pref TEXT,
  staff INTEGER DEFAULT(0),
  email TEXT,
  password TEXT,
  last_seen TEXT,
  date_created TEXT,
  email_verified INTEGER,
  email_verses INTEGER,
  streak INTEGER DEFAULT(0),
  max_streak INTEGER DEFAULT(0),
  emoji TEXT
);
CREATE VIEW read_dates_dated AS SELECT rd.id, rd.user_id, u.name, rd.timestamp, DATETIME(rd.timestamp, 'unixepoch') date, sd.passage
FROM read_dates rd
JOIN schedule_dates sd ON rd.schedule_date_id = sd.id
JOIN users u ON rd.user_id = u.id
ORDER BY rd.id DESC
/* read_dates_dated(id,user_id,name,timestamp,date,passage) */;
CREATE VIEW schedule_date_verses AS
SELECT sd.id schedule_date_id, sd.schedule_id, sd.date, sd.passage, b.name || ' ' || c.number || ':' || v.number reference, b.id book_id, b.name book_name, c.id chapter_id, c.number chapter_number, c.verses, v.word_count, v.number verse_number, v.id verse_id, JSON_EXTRACT(je.value, '$.s') verse_start, JSON_EXTRACT(je.value, '$.e') verse_end, rcv, kjv, niv, esv, orig, asv
FROM schedule_dates sd
JOIN JSON_EACH(passage_chapter_readings) je
JOIN chapters c ON c.id = JSON_EXTRACT(je.value, '$.id')
JOIN verses v ON v.chapter_id = c.id 
JOIN books b ON b.id = c.book_id 
WHERE v.number BETWEEN JSON_EXTRACT(je.value, '$.s') AND JSON_EXTRACT(je.value, '$.e')
/* schedule_date_verses(schedule_date_id,schedule_id,date,passage,reference,book_id,book_name,chapter_id,chapter_number,verses,word_count,verse_number,verse_id,verse_start,verse_end,rcv,kjv,niv,esv,orig,asv) */;
CREATE INDEX idx_chapters_book_id ON chapters(book_id);
CREATE INDEX idx_verses_chapter_id ON verses(chapter_id);
CREATE INDEX idx_verses_esv ON verses(esv);
CREATE INDEX idx_verses_kjv ON verses(kjv);
CREATE INDEX idx_verses_niv ON verses(niv);
CREATE INDEX idx_verses_nlt ON verses(nlt);
CREATE INDEX idx_verses_text ON verses(rcv);
CREATE INDEX idx_user_id ON read_dates(user_id);
CREATE INDEX idx_schedule_date_id ON read_dates(schedule_date_id);
CREATE INDEX idx_schedules_site_id ON schedules(site_id);
CREATE INDEX idx_schedules_user_id ON schedules(user_id);
CREATE INDEX idx_images_site_id ON images(site_id);
CREATE INDEX idx_books_name ON books(name);
CREATE INDEX idx_read_dates_timestamp ON read_dates(timestamp ASC);
CREATE INDEX idx_schedule_id ON schedule_dates(schedule_id);
CREATE INDEX idx_site_id ON users(site_id);
CREATE INDEX idx_schedule_dates_date ON schedule_dates(date);
CREATE INDEX idx_users_uuid ON users(uuid);
CREATE INDEX idx_users_last_seen ON users(last_seen);
CREATE TABLE push_subscriptions(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  subscription TEXT,
  last_sent TEXT,
  last_updated TEXT
);
CREATE TABLE sites(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  enabled INTEGER DEFAULT(1),
  site_name TEXT,
  domain_www TEXT,
  domain_www_test TEXT,
  domain_socket TEXT,
  domain_socket_test TEXT,
  short_name TEXT,
  contact_name TEXT,
  contact_email TEXT,
  contact_phone TEXT,
  email_from_address TEXT,
  email_from_name TEXT,
  favico_image_id INTEGER,
  logo_image_id INTEGER,
  login_image_id INTEGER,
  progress_image_id INTEGER,
  progress_image_coordinates TEXT DEFAULT('[50,0,50,88]'), color_primary TEXT DEFAULT('rgb(0, 0, 0)'), color_secondary TEXT DEFAULT('rgb(0, 0, 0)'), color_fade TEXT DEFAULT('rgb(0, 0, 0)'), default_emoji TEXT, reading_timer_wpm INTEGER DEFAULT(0), start_of_week INTEGER DEFAULT(1), time_zone_id TEXT DEFAULT('America/Chicago'), env TEXT, allow_personal_schedules INTEGER DEFAULT(0), translations TEXT DEFAULT ["rcv",
  "kjv",
  "esv",
  "asv",
  "niv",
  "nlt"],
  vapid_pubkey TEXT,
  vapid_privkey TEXT
);
CREATE INDEX idx_push_subscriptions_user_id ON push_subscriptions(user_id);
