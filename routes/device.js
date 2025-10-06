const express = require('express');
const router = express.Router();

// Get device information
router.get('/info', async (req, res) => {
  try {
    const result = await req.homeWizardAPI.getDeviceInfo();
    if (result.success) {
      res.json({ success: true, data: result.data });
    } else {
      res.json({ success: false, error: result.error, url: result.url });
    }
  } catch (error) {
    res.status(500).json({ success: false, error: { message: error.message } });
  }
});

// Get current measurements
router.get('/data', async (req, res) => {
  try {
    const result = await req.homeWizardAPI.getCurrentData();
    if (result.success) {
      res.json({ success: true, data: result.data });
    } else {
      res.json({ success: false, error: result.error, url: result.url });
    }
  } catch (error) {
    res.status(500).json({ success: false, error: { message: error.message } });
  }
});

// Get device state
router.get('/state', async (req, res) => {
  try {
    const result = await req.homeWizardAPI.getDeviceState();
    if (result.success) {
      res.json({ success: true, data: result.data });
    } else {
      res.json({ success: false, error: result.error, url: result.url });
    }
  } catch (error) {
    res.status(500).json({ success: false, error: { message: error.message } });
  }
});

// Update device state
router.put('/state', async (req, res) => {
  try {
    const result = await req.homeWizardAPI.updateDeviceState(req.body);
    if (result.success) {
      res.json({ success: true, data: result.data });
    } else {
      res.json({ success: false, error: result.error, url: result.url });
    }
  } catch (error) {
    res.status(500).json({ success: false, error: { message: error.message } });
  }
});

// Power control
router.post('/power', async (req, res) => {
  try {
    const { power_on } = req.body;
    const result = await req.homeWizardAPI.setPower(power_on);
    
    if (result.success) {
      res.json({ 
        success: true, 
        message: `Power ${power_on ? 'ON' : 'OFF'} command sent successfully`,
        data: result.data 
      });
    } else {
      res.json({ 
        success: false, 
        error: result.error,
        message: `Failed to send power command: ${JSON.stringify(result.error)}`
      });
    }
  } catch (error) {
    res.status(500).json({ 
      success: false, 
      error: { message: error.message },
      message: `Failed to send power command: ${error.message}`
    });
  }
});

// Brightness control
router.post('/brightness', async (req, res) => {
  try {
    const { brightness } = req.body;
    const brightnessValue = parseInt(brightness);
    
    if (isNaN(brightnessValue) || brightnessValue < 0 || brightnessValue > 255) {
      return res.json({
        success: false,
        error: { message: 'Invalid brightness value. Must be 0-255.' },
        message: 'Invalid brightness value. Must be 0-255.'
      });
    }

    const result = await req.homeWizardAPI.setBrightness(brightnessValue);
    
    if (result.success) {
      res.json({ 
        success: true, 
        message: `Brightness set to ${brightnessValue}`,
        data: result.data 
      });
    } else {
      res.json({ 
        success: false, 
        error: result.error,
        message: `Failed to set brightness: ${JSON.stringify(result.error)}`
      });
    }
  } catch (error) {
    res.status(500).json({ 
      success: false, 
      error: { message: error.message },
      message: `Failed to set brightness: ${error.message}`
    });
  }
});

// Switch lock control
router.post('/lock', async (req, res) => {
  try {
    const { switch_lock } = req.body;
    const result = await req.homeWizardAPI.setSwitchLock(switch_lock);
    
    if (result.success) {
      res.json({ 
        success: true, 
        message: `Switch lock ${switch_lock ? 'ENABLED' : 'DISABLED'} successfully`,
        data: result.data 
      });
    } else {
      res.json({ 
        success: false, 
        error: result.error,
        message: `Failed to change switch lock: ${JSON.stringify(result.error)}`
      });
    }
  } catch (error) {
    res.status(500).json({ 
      success: false, 
      error: { message: error.message },
      message: `Failed to change switch lock: ${error.message}`
    });
  }
});

// Get full status (all device information at once)
router.get('/status', async (req, res) => {
  try {
    const result = await req.homeWizardAPI.getFullStatus();
    res.json(result);
  } catch (error) {
    res.status(500).json({ success: false, error: { message: error.message } });
  }
});

// Health check for device
router.get('/health', async (req, res) => {
  try {
    const result = await req.homeWizardAPI.healthCheck();
    res.status(result.status === 'healthy' ? 200 : 503).json(result);
  } catch (error) {
    res.status(503).json({ 
      status: 'unhealthy', 
      error: error.message, 
      timestamp: new Date() 
    });
  }
});

module.exports = router;