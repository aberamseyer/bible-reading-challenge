CREATE TABLE `books`(
  `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT
  ,
  `name` varchar(32) NOT NULL
  ,
  `chapters` integer NOT NULL
  ,
  `testament` integer NOT NULL
  ,
  `abbreviations` longtext NOT NULL
);
CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE verses(
  id integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  chapter_id integer NOT NULL,
  number integer NOT NULL,
  rcv text NOT NULL,
  kjv text NOT NULL,
  niv text NOT NULL,
  esv text NOT NULL,
  nlt text NOT NULL,
  orig text NOT NULL,
  asv TEXT
);
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
CREATE TABLE schedule_dates(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  schedule_id INTEGER,
  date TEXT,
  passage INTEGER,
  passage_chapter_ids TEXT,
  complete_key TEXT
);
CREATE TABLE chapters(
  id INTEGER NOT NULL
  PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL,
  number INTEGER NOT NULL,
  verses INTEGER NOT NULL,
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
  email_verify_token TEXT,
  email_verified INTEGER,
  forgot_password_token TEXT,
  forgot_password_expires TEXT,
  email_verses INTEGER,
  streak INTEGER DEFAULT(0),
  max_streak INTEGER DEFAULT(0),
  emoji TEXT,
  websocket_nonce TEXT
);
CREATE TABLE schedules(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER,
  name TEXT,
  start_date TEXT,
  end_date TEXT,
  active INTEGER
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
  favico_image_id INTEGER,
  logo_image_id INTEGER,
  login_image_id INTEGER,
  progress_image_id INTEGER,
  progress_image_coordinates TEXT DEFAULT('[50,0,50,88]'),
color_primary TEXT DEFAULT('rgb(0, 0, 0)'),
color_secondary TEXT DEFAULT('rgb(0, 0, 0)'),
color_fade TEXT DEFAULT('rgb(0, 0, 0)'),
default_emoji TEXT,
start_of_week INTEGER,
time_zone_id TEXT,
env TEXT
);
CREATE INDEX "idx_books_name" ON "books"(`name`);
CREATE INDEX idx_verses_chapter_id ON verses(chapter_id);
CREATE INDEX idx_verses_esv ON verses(esv);
CREATE INDEX idx_verses_kjv ON verses(kjv);
CREATE INDEX idx_verses_niv ON verses(niv);
CREATE INDEX idx_verses_nlt ON verses(nlt);
CREATE INDEX idx_verses_text ON verses(rcv);
CREATE INDEX idx_chapters_book_id ON chapters(book_id);
CREATE TABLE images(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER,
  uploaded_by_id INTEGER,
  uploaded_name TEXT,
  md5 TEXT,
  uploads_dir_filename TEXT,
  extension TEXT,
  mime_type TEXT,
  date_uploaded TEXT DEFAULT(CURRENT_TIMESTAMP)
);
