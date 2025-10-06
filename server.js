const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const path = require('path');

// Import our modules
const { config, validateConfig } = require('./src/config');
const { initializeDatabase, healthCheck } = require('./src/database');
const HomeWizardAPI = require('./src/homewizard-api');
const DataCollectionService = require('./src/data-collection');

// Import route modules
const deviceRoutes = require('./routes/device');
const statsRoutes = require('./routes/stats');
const systemRoutes = require('./routes/system');

// Initialize Express app
const app = express();

// Global variables for services
let homeWizardAPI;
let dataCollectionService;

// Middleware
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      styleSrc: ["'self'", "'unsafe-inline'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      imgSrc: ["'self'", "data:", "https:"]
    }
  }
}));
app.use(cors());
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));

// Serve static files
app.use(express.static(path.join(__dirname, 'public')));

// Make services available to routes
app.use((req, res, next) => {
  req.homeWizardAPI = homeWizardAPI;
  req.dataCollectionService = dataCollectionService;
  next();
});

// API Routes
app.use('/api/device', deviceRoutes);
app.use('/api/stats', statsRoutes);
app.use('/api/system', systemRoutes);

// Legacy API compatibility (for existing frontend)
app.get('/api', async (req, res) => {
  const result = await homeWizardAPI.getDeviceInfo();
  if (result.success) {
    res.json(result.data);
  } else {
    res.status(500).json(result.error);
  }
});

// Health check endpoint
app.get('/health', async (req, res) => {
  try {
    const dbHealth = await healthCheck();
    const deviceHealth = await homeWizardAPI.healthCheck();
    const serviceHealth = await dataCollectionService.healthCheck();
    
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
      },
      version: process.env.npm_package_version || '2.0.0'
    });
  } catch (error) {
    res.status(503).json({
      status: 'unhealthy',
      error: error.message,
      timestamp: new Date()
    });
  }
});

// Serve the main HTML page for any unmatched routes
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Error handler
app.use((error, req, res, next) => {
  console.error('Server error:', error);
  res.status(500).json({
    success: false,
    error: {
      message: 'Internal server error',
      details: config.server.environment === 'development' ? error.message : undefined
    }
  });
});

// Initialize services and start server
async function startServer() {
  try {
    console.log('🚀 Starting Pilo Plug server...');
    
    // Validate configuration
    validateConfig();
    console.log('✅ Configuration validated');

    // Initialize database
    initializeDatabase();
    console.log('✅ Database connection established');

    // Initialize HomeWizard API client
    homeWizardAPI = new HomeWizardAPI();
    console.log('✅ HomeWizard API client initialized');

    // Initialize data collection service
    dataCollectionService = new DataCollectionService();
    dataCollectionService.start();
    console.log('✅ Data collection service started');

    // Start HTTP server
    const port = config.server.port;
    app.listen(port, '0.0.0.0', () => {
      console.log(`🌐 Server running on port ${port}`);
      console.log(`🏠 Device URL: ${config.device.url}`);
      console.log(`📊 Collection interval: ${config.collection.interval}ms`);
      console.log(`🗄️  Database: ${config.database.host}/${config.database.database}`);
    });

  } catch (error) {
    console.error('❌ Failed to start server:', error);
    process.exit(1);
  }
}

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('🛑 Received SIGTERM, shutting down gracefully...');
  if (dataCollectionService) {
    dataCollectionService.stop();
  }
  process.exit(0);
});

process.on('SIGINT', () => {
  console.log('🛑 Received SIGINT, shutting down gracefully...');
  if (dataCollectionService) {
    dataCollectionService.stop();
  }
  process.exit(0);
});

// Start the server
startServer();