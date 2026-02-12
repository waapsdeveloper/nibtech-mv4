const path = require('path');

// Base path: use PM2_APP_BASE or project root (where ecosystem.config.js lives).
// Production: export PM2_APP_BASE=/var/www/sdpos
// Dev: omit (uses __dirname)
const basePath = process.env.PM2_APP_BASE || __dirname;
const logDir = path.join(basePath, 'storage', 'logs');

module.exports = {
  apps: [

    // =====================================================
    // API QUEUE WORKER (HIGH PRIORITY / FAST JOBS)
    // =====================================================
    {
      name: 'sdpos-api-queue',

      script: 'artisan',
      args: 'queue:work redis --queue=api-requests --sleep=1 --tries=3 --timeout=30 --max-jobs=500 --max-time=3600',

      interpreter: 'php',
      cwd: basePath,

      exec_mode: 'fork',
      instances: 1,

      autorestart: true,
      watch: false,
      max_memory_restart: '256M',

      error_file: path.join(logDir, 'pm2-api-error.log'),
      out_file: path.join(logDir, 'pm2-api-out.log'),
      log_file: path.join(logDir, 'pm2-api.log'),

      time: true,
      merge_logs: true,

      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false'
      }
    },

    // =====================================================
    // DEFAULT QUEUE WORKER (HEAVY / LONG JOBS)
    // =====================================================
    {
      name: 'sdpos-default-queue',

      script: 'artisan',
      args: 'queue:work redis --queue=default --sleep=3 --tries=3 --timeout=120 --max-jobs=100 --max-time=3600',

      interpreter: 'php',
      cwd: basePath,

      exec_mode: 'fork',
      instances: 1,

      autorestart: true,
      watch: false,
      max_memory_restart: '256M',

      error_file: path.join(logDir, 'pm2-default-error.log'),
      out_file: path.join(logDir, 'pm2-default-out.log'),
      log_file: path.join(logDir, 'pm2-default.log'),

      time: true,
      merge_logs: true,

      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false'
      }
    },

    // =====================================================
    // LISTINGS SYNC WORKER (functions:thirty – BackMarket listings sync, hourly)
    // Separate from default worker so heavy sync doesn't block other jobs.
    // =====================================================
    {
      name: 'sdpos-listings-sync',

      script: 'artisan',
      args: 'queue:work redis --queue=listings-sync --sleep=5 --tries=1 --timeout=7200 --max-jobs=10 --max-time=3600',

      interpreter: 'php',
      cwd: basePath,

      exec_mode: 'fork',
      instances: 1,

      autorestart: true,
      watch: false,
      max_memory_restart: '512M',

      error_file: path.join(logDir, 'pm2-listings-sync-error.log'),
      out_file: path.join(logDir, 'pm2-listings-sync-out.log'),
      log_file: path.join(logDir, 'pm2-listings-sync.log'),

      time: true,
      merge_logs: true,

      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false'
      }
    },

    // =====================================================
    // LARAVEL SCHEDULER
    // =====================================================
    {
      name: 'sdpos-scheduler',

      script: 'artisan',
      args: 'schedule:work',

      interpreter: 'php',
      cwd: basePath,

      exec_mode: 'fork',
      instances: 1,

      autorestart: true,
      watch: false,
      max_memory_restart: '128M',

      error_file: path.join(logDir, 'pm2-scheduler-error.log'),
      out_file: path.join(logDir, 'pm2-scheduler-out.log'),
      log_file: path.join(logDir, 'pm2-scheduler.log'),

      time: true,
      merge_logs: true,

      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false'
      }
    }

  ]
};
