#!/bin/bash
set -e

echo "🚀 Setting up multi-component Laravel + Next.js + E2E testing environment..."

# Update system packages
sudo apt-get update -y

# Install PHP 8.2 and required extensions
echo "📦 Installing PHP 8.2 and extensions..."
sudo apt-get install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update -y
sudo apt-get install -y \
    php8.2 \
    php8.2-cli \
    php8.2-common \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-mysql \
    php8.2-xml \
    php8.2-mbstring \
    php8.2-sqlite3 \
    php8.2-intl \
    php8.2-bcmath \
    php8.2-soap \
    php8.2-xdebug \
    unzip \
    curl

# Install Composer
echo "📦 Installing Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

# Install Node.js 18.x
echo "📦 Installing Node.js 18.x..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

# Add executables to PATH
echo 'export PATH="/usr/local/bin:$PATH"' >> $HOME/.profile
source $HOME/.profile

echo "🔧 Setting up Backend (Laravel)..."
cd backend

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# Setup environment file
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
fi

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate --ansi

# Create SQLite database file
echo "🗄️ Creating SQLite database..."
touch database/database.sqlite
touch database/testing.sqlite

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force --no-interaction

# Install Node.js dependencies for backend
echo "📦 Installing backend Node.js dependencies..."
npm install

# Fix the PHP method signature conflict
echo "🔧 Fixing PHP method signature conflict..."
sed -i 's/public function validate(Request $request, CurriculumTemplate $template): JsonResponse/public function validateTemplate(Request $request, CurriculumTemplate $template): JsonResponse/g' app/Http/Controllers/API/Tenant/Team/TeamCurriculumTemplateController.php

echo "🔧 Setting up Frontend (Next.js)..."
cd ../frontend

# Install frontend dependencies with legacy peer deps to resolve React 19 conflicts
echo "📦 Installing frontend dependencies with legacy peer deps..."
npm install --legacy-peer-deps

echo "🔧 Setting up E2E tests..."
cd ../e2e

# Install E2E dependencies
echo "📦 Installing E2E dependencies..."
npm install

# Install Playwright browsers with dependencies
echo "🌐 Installing Playwright browsers with dependencies..."
npx playwright install --with-deps chromium

# Setup E2E environment
if [ ! -f .env.test.local ]; then
    echo "📝 Creating E2E test environment..."
    cp .env.test .env.test.local
fi

echo "✅ Setup completed successfully!"
echo "📍 Current working directory: $(pwd)"
echo "🧪 Ready to run tests..."