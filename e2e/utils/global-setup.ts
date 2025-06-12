import { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import { DatabaseHelper } from './database-helpers';

/**
 * Global setup for E2E tests
 * 
 * This runs once before all tests and prepares the testing environment:
 * - Sets up test database
 * - Runs migrations
 * - Creates necessary test data
 * - Ensures backend is ready
 */
async function globalSetup(config: FullConfig) {
  console.log('🚀 Starting E2E test environment setup...');

  try {
    // 1. Setup backend test environment
    console.log('📦 Setting up backend test environment...');

    // Ensure .env.testing exists (don't overwrite if it already exists)
    try {
      execSync('cd ../backend && test -f .env.testing', { stdio: 'pipe' });
      console.log('✅ .env.testing already exists, using existing configuration');
    } catch {
      console.log('📝 Creating .env.testing from .env.example');
      execSync('cd ../backend && cp .env.example .env.testing', { stdio: 'inherit' });
    }

    // Clear configuration cache to ensure fresh config is loaded
    execSync('cd ../backend && php artisan config:clear --env=testing', { stdio: 'inherit' });

    // Run landlord migrations and seeding for central database
    console.log('🏢 Running landlord migrations and seeding...');
    execSync('cd ../backend && echo "y" | php artisan migrate:landlord --fresh --seed --force --no-interaction --env=testing', { stdio: 'inherit' });

    // 2. Initialize database helper
    const dbHelper = new DatabaseHelper();
    await dbHelper.initialize();

    // 3. Verify backend is accessible
    console.log('🔍 Verifying backend accessibility...');
    const backendUrl = process.env.BACKEND_URL || 'http://localhost:8000';
    
    try {
      const response = await fetch(`${backendUrl}/up`);
      if (!response.ok) {
        throw new Error(`Backend health check failed: ${response.status}`);
      }
      console.log('✅ Backend is accessible');
    } catch (error) {
      console.warn('⚠️  Backend health check failed, but continuing with tests');
      console.warn('   Make sure Laravel server is running: php artisan serve');
    }

    // 4. Verify frontend is accessible
    console.log('🔍 Verifying frontend accessibility...');
    const frontendUrl = process.env.FRONTEND_URL || 'http://localhost:3000';
    
    try {
      const response = await fetch(frontendUrl);
      if (!response.ok) {
        throw new Error(`Frontend health check failed: ${response.status}`);
      }
      console.log('✅ Frontend is accessible');
    } catch (error) {
      console.warn('⚠️  Frontend health check failed, but continuing with tests');
      console.warn('   Make sure Next.js server is running: npm run dev');
    }

    console.log('✅ E2E test environment setup completed successfully!');

  } catch (error) {
    console.error('❌ Failed to setup E2E test environment:', error);
    throw error;
  }
}

export default globalSetup;
