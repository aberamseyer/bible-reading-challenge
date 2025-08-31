CREATE TABLE verse_email_stats (
	email_id TEXT NOT NULL PRIMARY KEY,
	user_id INTEGER,
	schedule_date_id INTEGER,
	sent_timestamp TEXT,
	opened_timestamp TEXT,
	clicked_done_timestamp TEXT,
	CONSTRAINT emails_users_FK FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_verse_email_stats_email_id ON verse_email_stats(email_id);
CREATE INDEX idx_verse_email_stats_user_id ON verse_email_stats(user_id);
CREATE INDEX idx_verse_email_stats_schedule_date_id ON verse_email_stats(
  schedule_date_id
);
