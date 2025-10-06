const express = require('express');
const router = express.Router();
const { getRecentStats, getHourlyStats, query } = require('../src/database');

// Get recent power statistics
router.get('/recent', async (req, res) => {
  try {
    const hours = parseInt(req.query.hours) || 24;
    const stats = await getRecentStats(hours);
    
    res.json({
      success: true,
      data: stats,
      count: stats.length,
      hours: hours
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

// Get hourly aggregated statistics
router.get('/hourly', async (req, res) => {
  try {
    const days = parseInt(req.query.days) || 7;
    const stats = await getHourlyStats(days);
    
    res.json({
      success: true,
      data: stats,
      count: stats.length,
      days: days
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

// Get daily summary statistics
router.get('/daily', async (req, res) => {
  try {
    const days = parseInt(req.query.days) || 30;
    
    const sql = `
      SELECT 
        DATE(timestamp) as date,
        AVG(active_power_w) as avg_power,
        MIN(active_power_w) as min_power,
        MAX(active_power_w) as max_power,
        COUNT(*) as sample_count,
        SUM(CASE WHEN power_on = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 as uptime_percent
      FROM power_usage_stats 
      WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND active_power_w IS NOT NULL
      GROUP BY DATE(timestamp)
      ORDER BY date DESC
    `;
    
    const stats = await query(sql, [days]);
    
    res.json({
      success: true,
      data: stats,
      count: stats.length,
      days: days
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

// Get current power consumption
router.get('/current', async (req, res) => {
  try {
    const sql = `
      SELECT 
        timestamp,
        active_power_w,
        voltage_v,
        current_a,
        power_on,
        brightness
      FROM power_usage_stats 
      ORDER BY timestamp DESC 
      LIMIT 1
    `;
    
    const result = await query(sql);
    
    if (result.length > 0) {
      res.json({
        success: true,
        data: result[0]
      });
    } else {
      res.json({
        success: false,
        error: { message: 'No data available' }
      });
    }
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

// Get statistics summary
router.get('/summary', async (req, res) => {
  try {
    const period = req.query.period || 'day'; // day, week, month
    let interval;
    
    switch (period) {
      case 'week':
        interval = 7;
        break;
      case 'month':
        interval = 30;
        break;
      case 'day':
      default:
        interval = 1;
        break;
    }
    
    const sql = `
      SELECT 
        COUNT(*) as total_measurements,
        AVG(active_power_w) as avg_power,
        MIN(active_power_w) as min_power,
        MAX(active_power_w) as max_power,
        MIN(timestamp) as first_measurement,
        MAX(timestamp) as last_measurement,
        SUM(CASE WHEN power_on = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 as uptime_percent
      FROM power_usage_stats 
      WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND active_power_w IS NOT NULL
    `;
    
    const summary = await query(sql, [interval]);
    
    res.json({
      success: true,
      data: summary[0] || {},
      period: period,
      days: interval
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

// Get power usage trends
router.get('/trends', async (req, res) => {
  try {
    const days = parseInt(req.query.days) || 7;
    
    // Get hourly averages for trend analysis
    const sql = `
      SELECT 
        DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as hour,
        AVG(active_power_w) as avg_power,
        COUNT(*) as sample_count
      FROM power_usage_stats 
      WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND active_power_w IS NOT NULL
      GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d %H')
      ORDER BY hour ASC
    `;
    
    const trends = await query(sql, [days]);
    
    // Calculate basic trend indicators
    const powers = trends.map(t => t.avg_power).filter(p => p !== null);
    const avgPower = powers.length > 0 ? powers.reduce((a, b) => a + b, 0) / powers.length : 0;
    
    // Simple trend calculation (comparing first half vs second half)
    const midPoint = Math.floor(powers.length / 2);
    const firstHalf = powers.slice(0, midPoint);
    const secondHalf = powers.slice(midPoint);
    
    const firstHalfAvg = firstHalf.length > 0 ? firstHalf.reduce((a, b) => a + b, 0) / firstHalf.length : 0;
    const secondHalfAvg = secondHalf.length > 0 ? secondHalf.reduce((a, b) => a + b, 0) / secondHalf.length : 0;
    
    const trendDirection = secondHalfAvg > firstHalfAvg ? 'increasing' : 
                          secondHalfAvg < firstHalfAvg ? 'decreasing' : 'stable';
    
    res.json({
      success: true,
      data: trends,
      analysis: {
        avgPower: avgPower.toFixed(2),
        trendDirection: trendDirection,
        change: ((secondHalfAvg - firstHalfAvg) / firstHalfAvg * 100).toFixed(2) + '%'
      },
      count: trends.length,
      days: days
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: { message: error.message }
    });
  }
});

module.exports = router;