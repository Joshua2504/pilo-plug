const { initializeDatabase, query, closeDatabase } = require('../src/database');
const { validateConfig } = require('../src/config');

// SQL to create tables
const createTables = {
  power_usage_stats: `
    CREATE TABLE IF NOT EXISTS power_usage_stats (
      id INT AUTO_INCREMENT PRIMARY KEY,
      timestamp DATETIME NOT NULL,
      device_id VARCHAR(50) NOT NULL DEFAULT 'default',
      active_power_w DECIMAL(10,3) NULL,
      voltage_v DECIMAL(10,3) NULL,
      current_a DECIMAL(10,6) NULL,
      frequency_hz DECIMAL(10,3) NULL,
      total_energy_import_kwh DECIMAL(15,6) NULL,
      power_on BOOLEAN NOT NULL DEFAULT FALSE,
      brightness TINYINT UNSIGNED NULL,
      switch_lock BOOLEAN NOT NULL DEFAULT FALSE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_timestamp (timestamp),
      INDEX idx_device_id (device_id),
      INDEX idx_power_timestamp (active_power_w, timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  `,

  device_info: `
    CREATE TABLE IF NOT EXISTS device_info (
      id INT AUTO_INCREMENT PRIMARY KEY,
      device_id VARCHAR(50) NOT NULL UNIQUE,
      product_name VARCHAR(100) NULL,
      serial VARCHAR(50) NULL,
      firmware_version VARCHAR(20) NULL,
      api_version VARCHAR(10) NULL,
      last_seen DATETIME NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_device_id (device_id),
      INDEX idx_last_seen (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  `
};

async function initializeSchema() {
  console.log('🔧 Initializing database schema...');
  
  try {
    // Validate configuration
    validateConfig();
    console.log('✅ Configuration validated');

    // Initialize database connection
    initializeDatabase();
    console.log('✅ Database connection established');

    // Create tables
    for (const [tableName, createSQL] of Object.entries(createTables)) {
      console.log(`📝 Creating table: ${tableName}`);
      await query(createSQL);
      console.log(`✅ Table ${tableName} created/verified`);
    }

    // Verify tables were created
    const tables = await query('SHOW TABLES');
    console.log('📊 Available tables:');
    tables.forEach(table => {
      const tableName = Object.values(table)[0];
      console.log(`   - ${tableName}`);
    });

    console.log('🎉 Database initialization completed successfully!');
    
  } catch (error) {
    console.error('❌ Database initialization failed:', error);
    process.exit(1);
  } finally {
    await closeDatabase();
  }
}

// Run initialization if this script is executed directly
if (require.main === module) {
  initializeSchema();
}

module.exports = { initializeSchema };