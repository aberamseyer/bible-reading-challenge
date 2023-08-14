## Dependencies
Written with php 8.2.3 and apache 2.4.38

Install [composer](https://getcomposer.org/) dependencies in root of project

## Timezone
update php ini file date.timezone line to "America/Chicago"
- phpinfo() and look for "loaded configuration file" to find ini file to edit

## Database
create SQLite database file named "brc.db" in root of project

## API Keys
create a '.env' file in the project root. required keys:
- SENDGRID_API_KEY_ID
- SENDGRID_API_KEY