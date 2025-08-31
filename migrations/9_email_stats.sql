CREATE TABLE verse_email_stats (
	email_id TEXT NOT NULL PRIMARY KEY,
	user_id INTEGER,
	schedule_date_id INTEGER,
	sent_timestamp TEXT,
	opened_timestamp TEXT,
	clicked_done_timestamp TEXT,
	CONSTRAINT emails_users_FK FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
