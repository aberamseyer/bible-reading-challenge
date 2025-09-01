#!/bin/bash

# Development startup script for Bible Reading Challenge with PHP debugging
# This script sets up the development environment with Xdebug enabled

set -e

echo "🚀 Starting Bible Reading Challenge in Development Mode with Debugging..."

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "❌ .env file not found!"
    echo "📋 Please copy .env.debug.example to .env and configure your environment:"
    echo "   cp .env.debug.example .env"
    echo "   # Then edit .env with your specific configuration"
    exit 1
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Set development environment variables
export COMPOSE_FILE=docker-compose.yml:docker-compose.override.yml
export APP_ENV=development

# Create logs directory if it doesn't exist
mkdir -p logs
chmod 777 logs

echo "📁 Project structure:"
echo "   - Docker Compose files: docker-compose.yml + docker-compose.override.yml"
echo "   - Environment: Development mode with Xdebug enabled"
echo "   - Logs directory: ./logs"

# Pull latest images (optional, comment out if not needed)
echo "🔄 Pulling latest Docker images..."
docker-compose pull --quiet

# Build the containers
echo "🏗️  Building Docker containers..."
docker-compose build --parallel

# Start the services
echo "▶️  Starting services..."
docker-compose up -d

# Wait for services to be ready
echo "⏳ Waiting for services to start..."
sleep 5

# Check service status
echo "📊 Service Status:"
docker-compose ps

# Display connection information
echo ""
echo "🌐 Service URLs:"
echo "   - Application: http://localhost:8080"
echo "   - Socket Server: ws://localhost:8085"
echo ""
echo "🐛 Debugging Information:"
echo "   - Xdebug connects TO your IDE on port: 9003"
echo "   - Your IDE must LISTEN on port: 9003"
echo "   - IDE Key: VSCODE"
echo "   - Path Mapping: /var/www/html -> $(pwd)"
echo "   - Xdebug Logs: ./logs/xdebug.log"
echo "   - Communication Flow: Container -> Host (no Docker port mapping needed)"
echo ""
echo "🔧 VS Code Debugging:"
echo "   1. Open VS Code in this directory"
echo "   2. Go to Run and Debug (Ctrl+Shift+D)"
echo "   3. Select 'Docker: Listen for Xdebug'"
echo "   4. Click Start Debugging (F5) - VS Code will listen on port 9003"
echo "   5. Set breakpoints in your PHP code"
echo "   6. Visit http://localhost:8080 to trigger debugging"
echo "   7. Xdebug will connect from the container to VS Code"
echo ""
echo "📝 Useful Commands:"
echo "   - View logs: docker-compose logs -f"
echo "   - Stop services: docker-compose down"
echo "   - Restart PHP: docker-compose restart php"
echo "   - Access PHP container: docker-compose exec php bash"
echo ""

# Check if Xdebug is properly configured
echo "🔍 Checking Xdebug configuration..."
if docker-compose exec -T php php -m | grep -q xdebug; then
    echo "✅ Xdebug is installed and enabled"

    # Show Xdebug configuration
    echo "🔧 Xdebug Configuration:"
    docker-compose exec -T php php -i | grep xdebug.mode || echo "   Mode: (checking...)"
    docker-compose exec -T php php -i | grep xdebug.client_host || echo "   Client Host: (checking...)"
    docker-compose exec -T php php -i | grep xdebug.client_port || echo "   Client Port: (checking...)"
else
    echo "⚠️  Xdebug not found. Check Docker build logs."
fi

echo ""
echo "🎉 Development environment is ready!"
echo "💡 Tip: Use 'docker-compose logs -f php' to monitor PHP and Xdebug logs"
