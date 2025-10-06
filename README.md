# HomeWizard Energy - Pilo Plug Node.js Controller

A modern Node.js application for controlling HomeWizard Energy devices with power usage statistics collection and MariaDB storage.

## Features

### Device Control
- ✅ Turn socket on/off remotely
- ✅ Adjust brightness (if supported by device)
- ✅ Control switch lock functionality
- ✅ Real-time device status monitoring

### Live Monitoring
- ✅ Live power measurements (1-second refresh)
- ✅ Voltage, current, frequency monitoring
- ✅ Total energy consumption tracking
- ✅ Device state information

### Statistics & Analytics
- ✅ Automatic data collection every minute
- ✅ Historical power usage statistics
- ✅ Hourly/daily aggregated data for efficient querying
- ✅ Configurable data retention policy
- ✅ Power usage trends and analysis

### Modern Web Interface
- ✅ Responsive design with tabbed interface
- ✅ Real-time updates without page refresh
- ✅ Statistics visualization
- ✅ Device settings management
- ✅ System health monitoring

## Quick Start

### Prerequisites
- Docker & Docker Compose
- MariaDB database (external)
- HomeWizard Energy Socket device

### Installation

1. **Clone and setup:**
   ```bash
   git clone <your-repo> pilo-plug
   cd pilo-plug
   ```

2. **Configure environment:**
   Copy the example configuration and update with your settings:
   ```bash
   cp .env.example .env
   # Edit .env with your device URL and other settings if needed
   ```
   
   The default configuration includes:
   ```bash
   # Database settings (configured for krakatau.treudler.net)
   DB_HOST=krakatau.treudler.net
   DB_USER=pilo_plug_stats
   DB_PASSWORD=Gee4if0quaeThaing2ac7PaChahG9n
   DB_NAME=pilo_plug_stats
   
   # Device settings  
   DEVICE_URL=http://172.16.0.189
   ```

3. **Start the application:**
   ```bash
   docker compose up -d
   ```

4. **Access the interface:**
   Open `http://localhost:8080` in your browser

The database initialization will run automatically on first startup.

## Database Schema

The application creates two main tables:

### power_usage_stats
Raw measurement data collected every minute:
- Timestamp, device ID
- Active power (watts) - **primary metric collected**
- Voltage, current, frequency  
- Energy consumption
- Device state (power, brightness, lock)

### device_info  
Device metadata and information:
- Product name, serial number
- Firmware version, API version
- Last seen timestamp

## API Endpoints

### Device Control
- `GET /api/device/info` - Device information
- `GET /api/device/data` - Current measurements
- `GET /api/device/state` - Device state
- `PUT /api/device/state` - Update device state
- `POST /api/device/power` - Control power
- `POST /api/device/brightness` - Set brightness
- `POST /api/device/lock` - Control switch lock

### Statistics
- `GET /api/stats/recent` - Recent measurements
- `GET /api/stats/hourly` - Hourly aggregates
- `GET /api/stats/daily` - Daily summaries
- `GET /api/stats/summary` - Period summaries

### System
- `GET /health` - System health check
- `GET /api/system/collection/stats` - Data collection statistics
- `POST /api/system/collection/trigger` - Manual data collection

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `PORT` | 3000 | Server port |
| `DB_HOST` | - | MariaDB host |
| `DB_USER` | - | Database username |
| `DB_PASSWORD` | - | Database password |
| `DB_NAME` | - | Database name |
| `DEVICE_URL` | http://172.16.0.189 | HomeWizard device URL |
| `COLLECTION_INTERVAL` | 60000 | Data collection interval (ms) |
| `STATS_RETENTION_DAYS` | 90 | Raw data retention period |

### Data Collection

The system automatically:
- Collects measurements every minute (configurable)
- Stores **only power measurements (watts)** as requested
- Cleans up old data daily (runs at 2:30 AM)
- Tracks device state and basic electrical parameters

## Development

### Local Development

```bash
# Start with Docker Compose
docker compose up -d

# View logs
docker compose logs -f pilo-plug

# Restart application
docker compose restart pilo-plug

# Stop everything
docker compose down
```

### Manual Database Initialization

```bash
# Initialize database schema
docker compose exec pilo-plug npm run init-db
```

### Project Structure
```
├── server.js              # Main application server
├── src/
│   ├── config.js          # Configuration management
│   ├── homewizard-api.js  # HomeWizard API client
│   ├── database.js        # Database operations
│   └── data-collection.js # Background data collection
├── routes/
│   ├── device.js          # Device control routes
│   ├── stats.js           # Statistics routes
│   └── system.js          # System management routes
├── public/
│   └── index.html         # Web interface
├── scripts/
│   └── init-database.js   # Database initialization
└── package.json
```

## Architecture

This Node.js application provides:
- Maintaining full API compatibility for device control
- Adding automatic data collection and storage
- Providing historical statistics and analytics
- Improving error handling and system monitoring
- Using modern async/await patterns
- Implementing RESTful API design

### Key Features
- **Database**: Stores power usage statistics in MariaDB
- **Data Collection**: Automatic background job every minute  
- **Statistics**: Built-in analytics and trend analysis
- **Architecture**: Modern Node.js with Express.js framework
- **Monitoring**: Health checks and system status endpoints

## Automated Deployment

The GitHub Actions workflow automatically deploys to your server:

1. Triggers on push to `main` branch
2. Connects via SSH to your server
3. Pulls latest code and rebuilds containers
4. Initializes database schema if needed
5. Verifies deployment success

See the original README sections for SSH configuration details.

## Troubleshooting

### Common Issues
- **Database connection**: Verify MariaDB credentials and network access
- **Device not found**: Check HomeWizard device IP and network connectivity
- **Data collection failing**: Review logs with `docker compose logs pilo-plug`
- **Port conflicts**: Change port mapping in `docker-compose.yml`

### Monitoring
- Access `/health` endpoint for system status
- Check "System" tab in web interface for detailed monitoring
- Use `docker compose logs -f` to monitor real-time activity

## License

MIT License - Open source HomeWizard Energy device controller with power monitoring.