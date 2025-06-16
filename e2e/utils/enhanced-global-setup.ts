/**
 * Enhanced Global Setup with Pre-Test Validation
 * 
 * Extends the existing global setup to include comprehensive
 * pre-test validation for tenant testing infrastructure.
 */

import { FullConfig } from '@playwright/test';
import { createPlaywrightIntegrator } from '../validation/integration/PlaywrightIntegrator';
import originalGlobalSetup from './global-setup';

/**
 * Enhanced global setup function
 */
async function enhancedGlobalSetup(config: FullConfig): Promise<void> {
  console.log('🚀 Starting enhanced E2E test environment setup...');
  
  try {
    // Create validation integrator
    const integrator = createPlaywrightIntegrator();
    
    // Enhance the original setup with validation
    const enhancedSetup = await integrator.enhanceGlobalSetup(originalGlobalSetup);
    
    // Execute the enhanced setup
    await enhancedSetup(config);
    
    console.log('✅ Enhanced E2E test environment setup completed successfully!');
    
  } catch (error) {
    console.error('❌ Enhanced global setup failed:', error);
    
    // Provide helpful error messages based on error type
    if (error instanceof Error) {
      if (error.message.includes('Backend health check failed')) {
        console.error('💡 Suggestion: Start Laravel server with: cd backend && php artisan serve');
      }
      
      if (error.message.includes('Database connectivity')) {
        console.error('💡 Suggestion: Check database configuration in .env.testing');
      }
      
      if (error.message.includes('seeding')) {
        console.error('💡 Suggestion: Run database seeding with: cd backend && php artisan db:seed --env=testing');
      }
    }
    
    throw error;
  }
}

export default enhancedGlobalSetup;