module.exports = {
  apps: [
    {
      // =========================
      // Laravel Queue Worker
      // =========================
      name: 'sdpos-queue',

      script: 'artisan',
      args: 'queue:work database --queue=default --sleep=3 --tries=3 --timeout=90 --max-jobs=100 --max-time=3600',

      interpreter: 'php',
      cwd: '/var/www/sdpos', // 🔒 Explicit path = safer than process.cwd()

      exec_mode: 'fork',
      instances: 1, // ⚠️ Never cluster queue workers unless intentional

      autorestart: true,
      watch: false,

      max_memory_restart: '256M', // 🧠 Restart on memory leaks

      error_file: '/var/www/sdpos/storage/logs/pm2-queue-error.log',
      out_file: '/var/www/sdpos/storage/logs/pm2-queue-out.log',
      log_file: '/var/www/sdpos/storage/logs/pm2-queue.log',

      time: true,
      merge_logs: true,

      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false'
      }
    },

    // {
    //   // =========================
    //   // API Requests Queue (burst)
    //   // =========================
    //   name: 'sdpos-queue-api',

    //   script: 'artisan',
    //   args: 'queue:work database --queue=api-requests --sleep=1 --timeout=120 --tries=1 --max-jobs=500 --max-time=1800',

    //   interpreter: 'php',
    //   cwd: '/var/www/sdpos',

    //   exec_mode: 'fork',
    //   instances: 1,

    //   autorestart: true,
    //   watch: false,

    //   max_memory_restart: '256M',

    //   error_file: '/var/www/sdpos/storage/logs/pm2-queue-api-error.log',
    //   out_file: '/var/www/sdpos/storage/logs/pm2-queue-api-out.log',
    //   log_file: '/var/www/sdpos/storage/logs/pm2-queue-api.log',

    //   time: true,
    //   merge_logs: true,

    //   env: {
    //     APP_ENV: 'production',
    //     APP_DEBUG: 'false'
    //   }
    // },

    {
      // =========================
      // Laravel Scheduler
      // =========================
      name: 'sdpos-scheduler',

      script: 'artisan',
      args: 'schedule:work',

      interpreter: 'php',
      cwd: '/var/www/sdpos',

      exec_mode: 'fork',
      instances: 1,

      autorestart: true,
      watch: false,

      error_file: '/var/www/sdpos/storage/logs/pm2-scheduler-error.log',
      out_file: '/var/www/sdpos/storage/logs/pm2-scheduler-out.log',
      log_file: '/var/www/sdpos/storage/logs/pm2-scheduler.log',

      time: true,
      merge_logs: true,

      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false'
      }
    }
  ]
};
