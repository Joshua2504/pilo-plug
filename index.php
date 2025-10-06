<?php
/**
 * HomeWizard Energy Socket Controller
 *
 * Simple interface for controlling HomeWizard Energy Socket:
 * - Turn socket on/off
 * - Adjust brightness
 * - Live measurement display with 1-second refresh
 *
 * Default device URL is http://172.16.0.189 for v1 API.
 */

// Configuration
$defaultUrl = 'http://172.16.0.189';

// Session management
session_start();

function value($key, $fallback = '') {
    if (isset($_POST[$key])) return trim((string)$_POST[$key]);
    if (isset($_SESSION[$key])) return $_SESSION[$key];
    return $fallback;
}

function keep($key, $val) { $_SESSION[$key] = $val; }

function pretty_json($str) {
    if ($str === '' || $str === null) return '';
    $json = json_decode($str, true);
    if ($json === null) return $str; // not JSON
    return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function http_call($method, $url, $headers = [], $body = null, $insecure = false, $timeout = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // capture headers
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    if ($body !== null && $body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $headers[] = 'Content-Type: application/json';
    }
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Allow local testing with self-signed device cert
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        return [
            'status' => 0,
            'headers' => '',
            'body' => json_encode(['curl_error' => $err, 'curl_errno' => $code])
        ];
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $raw_headers = substr($resp, 0, $header_size);
    $body = substr($resp, $header_size);

    curl_close($ch);
    return ['status' => $status_code, 'headers' => $raw_headers, 'body' => $body];
}

// Handle requests
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Settings
$device_url = value('device_url', $defaultUrl);
$timeout = (int) (value('timeout', '10'));

keep('device_url', $device_url);
keep('timeout', (string)$timeout);

$response = null;
$error = null;

function do_call($method, $path, $body_json = null) {
    global $device_url, $timeout, $response, $error;

    $base = rtrim($device_url, '/');
    $url = $base . $path;

    $resp = http_call($method, $url, [], $body_json, false, $timeout);
    
    if ($resp['status'] === 0) {
        $error = json_decode($resp['body'], true);
        return false;
    }
    
    if ($resp['status'] >= 200 && $resp['status'] < 300) {
        $response = json_decode($resp['body'], true);
        return true;
    } else {
        $error = ['status' => $resp['status'], 'body' => $resp['body']];
        return false;
    }
}

// Handle AJAX requests - return JSON for API calls
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_info':
            if (do_call('GET', '/api')) {
                echo json_encode(['success' => true, 'data' => $response]);
            } else {
                echo json_encode(['success' => false, 'error' => $error, 'url' => $device_url . '/api']);
            }
            break;
            
        case 'get_data':
            if (do_call('GET', '/api/v1/data')) {
                echo json_encode(['success' => true, 'data' => $response]);
            } else {
                echo json_encode(['success' => false, 'error' => $error, 'url' => $device_url . '/api/v1/data']);
            }
            break;
            
        case 'get_state':
            if (do_call('GET', '/api/v1/state')) {
                echo json_encode(['success' => true, 'data' => $response]);
            } else {
                echo json_encode(['success' => false, 'error' => $error, 'url' => $device_url . '/api/v1/state']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// Handle form submissions
if ($action) {
    switch ($action) {
        case 'toggle_power':
            $power_on = ($_POST['power_on'] ?? '0') === '1';
            $payload = ['power_on' => $power_on];
            if (do_call('PUT', '/api/v1/state', json_encode($payload))) {
                $response_msg = 'Power ' . ($power_on ? 'ON' : 'OFF') . ' command sent successfully';
            } else {
                $error_msg = 'Failed to send power command: ' . json_encode($error);
            }
            break;
            
        case 'set_brightness':
            $brightness = (int)($_POST['brightness'] ?? 0);
            if ($brightness >= 0 && $brightness <= 255) {
                $payload = ['brightness' => $brightness];
                if (do_call('PUT', '/api/v1/state', json_encode($payload))) {
                    $response_msg = 'Brightness set to ' . $brightness;
                } else {
                    $error_msg = 'Failed to set brightness: ' . json_encode($error);
                }
            } else {
                $error_msg = 'Invalid brightness value. Must be 0-255.';
            }
            break;
            
        case 'toggle_lock':
            $switch_lock = ($_POST['switch_lock'] ?? '0') === '1';
            $payload = ['switch_lock' => $switch_lock];
            if (do_call('PUT', '/api/v1/state', json_encode($payload))) {
                $response_msg = 'Switch lock ' . ($switch_lock ? 'ENABLED' : 'DISABLED') . ' successfully';
            } else {
                $error_msg = 'Failed to change switch lock: ' . json_encode($error);
            }
            break;
    }
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HomeWizard Energy Socket Controller</title>
<style>
body{font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, sans-serif; margin:20px; background:#f8f9fa;}
.container{max-width: 800px; margin: 0 auto;}
.card{background: white; border:1px solid #e9ecef; border-radius:12px; padding:24px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,.1);}
.row{display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:16px;}
.col{flex:1; min-width:200px;}
label{font-weight:600; margin-bottom:8px; display:block;}
input[type=text], input[type=number], input[type=range]{padding:10px; border-radius:8px; border:1px solid #ced4da; width:100%;}
button{padding:12px 24px; border-radius:8px; border:none; cursor:pointer; font-weight:600; transition:all 0.2s;}
button.primary{background:#28a745; color:white;}
button.primary:hover{background:#218838;}
button.danger{background:#dc3545; color:white;}
button.danger:hover{background:#c82333;}
.status{padding:12px; border-radius:8px; margin-bottom:16px; font-weight:600;}
.status.online{background:#d4edda; color:#155724; border:1px solid #c3e6cb;}
.status.offline{background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;}
.measurement{display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:16px;}
.metric{text-align:center; padding:16px; background:#f8f9fa; border-radius:8px;}
.metric-value{font-size:24px; font-weight:bold; color:#495057;}
.metric-label{font-size:14px; color:#6c757d; margin-top:4px;}
.controls{display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;}
#brightnessValue{font-weight:bold; color:#495057;}
</style>
</head>
<body>
<div class="container">
<h1>HomeWizard Energy Socket Controller</h1>

<!-- Settings -->
<div class="card">
  <h2>Settings</h2>
  <form method="post">
    <div class="row">
      <div class="col">
        <label>Device URL</label>
        <input type="text" name="device_url" value="<?= htmlspecialchars($device_url) ?>" placeholder="http://192.168.1.100">
      </div>
      <div class="col">
        <label>Timeout (seconds)</label>
        <input type="number" name="timeout" value="<?= (int)$timeout ?>" min="1" max="60">
      </div>
      <div class="col" style="display:flex; align-items:end;">
        <button type="submit" class="primary">Save Settings</button>
      </div>
    </div>
  </form>
</div>

<!-- Status -->
<div id="connectionStatus" class="status offline">
  <span id="statusText">Checking connection...</span>
</div>

<?php if (isset($error_msg)): ?>
<div class="status" style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;">
  <strong>Error:</strong> <?= htmlspecialchars($error_msg) ?>
</div>
<?php endif; ?>

<?php if (isset($response_msg)): ?>
<div class="status" style="background:#d4edda; color:#155724; border:1px solid #c3e6cb;">
  <strong>Success:</strong> <?= htmlspecialchars($response_msg) ?>
</div>
<?php endif; ?>

<!-- Live Measurements -->
<div class="card">
  <h2>Live Measurements</h2>
  <div class="measurement">
    <div class="metric">
      <div id="powerActive" class="metric-value">--</div>
      <div class="metric-label">Active Power (W)</div>
    </div>
    <div class="metric">
      <div id="powerReactive" class="metric-value">--</div>
      <div class="metric-label">Reactive Power (VAR)</div>
    </div>
    <div class="metric">
      <div id="voltage" class="metric-value">--</div>
      <div class="metric-label">Voltage (V)</div>
    </div>
    <div class="metric">
      <div id="current" class="metric-value">--</div>
      <div class="metric-label">Current (A)</div>
    </div>
    <div class="metric">
      <div id="frequency" class="metric-value">--</div>
      <div class="metric-label">Frequency (Hz)</div>
    </div>
    <div class="metric">
      <div id="energyTotal" class="metric-value">--</div>
      <div class="metric-label">Total Energy (kWh)</div>
    </div>
  </div>
</div>

<!-- Socket Controls -->
<div class="card">
  <h2>Socket Controls</h2>
  <div class="controls">
    <div>
      <label>Power Control</label>
      <div style="display:flex; gap:12px; margin-top:8px;">
        <button type="button" onclick="togglePower(true)" class="primary" id="btnPowerOn">Turn ON</button>
        <button type="button" onclick="togglePower(false)" class="danger" id="btnPowerOff">Turn OFF</button>
      </div>
      <div style="margin-top:12px;">
        <strong>Status: <span id="powerStatus">Unknown</span></strong>
      </div>
    </div>
    <div>
      <label>Brightness Control</label>
      <div style="margin-top:8px;">
        <input type="range" id="brightnessSlider" min="0" max="255" value="0" oninput="updateBrightnessDisplay(this.value)">
        <div style="margin-top:8px;">
          Value: <span id="brightnessValue">0</span> / 255
        </div>
        <button type="button" onclick="setBrightness()" class="primary" style="margin-top:8px;">Set Brightness</button>
      </div>
    </div>
    <div>
      <label>Switch Lock</label>
      <div style="display:flex; gap:12px; margin-top:8px;">
        <button type="button" onclick="toggleLock(true)" class="primary" id="btnLockEnable">Enable Lock</button>
        <button type="button" onclick="toggleLock(false)" class="danger" id="btnLockDisable">Disable Lock</button>
      </div>
      <div style="margin-top:12px;">
        <strong>Lock Status: <span id="lockStatus">Unknown</span></strong>
      </div>
      <div style="margin-top:8px; font-size:14px; color:#6c757d;">
        When enabled, prevents accidental power changes via physical button
      </div>
    </div>
  </div>
</div>

<!-- Device Information -->
<div class="card">
  <h2>Device Information</h2>
  <div id="deviceInfo">
    <div>Loading device information...</div>
  </div>
</div>

<script>
let refreshInterval;
let currentState = {};
let userInteractingWithSlider = false;

// Start live updates when page loads
document.addEventListener('DOMContentLoaded', function() {
    startLiveUpdates();
    
    // Track when user is interacting with brightness slider
    const brightnessSlider = document.getElementById('brightnessSlider');
    brightnessSlider.addEventListener('mousedown', () => { userInteractingWithSlider = true; });
    brightnessSlider.addEventListener('mouseup', () => { 
        setTimeout(() => { userInteractingWithSlider = false; }, 500); // Delay to allow for final adjustment
    });
    brightnessSlider.addEventListener('touchstart', () => { userInteractingWithSlider = true; });
    brightnessSlider.addEventListener('touchend', () => { 
        setTimeout(() => { userInteractingWithSlider = false; }, 500); // Delay to allow for final adjustment
    });
});

function startLiveUpdates() {
    // Initial load
    updateDeviceInfo();
    updateMeasurements();
    updateSocketState();
    
    // Set up 1-second refresh
    refreshInterval = setInterval(function() {
        updateMeasurements();
        updateSocketState();
    }, 1000);
    
    // Update device info less frequently (every 30 seconds)
    setInterval(updateDeviceInfo, 30000);
}

function updateDeviceInfo() {
    fetch('?ajax=1&action=get_info')
        .then(response => response.json())
        .then(data => {
            const statusEl = document.getElementById('connectionStatus');
            const statusTextEl = document.getElementById('statusText');
            const deviceInfoEl = document.getElementById('deviceInfo');
            
            if (data.success) {
                statusEl.className = 'status online';
                statusTextEl.textContent = 'Connected to ' + (data.data.product_name || 'HomeWizard Device');
                
                let infoHtml = '<div class="row">';
                if (data.data.product_name) infoHtml += '<div class="col"><strong>Product:</strong> ' + data.data.product_name + '</div>';
                if (data.data.serial) infoHtml += '<div class="col"><strong>Serial:</strong> ' + data.data.serial + '</div>';
                if (data.data.firmware_version) infoHtml += '<div class="col"><strong>Firmware:</strong> ' + data.data.firmware_version + '</div>';
                infoHtml += '</div>';
                
                deviceInfoEl.innerHTML = infoHtml;
            } else {
                statusEl.className = 'status offline';
                statusTextEl.textContent = 'Connection failed';
                let errorHtml = '<div style="color: #dc3545;">Unable to connect to device</div>';
                if (data.error) {
                    errorHtml += '<div style="font-size: 12px; margin-top: 8px; color: #6c757d;">Error: ' + JSON.stringify(data.error) + '</div>';
                }
                if (data.url) {
                    errorHtml += '<div style="font-size: 12px; margin-top: 4px; color: #6c757d;">URL: ' + data.url + '</div>';
                }
                deviceInfoEl.innerHTML = errorHtml;
            }
        })
        .catch(error => {
            document.getElementById('connectionStatus').className = 'status offline';
            document.getElementById('statusText').textContent = 'Connection error';
            console.error('Device info fetch error:', error);
        });
}

function updateMeasurements() {
    fetch('?ajax=1&action=get_data')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const d = data.data;
                document.getElementById('powerActive').textContent = d.active_power_w !== undefined ? d.active_power_w.toFixed(1) : '--';
                document.getElementById('powerReactive').textContent = d.reactive_power_var !== undefined ? d.reactive_power_var.toFixed(1) : '--';
                document.getElementById('voltage').textContent = d.voltage_v !== undefined ? d.voltage_v.toFixed(1) : '--';
                document.getElementById('current').textContent = d.current_a !== undefined ? d.current_a.toFixed(3) : '--';
                document.getElementById('frequency').textContent = d.frequency_hz !== undefined ? d.frequency_hz.toFixed(2) : '--';
                document.getElementById('energyTotal').textContent = d.total_energy_import_kwh !== undefined ? d.total_energy_import_kwh.toFixed(3) : '--';
            } else {
                // Set error indicators
                ['powerActive', 'powerReactive', 'voltage', 'current', 'frequency', 'energyTotal'].forEach(id => {
                    document.getElementById(id).textContent = 'ERR';
                });
                if (data.error) {
                    console.log('Measurement error:', data.error, 'URL:', data.url);
                }
            }
        })
        .catch(error => {
            console.log('Measurement update failed:', error);
            // Set network error indicators
            ['powerActive', 'powerReactive', 'voltage', 'current', 'frequency', 'energyTotal'].forEach(id => {
                document.getElementById(id).textContent = 'NET';
            });
        });
}

function updateSocketState() {
    fetch('?ajax=1&action=get_state')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const d = data.data;
                currentState = d;
                
                // Update power status
                const powerStatus = document.getElementById('powerStatus');
                if (d.power_on !== undefined) {
                    powerStatus.textContent = d.power_on ? 'ON' : 'OFF';
                    powerStatus.style.color = d.power_on ? '#28a745' : '#dc3545';
                } else {
                    powerStatus.textContent = 'Unknown';
                    powerStatus.style.color = '#6c757d';
                }
                
                // Update brightness slider (only if supported and user isn't interacting with it)
                const brightnessControl = document.querySelector('.controls > div:nth-child(2)');
                if (d.brightness !== undefined) {
                    brightnessControl.style.display = 'block';
                    
                    // Only update slider if user isn't currently interacting with it
                    if (!userInteractingWithSlider) {
                        document.getElementById('brightnessSlider').value = d.brightness;
                        document.getElementById('brightnessValue').textContent = d.brightness;
                    }
                } else {
                    // Hide brightness control if not supported
                    brightnessControl.style.display = 'none';
                    console.log('Brightness control not supported by this device');
                }
                
                // Update lock status
                const lockStatus = document.getElementById('lockStatus');
                if (d.switch_lock !== undefined) {
                    lockStatus.textContent = d.switch_lock ? 'ENABLED' : 'DISABLED';
                    lockStatus.style.color = d.switch_lock ? '#dc3545' : '#28a745';
                } else {
                    lockStatus.textContent = 'Not Supported';
                    lockStatus.style.color = '#6c757d';
                }
            } else {
                // Handle error case
                const powerStatus = document.getElementById('powerStatus');
                powerStatus.textContent = 'Error';
                powerStatus.style.color = '#dc3545';
                
                const lockStatus = document.getElementById('lockStatus');
                lockStatus.textContent = 'Error';
                lockStatus.style.color = '#dc3545';
                
                if (data.error) {
                    console.log('State error:', data.error, 'URL:', data.url);
                }
            }
        })
        .catch(error => {
            console.log('State update failed:', error);
            const powerStatus = document.getElementById('powerStatus');
            powerStatus.textContent = 'Network Error';
            powerStatus.style.color = '#dc3545';
            
            const lockStatus = document.getElementById('lockStatus');
            lockStatus.textContent = 'Network Error';
            lockStatus.style.color = '#dc3545';
        });
}

function togglePower(turnOn) {
    const formData = new FormData();
    formData.append('action', 'toggle_power');
    formData.append('power_on', turnOn ? '1' : '0');
    
    fetch('', {
        method: 'POST',
        body: formData
    }).then(() => {
        // Force immediate state update
        setTimeout(updateSocketState, 100);
    });
}

function setBrightness() {
    const brightness = document.getElementById('brightnessSlider').value;
    const formData = new FormData();
    formData.append('action', 'set_brightness');
    formData.append('brightness', brightness);
    
    // Temporarily prevent slider updates while setting brightness
    userInteractingWithSlider = true;
    
    fetch('', {
        method: 'POST',
        body: formData
    }).then(() => {
        // Allow updates again after a short delay
        setTimeout(() => {
            userInteractingWithSlider = false;
            updateSocketState();
        }, 1000);
    }).catch(() => {
        // Re-enable updates on error
        userInteractingWithSlider = false;
    });
}

function updateBrightnessDisplay(value) {
    document.getElementById('brightnessValue').textContent = value;
}

function toggleLock(enableLock) {
    const formData = new FormData();
    formData.append('action', 'toggle_lock');
    formData.append('switch_lock', enableLock ? '1' : '0');
    
    fetch('', {
        method: 'POST',
        body: formData
    }).then(() => {
        // Force immediate state update
        setTimeout(updateSocketState, 100);
    });
}
</script>

</div>
</body>
</html>
