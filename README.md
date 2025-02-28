# Bible Reading Challenge
An application for coordinated reading schedules to read through the Bible together with customizable personal and group schedules, push notifications, emails, and lots of statistics.

Supports multiple deployments of the application running from the same source code and database, each customizable to match branding

See also [here](https://abe.ramseyer.dev/work/bible-reading-challenge/)

The live deployment of my personal site for this [here](https://brc.ramseyer.dev). Feel free to join and read with me!

# Dependencies

## Language
Written with php `8.2.20` and apache `2.4.61`
- REQUIRES php `8.2` because of [this bug](https://github.com/php/php-src/pull/8292) and Mailgun's API for bulk sending

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
Requires MINIMUM version 3.46 (support for `GROUP_CONCAT(..ORDER BY..)`, `->>` syntax, and `strftime('%U')` modifier)

Create an SQLite database file named "brc.db" in root of project from schema.sql

Initialize it with the data from `migrations/bible-import.sql`

Enable WAL mode: `sqlite3 brc.db "PRAGMA journal_mode=WAL;"`

An example backup script example can be found in `extras/db-backup.sh`

#### Redis
Redis is used for session management.

Run a Redis (or compatible) server, default ports.
```sh
# Server
docker run -v /home/bible-reading-challenge/:/data --restart unless-stopped -d -it -p 6379:6379 redis:7-alpine
# locally
docker run -it --rm -v ./:/data -p 6379:6379 redis:7-alpine
```

##### Stats
to refresh stats use `redis-cli --scan --pattern "bible-reading-challenge:user-stats/*" | xargs -L 1 redis-cli del`

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
Be sure to set up the email templates in the sendgrid console
- MAILGUN_SENDING_API_KEY_PROD (used on production domain)
- MAILGUN_SENDING_API_KEY_LOCAL (used when not on production domain)

### Google Sign-in button
also requires configuring OAuth consent screen in Google Cloud Console. Save this in a `.env` file at the project root
- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET (currently unused)

## Crons
files in the `cron` directory should be installed according to the comments at the top of each file

## Migrations
Any scripts in the `migration` directory are meant to be run-once for a particular purpose (e.g., initiating streaks mid-challenge). See comments in each file.

Numbers indicate the order in which scripts were created and the date changed.

From the root of the project, run `extras/dump-schema.sh` to save the current database schema to `migrations/schema.sql`

## Logo Generation
For different logo sizes to be generated for PWA installation, the `magick` command must be avilable somewhere in the `PATH` environment. See `logo_pngs()` in `Site.php` where `set_include_path()` is called
