#!/usr/bin/env node

/**
 * Test Runner for Team Word Controller E2E Tests
 * 
 * This script provides an easy way to run different categories of word tests
 * with proper environment setup and configuration.
 */

const { spawn } = require('child_process');
const path = require('path');

// Test categories and their grep patterns
const TEST_CATEGORIES = {
  'all': '',
  'auth': 'Authentication & Authorization',
  'isolation': 'Tenant Isolation', 
  'validation': 'Data Validation',
  'crud': 'Functional Testing - CRUD',
  'bulk': 'Bulk Operations',
  'translations': 'Translation Management',
  'audio': 'Audio Upload',
  'search': 'Filter & Search',
  'constraints': 'Word Constraint',
  'edge': 'Edge Cases',
  'format': 'Response Format'
};

// Parse command line arguments
const args = process.argv.slice(2);
const category = args[0] || 'all';
const debug = args.includes('--debug') || args.includes('-d');
const verbose = args.includes('--verbose') || args.includes('-v');

// Validate category
if (!TEST_CATEGORIES.hasOwnProperty(category)) {
  console.error(`❌ Invalid test category: ${category}`);
  console.log('\n📋 Available categories:');
  Object.keys(TEST_CATEGORIES).forEach(cat => {
    console.log(`   ${cat.padEnd(12)} - ${TEST_CATEGORIES[cat] || 'All tests'}`);
  });
  process.exit(1);
}

// Build command
const testFile = 'tests/team-word-controller.spec.ts';
let command = ['npx', 'playwright', 'test', testFile];

// Add grep pattern if specific category
if (category !== 'all' && TEST_CATEGORIES[category]) {
  command.push('--grep', TEST_CATEGORIES[category]);
}

// Add debug flags
if (debug) {
  command.push('--debug');
}

if (verbose) {
  command.push('--reporter=verbose');
}

// Set environment variables
const env = { ...process.env };
if (debug) {
  env.DEBUG_API_CALLS = 'true';
}

// Run the tests
console.log(`🧪 Running Team Word Controller E2E Tests`);
console.log(`📂 Category: ${category} ${TEST_CATEGORIES[category] ? `(${TEST_CATEGORIES[category]})` : ''}`);
console.log(`🔧 Debug mode: ${debug ? 'enabled' : 'disabled'}`);
console.log(`📝 Command: ${command.join(' ')}\n`);

const testProcess = spawn(command[0], command.slice(1), {
  stdio: 'inherit',
  env: env,
  cwd: __dirname
});

testProcess.on('close', (code) => {
  if (code === 0) {
    console.log('\n✅ Tests completed successfully!');
  } else {
    console.log(`\n❌ Tests failed with exit code: ${code}`);
  }
  process.exit(code);
});

testProcess.on('error', (error) => {
  console.error(`❌ Failed to start test process: ${error.message}`);
  process.exit(1);
});

// Handle Ctrl+C gracefully
process.on('SIGINT', () => {
  console.log('\n🛑 Test execution interrupted by user');
  testProcess.kill('SIGINT');
});