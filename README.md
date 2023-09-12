## Dependencies
Written with php 8.2.3 and apache 2.4.38

Install [composer](https://getcomposer.org/) dependencies in root of project with `composer install`

## Database
create SQLite database file named "brc.db" in root of project from schema.sql

### Schema
to export the schema after an update, run `sqlite3 brc.db ".schema --indent" > schema.sql`

## API Keys
create a '.env' file in the project root. required keys:

### Emails
- SENDGRID_API_KEY_ID
- SENDGRID_API_KEY

### Google Sign-in button
also requires configuring OAuth consent screen in Google Cloud Console
- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET
