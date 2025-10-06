const dotenv = require('dotenv');

// Load environment variables
dotenv.config();

const config = {
  // Server configuration
  server: {
    port: parseInt(process.env.PORT) || 3000,
    environment: process.env.NODE_ENV || 'development'
  },

  // Database configuration
  database: {
    host: process.env.DB_HOST,
    port: parseInt(process.env.DB_PORT) || 3306,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    connectionLimit: 10,
    acquireTimeout: 60000,
    timeout: 60000
  },

  // HomeWizard device configuration
  device: {
    url: process.env.DEVICE_URL || 'http://172.16.0.189',
    timeout: parseInt(process.env.DEVICE_TIMEOUT) || 10000
  },

  // Data collection configuration
  collection: {
    interval: parseInt(process.env.COLLECTION_INTERVAL) || 60000, // 1 minute
    retentionDays: parseInt(process.env.STATS_RETENTION_DAYS) || 90
  },

  // Logging configuration
  logging: {
    level: process.env.LOG_LEVEL || 'info'
  }
};

// Validate required configuration
function validateConfig() {
  const required = [
    'database.host',
    'database.user', 
    'database.password',
    'database.database'
  ];

  const missing = required.filter(key => {
    const value = key.split('.').reduce((obj, prop) => obj && obj[prop], config);
    return !value;
  });

  if (missing.length > 0) {
    throw new Error(`Missing required configuration: ${missing.join(', ')}`);
  }
}

module.exports = { config, validateConfig };