# HomeWizard Energy - Pilo Plug PHP Interface

A single-file PHP interface for HomeWizard Energy devices, providing a web UI to interact with both v1 and v2 APIs.

## Features

- Single PHP file interface for HomeWizard Energy devices
- Support for both API v1 (no auth) and v2 (Bearer token)
- Web-based UI for device interaction
- Real-time measurements and device control
- User and token management for v2 API
- Automated deployment with GitHub Actions

## Local Development

This project includes Docker Compose configuration for easy local development.

### Prerequisites

- Docker
- Docker Compose (or Docker with compose plugin)

### Quick Start

1. **Clone the project**
   ```bash
   git clone <your-repo> pilo-plug
   cd pilo-plug
   ```

2. **Start the application**
   ```bash
   docker compose up -d
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
docker compose up -d

# View logs
docker compose logs -f

# Stop the application
docker compose down

# Rebuild and restart
docker compose up -d --build

# Stop and remove everything (including volumes)
docker compose down -v
```

### Local Configuration

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
- You may need to use host networking: `docker compose up -d --network host`

### Development

For development, the `docker-compose.yml` includes a volume mount that allows you to edit `index.php` without rebuilding the container. Changes will be reflected immediately.

To disable the development volume mount for production:
1. Remove or comment out the volumes section in `docker-compose.yml`
2. Rebuild: `docker compose up -d --build`

## Automated Deployment

This repository uses GitHub Actions to automatically deploy to your server using Docker Compose.

### GitHub Configuration

#### Required Secrets
Go to your repository → Settings → Secrets and variables → Actions → Repository secrets

Add the following secret:

- **`SSH_PRIVATE_KEY`** - The private SSH key that corresponds to the public key on your server
  - Generate with: `ssh-keygen -t ed25519 -C "github-actions@pilo-plug"`
  - Copy the private key content (including `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----`)
  - Add the public key to `~/.ssh/authorized_keys` on your server

#### Required Variables  
Go to your repository → Settings → Secrets and variables → Actions → Variables

Add the following variables:

- **`SSH_HOST`** - Your server hostname (e.g., `pilo-plug.treudler.net`)
- **`SSH_PORT`** - SSH port (e.g., `22`)
- **`SSH_USER`** - SSH username (e.g., `root`)
- **`SSH_DIR`** - Absolute path where the project is deployed on the server (e.g., `/opt/pilo-plug`)

### Server Setup

#### Prerequisites

1. **Docker and Docker Compose** must be installed on the server
2. **Git** must be installed on the server  
3. **SSH access** configured with key-based authentication

#### Initial Server Setup

Connect to your server and run:

```bash
# Install Docker (if not already installed)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Ensure Docker Compose plugin is available
# (Usually included with modern Docker installations)
docker compose version

# Set up SSH key authentication
mkdir -p ~/.ssh
# Add your GitHub Actions public key to ~/.ssh/authorized_keys
echo "your-public-key-here" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

**Note:** The GitHub Actions workflow will automatically create the project directory and clone the repository on first deployment. You just need to ensure Docker, Git, and SSH access are configured.

### How Deployment Works

The GitHub Actions workflow triggers on:
- Push to `main` or `master` branch
- Manual workflow dispatch

The deployment process:
1. Connects to your server via SSH
2. Creates the project directory if it doesn't exist
3. Initializes Git repository if needed and pulls the latest code
4. Stops existing Docker containers (if any)
5. Builds and starts new containers with `docker compose up -d --build`
6. Cleans up unused Docker images
7. Verifies the deployment was successful

### Manual Deployment

To deploy manually from your local machine:

```bash
ssh -p 5674 root@pilo-plug.treudler.net
cd /opt/pilo-plug  # or your project path
git pull origin main
docker compose down
docker compose up -d --build
```

## Troubleshooting

### Local Development Issues
- **Port already in use**: Change the port mapping in `docker-compose.yml` from `8080:80` to another port like `8081:80`
- **Cannot reach device**: Verify your HomeWizard device IP and ensure it's accessible from your Docker host
- **SSL/TLS issues**: For v2 API, you may need to enable "Disable SSL verification" in the web interface for local testing

### Deployment Issues
If the deployment fails:
1. Check the GitHub Actions logs for error messages
2. Verify all secrets and variables are set correctly
3. Ensure SSH access works manually: `ssh -p [PORT] [USER]@[HOST]`
4. Check that Docker and Docker Compose are installed on the server
5. Verify the project path exists and has the correct permissions
6. Check server logs: `docker compose logs` on the server

## License

This is a single-file PHP interface for HomeWizard Energy devices. Use according to your needs.