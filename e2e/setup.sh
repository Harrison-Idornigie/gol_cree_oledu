#!/bin/bash

# E2E Test Setup Script
# This script sets up the E2E testing environment for the multi-tenant Laravel application

set -e

echo "🚀 Setting up E2E testing environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if we're in the e2e directory
if [ ! -f "package.json" ]; then
    print_error "Please run this script from the e2e directory"
    exit 1
fi

# Check Node.js version
NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    print_error "Node.js 18 or higher is required. Current version: $(node --version)"
    exit 1
fi
print_status "Node.js version check passed: $(node --version)"

# Install E2E test dependencies
echo "📦 Installing E2E test dependencies..."
npm install
print_status "E2E dependencies installed"

# Install Playwright browsers
echo "🌐 Installing Playwright browsers..."
npx playwright install
print_status "Playwright browsers installed"

# Check if backend directory exists
if [ ! -d "../backend" ]; then
    print_error "Backend directory not found. Please ensure the Laravel backend is in ../backend"
    exit 1
fi

# Check if frontend directory exists
if [ ! -d "../frontend" ]; then
    print_error "Frontend directory not found. Please ensure the Next.js frontend is in ../frontend"
    exit 1
fi

# Setup backend for testing
echo "🔧 Setting up backend for testing..."
cd ../backend

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install Composer first."
    exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed. Please install PHP 8.2 or higher."
    exit 1
fi

# Install backend dependencies if needed
if [ ! -d "vendor" ]; then
    echo "📦 Installing backend dependencies..."
    composer install
    print_status "Backend dependencies installed"
fi

# Setup test environment file
if [ ! -f ".env.testing" ]; then
    echo "📝 Creating test environment file..."
    cp .env.example .env.testing
    
    # Update test environment settings
    sed -i.bak 's/APP_ENV=local/APP_ENV=testing/' .env.testing
    sed -i.bak 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env.testing
    sed -i.bak 's/DB_DATABASE=laravel/DB_DATABASE=:memory:/' .env.testing
    rm .env.testing.bak 2>/dev/null || true
    
    print_status "Test environment file created"
else
    print_status "Test environment file already exists"
fi

# Clear config cache
php artisan config:clear --env=testing
print_status "Configuration cache cleared"

# Run migrations for testing
echo "🗄️  Setting up test database..."
php artisan migrate:fresh --env=testing --force
print_status "Test database migrations completed"

# Seed basic data
if [ -f "database/seeders/RoleSeeder.php" ]; then
    php artisan db:seed --class=RoleSeeder --env=testing --force
    print_status "Basic test data seeded"
fi

# Return to e2e directory
cd ../e2e

# Setup frontend for testing
echo "🔧 Setting up frontend for testing..."
cd ../frontend

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed. Please install Node.js and npm first."
    exit 1
fi

# Install frontend dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "📦 Installing frontend dependencies..."
    npm install
    print_status "Frontend dependencies installed"
fi

# Return to e2e directory
cd ../e2e

# Create local environment file if it doesn't exist
if [ ! -f ".env.test.local" ]; then
    echo "📝 Creating local test environment file..."
    cp .env.test .env.test.local
    print_status "Local test environment file created"
    print_warning "Please review and customize .env.test.local as needed"
else
    print_status "Local test environment file already exists"
fi

# Verify setup
echo "🔍 Verifying setup..."

# Check if backend can start
echo "Checking backend accessibility..."
cd ../backend
timeout 10s php artisan serve --port=8001 &
SERVER_PID=$!
sleep 3

if curl -s http://localhost:8001/up > /dev/null; then
    print_status "Backend is accessible"
else
    print_warning "Backend health check failed - make sure to start it with 'php artisan serve'"
fi

# Kill test server
kill $SERVER_PID 2>/dev/null || true
cd ../e2e

# Final instructions
echo ""
echo "🎉 E2E testing environment setup completed!"
echo ""
echo "Next steps:"
echo "1. Start the backend server:"
echo "   cd ../backend && php artisan serve"
echo ""
echo "2. Start the frontend server:"
echo "   cd ../frontend && npm run dev"
echo ""
echo "3. Run the E2E tests:"
echo "   npm run test"
echo ""
echo "4. Or run tests with UI:"
echo "   npm run test:ui"
echo ""
echo "For more information, see the README.md file."
echo ""
print_status "Setup complete! Happy testing! 🚀"
