module.exports = {
  apps: [{
    name: 'quiz-websocket',
    script: 'php',
    args: 'server.php',
    cwd: '/var/www/html/worshipteam', // UPDATE THIS PATH!
    interpreter: 'php',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '500M',
    error_file: './logs/error.log',
    out_file: './logs/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    merge_logs: true,
    env: {
      NODE_ENV: 'production'
    }
  }]
};

