const path = require('path');

// Base path: use PM2_APP_BASE or project root (where ecosystem.config.js lives).
// Production (e.g. Linux): set PM2_APP_BASE=/var/www/sdpos when starting PM2.
// Dev (Windows): omit; uses __dirname (project root).
const basePath = process.env.PM2_APP_BASE || __dirname;
const logDir = path.join(basePath, 'storage', 'logs');

module.exports = {
  apps: [
    {
      // =========================
      // Laravel Queue Worker (single process, both queues)
      // =========================
      // Processes api-requests first, then default. One worker = one DB connection.
      // Avoids separate api worker (see docs/DB_CONNECTION_ANALYSIS_LAST_WEEK.md).
      name: 'sdpos-queue',

      script: 'artisan',
      args: 'queue:work database --queue=api-requests,default --sleep=3 --tries=3 --timeout=90 --max-jobs=100 --max-time=3600',

      interpreter: 'php',
      cwd: basePath,

      exec_mode: 'fork',
      instances: 1,

      autorestart: true,
      watch: false,
      max_memory_restart: '256M',

      error_file: path.join(logDir, 'pm2-queue-error.log'),
      out_file: path.join(logDir, 'pm2-queue-out.log'),
      log_file: path.join(logDir, 'pm2-queue.log'),
      time: true,
      merge_logs: true,

      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false'
      }
    },

    {
      // =========================
      // Laravel Scheduler
      // =========================
      name: 'sdpos-scheduler',

      script: 'artisan',
      args: 'schedule:work',

      interpreter: 'php',
      cwd: basePath,

      exec_mode: 'fork',
      instances: 1,

      autorestart: true,
      watch: false,
      max_memory_restart: '256M',

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
