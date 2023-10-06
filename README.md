# Dependencies

## Language
Written with php 8.2.3 and apache 2.4.38

Install [composer](https://getcomposer.org/) dependencies in root of project with `composer install`

## Database
create SQLite database file named "brc.db" in root of project from schema.sql

Initialize it with the data from `migrations/bible-import.sql`

### Schema
to export the schema after an update, run `sqlite3 brc.db ".schema --indent" > migrations/schema.sql`


## Realtime updates
This website supports readers' seeing each other on the page reading together

### Setup
From the `socket` directory, run `npm i` and then keep it alive with `forever start server.js`

## API Keys
create a '.env' file in the project root. required keys:

### Emails
- SENDGRID_API_KEY_ID
- SENDGRID_API_KEY

### Google Sign-in button
also requires configuring OAuth consent screen in Google Cloud Console
- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET

## Crons
files in the `cron` directory should be installed according to the comments at the top of each file

## Migrations
Any scripts in the `migration` directory are meant to be run-once for a particular purpose (e.g., initiating streaks mid-challenge). See comments in each file