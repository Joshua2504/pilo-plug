const mariadb = require('mariadb');
const { config } = require('./config');

let pool = null;

// Initialize database connection pool
function initializeDatabase() {
  try {
    pool = mariadb.createPool({
      host: config.database.host,
      port: config.database.port,
      user: config.database.user,
      password: config.database.password,
      database: config.database.database,
      connectionLimit: config.database.connectionLimit,
      acquireTimeout: config.database.acquireTimeout,
      timeout: config.database.timeout
    });

    console.log('Database connection pool created');
    return pool;
  } catch (error) {
    console.error('Error creating database pool:', error);
    throw error;
  }
}

// Get database connection from pool
async function getConnection() {
  if (!pool) {
    throw new Error('Database not initialized. Call initializeDatabase() first.');
  }
  return await pool.getConnection();
}

// Execute a query
async function query(sql, params = []) {
  let conn;
  try {
    conn = await getConnection();
    const result = await conn.query(sql, params);
    return result;
  } catch (error) {
    console.error('Database query error:', error);
    throw error;
  } finally {
    if (conn) conn.release();
  }
}

// Save power usage statistics
async function savePowerStats(data) {
  const sql = `
    INSERT INTO power_usage_stats (
      timestamp, device_id, active_power_w, voltage_v, current_a, 
      frequency_hz, total_energy_import_kwh, power_on, brightness, switch_lock
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `;
  
  const params = [
    new Date(),
    data.device_id || 'default',
    data.active_power_w || null,
    data.voltage_v || null, 
    data.current_a || null,
    data.frequency_hz || null,
    data.total_energy_import_kwh || null,
    data.power_on ? 1 : 0,
    data.brightness || null,
    data.switch_lock ? 1 : 0
  ];

  return await query(sql, params);
}

// Get recent power statistics 
async function getRecentStats(hours = 24) {
  const sql = `
    SELECT * FROM power_usage_stats 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY timestamp DESC
    LIMIT 1000
  `;
  
  return await query(sql, [hours]);
}

// Get hourly aggregated statistics
async function getHourlyStats(days = 7) {
  const sql = `
    SELECT 
      DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as hour,
      AVG(active_power_w) as avg_power,
      MIN(active_power_w) as min_power,
      MAX(active_power_w) as max_power,
      COUNT(*) as sample_count
    FROM power_usage_stats 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
      AND active_power_w IS NOT NULL
    GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d %H')
    ORDER BY hour DESC
  `;
  
  return await query(sql, [days]);
}

// Save device information
async function saveDeviceInfo(deviceInfo) {
  const sql = `
    INSERT INTO device_info (
      device_id, product_name, serial, firmware_version, api_version, last_seen
    ) VALUES (?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      product_name = VALUES(product_name),
      serial = VALUES(serial),
      firmware_version = VALUES(firmware_version), 
      api_version = VALUES(api_version),
      last_seen = NOW()
  `;
  
  const params = [
    deviceInfo.device_id || 'default',
    deviceInfo.product_name,
    deviceInfo.serial,
    deviceInfo.firmware_version,
    deviceInfo.api_version
  ];

  return await query(sql, params);
}

// Clean up old statistics
async function cleanupOldStats(retentionDays = 90) {
  const sql = `
    DELETE FROM power_usage_stats 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
  `;
  
  const result = await query(sql, [retentionDays]);
  console.log(`Cleaned up ${result.affectedRows} old statistics records`);
  return result;
}

// Close database connection pool
async function closeDatabase() {
  if (pool) {
    await pool.end();
    pool = null;
    console.log('Database connection pool closed');
  }
}

// Health check for database
async function healthCheck() {
  try {
    await query('SELECT 1');
    return { status: 'healthy', timestamp: new Date() };
  } catch (error) {
    return { status: 'unhealthy', error: error.message, timestamp: new Date() };
  }
}

module.exports = {
  initializeDatabase,
  getConnection,
  query,
  savePowerStats,
  getRecentStats,
  getHourlyStats,
  saveDeviceInfo,
  cleanupOldStats,
  closeDatabase,
  healthCheck
};