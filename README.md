# HomeWizard Energy - Pilo Plug PHP Interface

A single-file PHP interface for HomeWizard Energy devices, providing a web UI to interact with both v1 and v2 APIs.

## Features

- Single PHP file interface for HomeWizard Energy devices
- Support for both API v1 (no auth) and v2 (Bearer token)
- Web-based UI for device interaction
- Real-time measurements and device control
- User and token management for v2 API

## Docker Setup

This project includes Docker Compose configuration for easy deployment.

### Prerequisites

- Docker
- Docker Compose

### Quick Start

1. **Clone or download this project**
   ```bash
   git clone <your-repo> pilo-plug
   cd pilo-plug
   ```

2. **Start the application**
   ```bash
   docker-compose up -d
   ```

3. **Access the web interface**
   Open your browser and navigate to: `http://localhost:8080`

4. **Configure your device**
   - Set your HomeWizard device IP address (default: `172.16.0.189`)
   - Choose API version (v1 or v2)
   - For v2, create a user token through the web interface

### Docker Commands

```bash
# Start the application in background
docker-compose up -d

# View logs
docker-compose logs -f

# Stop the application
docker-compose down

# Rebuild and restart
docker-compose up -d --build

# Stop and remove everything (including volumes)
docker-compose down -v
```

### Configuration

The Docker setup includes:

- **Web Server**: Apache with PHP 8.2
- **Port**: The application is accessible on port 8080
- **Volume Mount**: The `index.php` file is mounted for development (hot-reload)
- **Network**: Isolated Docker network for the application

### Network Access

The containerized application needs network access to reach your HomeWizard devices on your local network. The Docker configuration uses bridge networking which should allow access to devices on your LAN.

If you have connectivity issues:
- Ensure your HomeWizard device is on the same network
- Check if your Docker installation allows bridge network access to LAN
- You may need to use host networking: `docker-compose up -d --network host`

### Development

For development, the `docker-compose.yml` includes a volume mount that allows you to edit `index.php` without rebuilding the container. Changes will be reflected immediately.

To disable the development volume mount for production:
1. Remove or comment out the volumes section in `docker-compose.yml`
2. Rebuild: `docker-compose up -d --build`

### Troubleshooting

- **Port already in use**: Change the port mapping in `docker-compose.yml` from `8080:80` to another port like `8081:80`
- **Cannot reach device**: Verify your HomeWizard device IP and ensure it's accessible from your Docker host
- **SSL/TLS issues**: For v2 API, you may need to enable "Disable SSL verification" in the web interface for local testing

## License

This is a single-file PHP interface for HomeWizard Energy devices. Use according to your needs.