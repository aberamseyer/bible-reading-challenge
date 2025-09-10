# PHP Debugging Setup for Bible Reading Challenge

This document explains how to set up and use PHP debugging with Xdebug in the Docker development environment.

## Quick Start

1. **Setup Environment**

   ```bash
   # Copy the debugging environment template
   cp .env.debug.example .env

   # Edit .env with your specific configuration
   vim .env
   ```

2. **Start Development Environment**

   ```bash
   # Use the development startup script
   ./dev-start.sh
   ```

3. **Start Debugging in VS Code**
   - Open VS Code in the project directory
   - Go to Run and Debug (`Ctrl+Shift+D` / `Cmd+Shift+D`)
   - Select "Docker: Listen for Xdebug"
   - Click Start Debugging (`F5`)
   - Set breakpoints in your PHP files
   - Visit `http://localhost:8080` to trigger debugging

## Environment Configuration

### Required Environment Variables

Add these to your `.env` file to enable debugging:

```env
# Enable development mode
APP_ENV=development

# Xdebug Configuration
XDEBUG_MODE=debug
XDEBUG_CLIENT_HOST=host.docker.internal
XDEBUG_CLIENT_PORT=9003
XDEBUG_START_WITH_REQUEST=yes
XDEBUG_LOG_LEVEL=0
```

### Xdebug Modes

- `debug` - Step debugging (default for development)
- `develop` - Development helpers
- `coverage` - Code coverage
- `profile` - Performance profiling
- `trace` - Function trace

You can combine modes: `XDEBUG_MODE=debug,develop`

## Docker Configuration

### Development vs Production

The project uses Docker Compose overrides for development:

- **Production**: `docker-compose.yml` only
- **Development**: `docker-compose.yml` + `docker-compose.override.yml`

### Development Services

When running in development mode:

- **PHP Container**: Includes Xdebug and development dependencies
- **Xdebug Communication**: Xdebug connects FROM the container TO your IDE on port 9003
- **No Port Mapping**: Docker doesn't need to expose port 9003 - Xdebug initiates outbound connections
- **Logs**: Xdebug logs are written to `./logs/` directory
- **Path Mapping**: `/var/www/html` (container) ↔ `./` (host)

## IDE Configuration

### VS Code

The project includes pre-configured VS Code settings in `.vscode/launch.json`. Zed recognized it, but it doesn't appear to work with it, causing the request to hang:

#### "Docker: Listen for Xdebug"

- **Use this for**: Web requests and general debugging
- **Port**: 9003
- **Path Mapping**: Automatically configured
- **Auto-start**: No (manual trigger via web request)

#### "Docker: Launch currently open script"

- **Use this for**: CLI script debugging
- **Port**: 9003
- **Execution**: Runs the currently open PHP file

### PhpStorm/IntelliJ

If using PhpStorm, configure:

1. **Languages & Frameworks → PHP → Servers**
   - Name: `docker`
   - Host: `localhost`
   - Port: `8080`
   - Path mappings: `<project root>` → `/var/www/html`

2. **Languages & Frameworks → PHP → Debug**
   - Xdebug port: `9003`
   - Can accept external connections: ✓

3. **Run/Debug Configurations**
   - Type: "PHP Remote Debug"
   - Server: `docker`
   - IDE Key: `VSCODE`

## Debugging Workflow

### Web Application Debugging

1. **Start the debugger** in your IDE (it will listen on port 9003)
2. **Set breakpoints** in your PHP files
3. **Make a web request**
4. **Xdebug connects** to your IDE and the debug session starts automatically

### CLI Script Debugging

1. **Access the PHP container**:

   ```bash
   docker-compose exec php bash
   ```

2. **Run PHP script with debugging**:
   ```bash
   php -dxdebug.start_with_request=yes /var/www/html/your-script.php
   ```

### Cron Job Debugging

Cron jobs run in a separate container but can also be debugged:

1. **Check cron logs**:

   ```bash
   docker-compose logs -f cron
   ```

2. **Manually run cron job with debugging**:
   ```bash
   docker-compose exec cron php -dxdebug.start_with_request=yes /var/www/html/cron/your-job.php
   ```

## Troubleshooting

### Common Issues

#### Xdebug Not Connecting

1. **Check if Xdebug is installed**:

   ```bash
   docker-compose exec php php -m | grep xdebug
   ```

2. **Verify Xdebug configuration**:

   ```bash
   docker-compose exec php php -i | grep xdebug
   ```

3. **Check network connectivity**:
   - Ensure port 9003 is not blocked by firewall on your host machine
   - Your IDE must be listening on port 9003 (not the Docker container)
   - Try `XDEBUG_CLIENT_HOST=172.17.0.1` on Linux systems instead of `host.docker.internal`
   - If port 9003 is in use by another application, change both IDE and Docker config to use an alternative port
   - Check that no other Xdebug instances are trying to connect to the same port

#### Breakpoints Not Hit

1. **Verify path mappings** in your IDE
2. **Check file permissions** in the logs directory
3. **Ensure `XDEBUG_START_WITH_REQUEST=yes`** is set

#### Performance Issues

1. **Disable Xdebug in production**:

   ```env
   APP_ENV=production
   # or
   XDEBUG_MODE=off
   ```

2. **Use specific Xdebug modes**:
   ```env
   XDEBUG_MODE=debug  # Only enable what you need
   ```

### Debugging Logs

#### Xdebug Logs

- **Location**: `./logs/xdebug.log`
- **Enable verbose logging**:
  ```env
  XDEBUG_LOG_LEVEL=7
  ```

#### PHP Error Logs

- **Container location**: `/var/log/php_errors.log`
- **View logs**:
  ```bash
  docker-compose exec php tail -f /var/log/php_errors.log
  ```

#### Application Logs

- **Container logs**:
  ```bash
  docker-compose logs -f php
  docker-compose logs -f nginx
  ```

## Advanced Configuration

### Custom Xdebug Settings

You can add custom Xdebug settings by modifying the entrypoint script or creating additional configuration files:

```bash
# In container
echo "xdebug.max_nesting_level=512" >> /usr/local/etc/php/conf.d/xdebug.ini
```

### Remote Debugging

For debugging from a remote machine, update your `.env`:

```env
XDEBUG_CLIENT_HOST=your-remote-ip
```

### Selective Debugging

You can enable Xdebug only when needed:

```bash
# Start without debugging
docker-compose up -d

# Enable debugging for specific request
docker-compose exec php bash -c 'export XDEBUG_MODE=debug && php your-script.php'
```

## Useful Commands

### Container Management

```bash
# Start development environment
./dev-start.sh

# Stop all services
docker-compose down

# Restart just PHP service
docker-compose restart php

# Access PHP container
docker-compose exec php bash

# View service status
docker-compose ps
```

### Debugging Commands

```bash
# Check Xdebug status
docker-compose exec php php -m | grep xdebug

# View Xdebug configuration
docker-compose exec php php -i | grep xdebug

# Test PHP syntax
docker-compose exec php php -l /var/www/html/www/index.php

# Run Composer commands
docker-compose exec php composer install
docker-compose exec php composer update
```

### Log Monitoring

```bash
# Follow all logs
docker-compose logs -f

# Follow specific service logs
docker-compose logs -f php
docker-compose logs -f nginx

# View Xdebug logs
tail -f ./logs/xdebug.log
```

## Support

If you encounter issues with the debugging setup:

1. Check the logs in `./logs/`
2. Verify your IDE configuration
3. Ensure Docker containers are running properly
4. Test with a simple PHP file first

For more information, see:

- [Xdebug Documentation](https://xdebug.org/docs/)
- [VS Code PHP Debugging](https://code.visualstudio.com/docs/languages/php#_debugging)
- [Docker Compose Override](https://docs.docker.com/compose/extends/)
