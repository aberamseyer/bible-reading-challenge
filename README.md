# Bible Reading Challenge

An application for coordinated reading schedules to read through the Bible together with customizable personal and group schedules, push notifications, emails, and lots of statistics.

Supports multiple deployments of the application running from the same source code and database, each customizable to match branding.

Demo video [here](https://youtu.be/5PcOdYpnv_U)

See also [here](https://abe.ramseyer.dev/work/bible-reading-challenge/)

The live deployment of my personal site for this [here](https://brc.ramseyer.dev). Feel free to join and read with me!

---

## Local Development Setup

1.  **Clone the Repository:**
2.  **Set up Git Pre-commit Hook (for versioning):**
    This application uses a Git pre-commit hook to automatically write the current commit hash to the file `./extras/version.txt` file. This file is then read by the application at runtime.

    To set this up, create a symlink or copy the hook script from `.githooks/pre-commit` to `.git/hooks/pre-commit`:

    ```bash
    ln -s -f ../../.githooks/pre-commit .git/hooks/pre-commit
    ```

3.  **Create and Configure `.env` File:**
    Copy the example environment file and customize it with your settings:

    ```bash
    cp .env.example .env
    ```

    Edit `.env` and fill in your details, especially:

    - `GOOGLE_CLIENT_ID`
    - `DEPLOYMENT_EMAIL_FROM_ADDRESS`
    - `DEPLOYMENT_EMAIL_TO_ADDRESS`

4.  **Prepare the SQLite Database:**

    - Create an empty SQLite database file named `brc.db` in the project root:
      ```bash
      sqlite3 brc.db < migrations/schema.sql
      ```
    - Initialize it with the Bible data:
      ```bash
      sqlite3 brc.db < migrations/bible-import.sql
      ```
    - Enable WAL (Write-Ahead Logging) mode for better concurrency:
      ```bash
      sqlite3 brc.db "PRAGMA journal_mode=WAL;"
      ```

5.  **Build and Run the Application with Docker Compose:**

    ```bash
    docker-compose up --build -d
    ```

6.  **Access the Application:**
    - Web Application: `http://localhost:8080` (or the `NGINX_HOST_PORT` you configured in `.env`).
    - WebSocket Server: Listens on port `8085` (or `SOCKET_HOST_PORT` / `SOCKET_PORT` configured).

## Services Managed by Docker Compose

- **`php`:** Includes necessary extensions, Composer dependencies, and reads from the shared application code.
- **`nginx`:** Serves the PHP application via PHP-FPM.
- **`redis`:** Used for session management and caching (e.g., site version). Data is persisted in a Docker volume.
- **`socket`:** WebSocket server for real-time updates.
- **`cron`:** A dedicated container running cron jobs defined in `.docker/cron/crontab`. It uses the same PHP environment as the main `php` service.

## Additional Details

### Stats

to refresh stats use `redis-cli --scan --pattern "bible-reading-challenge:user-stats/*" | xargs -L 1 redis-cli del`

### Schema

to export the schema after an update, run `sqlite3 brc.db ".schema --indent" > migrations/schema.sql`

### API Keys & Environment Variables

- All API keys and environment-specific configurations should be placed in the `.env` file at the project root.
- **Site-Specific `env` Data:** The mechanism for storing site-specific `env` values in the database `sites.env` column remains relevant for multi-tenant deployments.
- **Deployment-wide Emails:**

  - Variables `DEPLOYMENT_EMAIL_FROM_ADDRESS` and `DEPLOYMENT_EMAIL_TO_ADDRESS` in `.env` are used.

  - For deployment-wide administration emails (not site-specific emails), configure postfix or whatever [PhpMailer's sendmail](https://github.com/PHPMailer/PHPMailer/blob/v6.9.3/examples/sendmail.phps) is going to interface with.
  - I set up mine with OCI Email Delivery following [this guide](https://docs.oracle.com/en-us/iaas/Content/Email/Reference/postfix.htm)

### Google Sign-in button

also requires configuring OAuth consent screen in Google Cloud Console.

## Migrations

Any scripts in the `migration` directory are meant to be run-once for a particular purpose (e.g., initiating streaks mid-challenge). See comments in each file.

Numbers indicate the order in which scripts were created and the date changed.

From the root of the project, run `extras/dump-schema.sh` to save the current database schema to `migrations/schema.sql`

## Logo Generation

For different logo sizes to be generated for PWA installation, the `magick` command must be avilable somewhere in the `PATH` environment. See `logo_pngs()` in `Site.php` where `set_include_path()` is called

## Cron Jobs

- Cron jobs are defined in `.docker/cron/crontab`.
- They are executed by the `cron` service, which shares the same PHP environment and application code as the main `php` service.
- Logs from cron jobs are directed to the Docker container's output and can be viewed with `docker-compose logs brc_cron`.

## Managing the Application

- **View Logs:**
  ```bash
  docker-compose logs -f <service_name>  # e.g., php, nginx, cron, redis, socket
  docker-compose logs -f # View logs for all services
  ```
- **Stop Application:**
  ```bash
  docker-compose down
  ```
- **Stop and Remove Volumes (e.g., to clear Redis data):**
  ```bash
  docker-compose down -v
  ```
- **Access a Running Container (e.g., to run a command inside the PHP container):**
  ```bash
  docker-compose exec php bash
  ```
- **Rebuild Images:**
  ```bash
  docker-compose build
  # or
  docker-compose up --build -d
  ```
