const cron = require('node-cron');
const HomeWizardAPI = require('./homewizard-api');
const { savePowerStats, saveDeviceInfo, cleanupOldStats } = require('./database');
const { config } = require('./config');

class DataCollectionService {
  constructor() {
    this.homeWizardAPI = new HomeWizardAPI();
    this.isRunning = false;
    this.intervalId = null;
    this.cronJobs = [];
    this.stats = {
      collectionsAttempted: 0,
      collectionsSuccessful: 0,
      lastCollection: null,
      lastError: null
    };
  }

  // Start the data collection service
  start() {
    if (this.isRunning) {
      console.log('⚠️  Data collection service is already running');
      return;
    }

    // Skip data collection in development environment
    if (config.server.environment === 'development') {
      console.log('🚫 Skipping data collection - NODE_ENV is set to development');
      console.log('   Device control and monitoring APIs remain available');
      this.isRunning = false; // Keep service marked as not running
      return;
    }

    console.log(`🚀 Starting data collection service (interval: ${config.collection.interval}ms)`);
    
    // Start periodic data collection
    this.startPeriodicCollection();
    
    // Schedule cleanup job (daily at 2:30 AM)
    this.scheduleCleanupJob();
    
    this.isRunning = true;
    console.log('✅ Data collection service started');
  }

  // Stop the data collection service
  stop() {
    if (!this.isRunning) {
      console.log('⚠️  Data collection service is not running');
      return;
    }

    console.log('🛑 Stopping data collection service...');
    
    // Clear interval
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }

    // Stop all cron jobs
    this.cronJobs.forEach(job => job.stop());
    this.cronJobs = [];
    
    this.isRunning = false;
    console.log('✅ Data collection service stopped');
  }

  // Start periodic data collection
  startPeriodicCollection() {
    // Initial collection
    this.collectData();
    
    // Set up interval for regular collection
    this.intervalId = setInterval(() => {
      this.collectData();
    }, config.collection.interval);
  }

  // Collect data from HomeWizard device
  async collectData() {
    this.stats.collectionsAttempted++;
    
    try {
      console.log('📊 Collecting power usage data...');
      
      // Get full device status
      const status = await this.homeWizardAPI.getFullStatus();
      
      if (!status.success) {
        throw new Error('Failed to get device status');
      }

      // Save device info if available
      if (status.info) {
        await this.saveDeviceInfo(status.info);
      }

      // Save power stats if measurement data is available
      if (status.data) {
        await this.saveMeasurementData(status.data, status.state);
      }

      this.stats.collectionsSuccessful++;
      this.stats.lastCollection = new Date();
      this.stats.lastError = null;
      
      console.log(`✅ Data collection successful (${this.stats.collectionsSuccessful}/${this.stats.collectionsAttempted})`);
      
    } catch (error) {
      this.stats.lastError = {
        timestamp: new Date(),
        message: error.message,
        details: error
      };
      
      console.error('❌ Data collection failed:', error.message);
      
      // Don't throw error to prevent service from crashing
      // Just log and continue with next collection cycle
    }
  }

  // Save device information to database
  async saveDeviceInfo(deviceInfo) {
    const info = {
      device_id: 'default',
      product_name: deviceInfo.product_name,
      serial: deviceInfo.serial,
      firmware_version: deviceInfo.firmware_version,
      api_version: deviceInfo.api_version
    };

    await saveDeviceInfo(info);
  }

  // Save measurement data to database
  async saveMeasurementData(measurementData, stateData = {}) {
    const powerStats = {
      device_id: 'default',
      active_power_w: measurementData.active_power_w,
      voltage_v: measurementData.voltage_v,
      current_a: measurementData.current_a,
      frequency_hz: measurementData.frequency_hz,
      total_energy_import_kwh: measurementData.total_energy_import_kwh,
      power_on: stateData?.power_on || false,
      brightness: stateData?.brightness || null,
      switch_lock: stateData?.switch_lock || false
    };

    await savePowerStats(powerStats);
  }

  // Schedule daily cleanup job
  scheduleCleanupJob() {
    // Run cleanup daily at 2:30 AM
    const cleanupJob = cron.schedule('30 2 * * *', async () => {
      try {
        console.log('🧹 Starting daily cleanup of old statistics...');
        await cleanupOldStats(config.collection.retentionDays);
        console.log('✅ Daily cleanup completed');
      } catch (error) {
        console.error('❌ Daily cleanup failed:', error);
      }
    }, {
      scheduled: false,
      timezone: 'Europe/Berlin'
    });

    cleanupJob.start();
    this.cronJobs.push(cleanupJob);
    console.log(`📅 Scheduled daily cleanup job (retention: ${config.collection.retentionDays} days)`);
  }

  // Manual trigger for data collection (useful for testing/debugging)
  async triggerCollection() {
    if (config.server.environment === 'development') {
      console.log('🚫 Manual data collection skipped - NODE_ENV is set to development');
      return;
    }
    console.log('🔄 Manual data collection triggered');
    await this.collectData();
  }

  // Get collection statistics
  getStats() {
    const successRate = this.stats.collectionsAttempted > 0 
      ? (this.stats.collectionsSuccessful / this.stats.collectionsAttempted * 100).toFixed(2)
      : 0;

    const baseStats = {
      isRunning: this.isRunning,
      interval: config.collection.interval,
      retentionDays: config.collection.retentionDays,
      collectionsAttempted: this.stats.collectionsAttempted,
      collectionsSuccessful: this.stats.collectionsSuccessful,
      successRate: `${successRate}%`,
      lastCollection: this.stats.lastCollection,
      lastError: this.stats.lastError,
      nextCollection: this.isRunning && this.stats.lastCollection 
        ? new Date(this.stats.lastCollection.getTime() + config.collection.interval)
        : null
    };

    // Add development mode note if applicable
    if (config.server.environment === 'development') {
      baseStats.environment = 'development';
      baseStats.note = 'Data collection disabled in development environment';
    }

    return baseStats;
  }

  // Health check for the service
  async healthCheck() {
    const deviceHealth = await this.homeWizardAPI.healthCheck();
    const stats = this.getStats();
    
    // In development mode, service is considered healthy if device is accessible
    if (config.server.environment === 'development') {
      return {
        status: deviceHealth.status === 'healthy' ? 'healthy' : 'unhealthy',
        timestamp: new Date(),
        service: {
          isRunning: false,
          stats: stats,
          note: 'Data collection disabled in development environment'
        },
        device: deviceHealth
      };
    }
    
    // Service is healthy if:
    // 1. It's running
    // 2. Recent collections have been successful (> 50% success rate)
    // 3. Device is accessible
    const isHealthy = this.isRunning && 
      (stats.collectionsAttempted === 0 || parseFloat(stats.successRate) > 50) &&
      deviceHealth.status === 'healthy';

    return {
      status: isHealthy ? 'healthy' : 'unhealthy',
      timestamp: new Date(),
      service: {
        isRunning: this.isRunning,
        stats: stats
      },
      device: deviceHealth
    };
  }

  // Update HomeWizard device URL
  updateDeviceURL(newURL) {
    this.homeWizardAPI.updateDeviceURL(newURL);
    console.log(`🔄 Updated device URL to: ${newURL}`);
  }
}

module.exports = DataCollectionService;