# Dependencies

## Language
Written with php 8.2.3 and apache 2.4.38

## Configuration
Set the system time zone and php time zone (`date.timezone` in `php.ini`) to the same thing.

In `php.ini`, set the following:
```
max_input_vars = 3000;
upload_max_filesize = 10M;
```

This is sufficient for editing a schedule that is up to 4 years long and typical picture uploads.

Install [composer](https://getcomposer.org/) dependencies in root of project with `composer install`

In apache and nginx configuration, be sure file uploads are also set to be at least 10MB.

## Database

#### SQLite
create an SQLite database file named "brc.db" in root of project from schema.sql

Initialize it with the data from `migrations/bible-import.sql`

#### Redis
Redis is used for session management.

Run a Redis (or compatible) server, default ports.
`docker run -v ./:/data --restart unless-stopped -it -p 6379:6379 redis:7-alpine`

### Schema
to export the schema after an update, run `sqlite3 brc.db ".schema --indent" > migrations/schema.sql`

## Realtime updates
This website supports readers' seeing each other on the page reading together

### Setup
From the `socket` directory, run `npm i` and then keep it alive with `forever start server.js`

It defaults to port `8085`, customizable with the environment variable `SOCKET_PORT`

## API Keys
Each site created in the database requires the following values in the 'env' column

### Emails
- SENDGRID_API_KEY_ID
- SENDGRID_API_KEY
- SENDGRID_REGISTER_EMAIL_TEMPLATE
- SENDGRID_DAILY_EMAIL_TEMPLATE
- SENDGRID_FORGOT_PASSWORD_TEMPLATE

### Google Sign-in button
also requires configuring OAuth consent screen in Google Cloud Console
- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET

## Crons
files in the `cron` directory should be installed according to the comments at the top of each file

## Migrations
Any scripts in the `migration` directory are meant to be run-once for a particular purpose (e.g., initiating streaks mid-challenge). See comments in each file