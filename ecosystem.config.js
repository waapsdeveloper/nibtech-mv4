module.exports = {
  apps: [{
    name: 'sdpos-queue',
    script: 'artisan',
    args: 'queue:work --tries=3 --timeout=300',
    interpreter: 'php',
    cwd: process.cwd(), // Use current working directory (works on any server)
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '500M',
    error_file: './storage/logs/pm2-error.log',
    out_file: './storage/logs/pm2-out.log',
    log_file: './storage/logs/pm2-combined.log',
    time: true,
    merge_logs: true,
    env: {
      APP_ENV: 'production'
    },
    env_development: {
      APP_ENV: 'local'
    },
    env_staging: {
      APP_ENV: 'staging'
    }
  }]
};

