const axios = require('axios');
const { config } = require('./config');

class HomeWizardAPI {
  constructor(deviceUrl = null, timeout = null) {
    this.baseURL = deviceUrl || config.device.url;
    this.timeout = timeout || config.device.timeout;
    
    // Create axios instance with default configuration
    this.client = axios.create({
      baseURL: this.baseURL,
      timeout: this.timeout,
      headers: {
        'Content-Type': 'application/json'
      }
    });
  }

  // Get device information (API v1)
  async getDeviceInfo() {
    try {
      const response = await this.client.get('/api');
      return {
        success: true,
        data: response.data
      };
    } catch (error) {
      return {
        success: false,
        error: this.formatError(error),
        url: `${this.baseURL}/api`
      };
    }
  }

  // Get current measurements (API v1)
  async getCurrentData() {
    try {
      const response = await this.client.get('/api/v1/data');
      return {
        success: true,
        data: response.data
      };
    } catch (error) {
      return {
        success: false,
        error: this.formatError(error),
        url: `${this.baseURL}/api/v1/data`
      };
    }
  }

  // Get device state (API v1)
  async getDeviceState() {
    try {
      const response = await this.client.get('/api/v1/state');
      return {
        success: true,
        data: response.data
      };
    } catch (error) {
      return {
        success: false,
        error: this.formatError(error),
        url: `${this.baseURL}/api/v1/state`
      };
    }
  }

  // Update device state (API v1)
  async updateDeviceState(stateUpdate) {
    try {
      const response = await this.client.put('/api/v1/state', stateUpdate);
      return {
        success: true,
        data: response.data
      };
    } catch (error) {
      return {
        success: false,
        error: this.formatError(error),
        url: `${this.baseURL}/api/v1/state`
      };
    }
  }

  // Control power (convenience method)
  async setPower(powerOn) {
    return await this.updateDeviceState({ power_on: powerOn });
  }

  // Set brightness (convenience method)
  async setBrightness(brightness) {
    if (brightness < 0 || brightness > 255) {
      return {
        success: false,
        error: { message: 'Brightness must be between 0 and 255' }
      };
    }
    return await this.updateDeviceState({ brightness: brightness });
  }

  // Control switch lock (convenience method)
  async setSwitchLock(locked) {
    return await this.updateDeviceState({ switch_lock: locked });
  }

  // Get comprehensive device status (combines multiple API calls)
  async getFullStatus() {
    try {
      const [info, data, state] = await Promise.all([
        this.getDeviceInfo(),
        this.getCurrentData(), 
        this.getDeviceState()
      ]);

      return {
        success: true,
        info: info.success ? info.data : null,
        data: data.success ? data.data : null,
        state: state.success ? state.data : null,
        errors: {
          info: info.success ? null : info.error,
          data: data.success ? null : data.error,
          state: state.success ? null : state.error
        }
      };
    } catch (error) {
      return {
        success: false,
        error: this.formatError(error)
      };
    }
  }

  // Health check - simple connectivity test
  async healthCheck() {
    try {
      const start = Date.now();
      const result = await this.getDeviceInfo();
      const responseTime = Date.now() - start;
      
      return {
        status: result.success ? 'healthy' : 'unhealthy',
        responseTime: responseTime,
        timestamp: new Date(),
        details: result.success ? result.data : result.error
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        responseTime: null,
        timestamp: new Date(),
        details: this.formatError(error)
      };
    }
  }

  // Format error for consistent error handling
  formatError(error) {
    if (error.code === 'ECONNABORTED') {
      return {
        type: 'timeout',
        message: 'Request timeout',
        timeout: this.timeout
      };
    }
    
    if (error.code === 'ECONNREFUSED' || error.code === 'ENOTFOUND') {
      return {
        type: 'connection',
        message: 'Cannot connect to device',
        url: this.baseURL,
        code: error.code
      };
    }

    if (error.response) {
      return {
        type: 'http',
        status: error.response.status,
        message: error.response.statusText,
        data: error.response.data
      };
    }

    return {
      type: 'unknown',
      message: error.message,
      code: error.code
    };
  }

  // Update device URL and recreate client
  updateDeviceURL(newURL) {
    this.baseURL = newURL;
    this.client = axios.create({
      baseURL: this.baseURL,
      timeout: this.timeout,
      headers: {
        'Content-Type': 'application/json'
      }
    });
  }

  // Update timeout and recreate client
  updateTimeout(newTimeout) {
    this.timeout = newTimeout;
    this.client = axios.create({
      baseURL: this.baseURL,
      timeout: this.timeout,
      headers: {
        'Content-Type': 'application/json'
      }
    });
  }
}

module.exports = HomeWizardAPI;