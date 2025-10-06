const express = require('express');
const router = express.Router();

// Get system health status
router.get('/health', async (req, res) => {
  try {
    const { healthCheck } = require('../src/database');
    
    const dbHealth = await healthCheck();
    const deviceHealth = await req.homeWizardAPI.healthCheck();
    const serviceHealth = await req.dataCollectionService.healthCheck();
    
    const overallHealth = dbHealth.status === 'healthy' && 
                         deviceHealth.status === 'healthy' && 
                         serviceHealth.status === 'healthy';

    res.status(overallHealth ? 200 : 503).json({
      status: overallHealth ? 'healthy' : 'unhealthy',
      timestamp: new Date(),
      services: {
        database: dbHealth,
        device: deviceHealth,
        dataCollection: serviceHealth
      }
    });
  } catch (error) {
    res.status(503).json({
      status: 'unhealthy',
      error: error.message,
      timestamp: new Date()
    });
  }
});

// Get data collection statistics
router.get('/collection/stats', (req, res) => {
  try {
    const stats = req.dataCollectionService.getStats();
    res.json({
      success: true,
      data: stats
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

// Trigger manual data collection
router.post('/collection/trigger', async (req, res) => {
  try {
    await req.dataCollectionService.triggerCollection();
    res.json({
      success: true,
      message: 'Data collection triggered successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message },
      message: 'Failed to trigger data collection'
    });
  }
});

// Get system information
router.get('/info', (req, res) => {
  const { config } = require('../src/config');
  
  res.json({
    success: true,
    data: {
      version: process.env.npm_package_version || '2.0.0',
      environment: config.server.environment,
      uptime: process.uptime(),
      memory: process.memoryUsage(),
      platform: process.platform,
      nodeVersion: process.version,
      configuration: {
        port: config.server.port,
        deviceUrl: config.device.url,
        collectionInterval: config.collection.interval,
        retentionDays: config.collection.retentionDays,
        database: {
          host: config.database.host,
          database: config.database.database
        }
      }
    }
  });
});

// Update system configuration
router.post('/config', (req, res) => {
  try {
    const { deviceUrl, collectionInterval, timeout } = req.body;
    let updated = [];

    // Update device URL if provided
    if (deviceUrl && deviceUrl !== req.homeWizardAPI.baseURL) {
      req.homeWizardAPI.updateDeviceURL(deviceUrl);
      req.dataCollectionService.updateDeviceURL(deviceUrl);
      updated.push(`deviceUrl: ${deviceUrl}`);
    }

    // Update timeout if provided
    if (timeout && timeout !== req.homeWizardAPI.timeout) {
      req.homeWizardAPI.updateTimeout(timeout);
      updated.push(`timeout: ${timeout}ms`);
    }

    // Note: Collection interval requires service restart to take effect
    if (collectionInterval) {
      updated.push(`collectionInterval: ${collectionInterval}ms (requires restart)`);
    }

    res.json({
      success: true,
      message: updated.length > 0 ? `Updated: ${updated.join(', ')}` : 'No changes made',
      updated: updated
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

// Get database statistics
router.get('/database/stats', async (req, res) => {
  try {
    const { query } = require('../src/database');
    
    // Get table statistics
    const tableStats = await query(`
      SELECT 
        table_name,
        table_rows,
        data_length,
        index_length,
        (data_length + index_length) as total_size
      FROM information_schema.tables 
      WHERE table_schema = ? 
        AND table_name IN ('power_usage_stats', 'device_info')
    `, [process.env.DB_NAME]);

    // Get recent activity
    const recentActivity = await query(`
      SELECT 
        COUNT(*) as total_records,
        MIN(timestamp) as oldest_record,
        MAX(timestamp) as newest_record,
        COUNT(DISTINCT DATE(timestamp)) as days_with_data
      FROM power_usage_stats
    `);

    res.json({
      success: true,
      data: {
        tables: tableStats,
        activity: recentActivity[0] || {},
        timestamp: new Date()
      }
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

module.exports = router;