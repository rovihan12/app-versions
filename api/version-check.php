<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log request untuk debugging
error_log("Version check request received: " . date('Y-m-d H:i:s'));

function getClientVersion() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        return isset($input['current_version']) ? $input['current_version'] : null;
    } else {
        return isset($_GET['version']) ? $_GET['version'] : null;
    }
}

function loadAppConfig() {
    $configFile = __DIR__ . '/../versions/utbk-app.json';
    
    if (!file_exists($configFile)) {
        return null;
    }
    
    $configContent = file_get_contents($configFile);
    return json_decode($configContent, true);
}

function compareVersions($current, $latest) {
    $currentParts = explode('.', $current);
    $latestParts = explode('.', $latest);
    
    $maxLength = max(count($currentParts), count($latestParts));
    
    for ($i = 0; $i < $maxLength; $i++) {
        $currentPart = isset($currentParts[$i]) ? (int)$currentParts[$i] : 0;
        $latestPart = isset($latestParts[$i]) ? (int)$latestParts[$i] : 0;
        
        if ($currentPart < $latestPart) {
            return -1; // Update needed
        } elseif ($currentPart > $latestPart) {
            return 1; // Current version is newer (shouldn't happen)
        }
    }
    
    return 0; // Versions are equal
}

function determineUpdateType($currentVersion, $minRequiredVersion, $latestVersion) {
    if (compareVersions($currentVersion, $minRequiredVersion) < 0) {
        return 'force'; // Force update if below min required version
    } elseif (compareVersions($currentVersion, $latestVersion) < 0) {
        return 'soft'; // Soft update if newer version available
    }
    
    return 'none'; // No update needed
}

// Main execution
try {
    $clientVersion = getClientVersion();
    $appConfig = loadAppConfig();
    
    if (!$appConfig) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Configuration not found'
        ]);
        exit;
    }
    
    $latestVersion = $appConfig['latest_version'];
    $minRequiredVersion = $appConfig['min_required_version'];
    
    // Determine if update is needed and what type
    if ($clientVersion) {
        $updateType = determineUpdateType($clientVersion, $minRequiredVersion, $latestVersion);
    } else {
        // If no version provided, assume update check is needed
        $updateType = 'soft';
    }
    
    $response = [
        'status' => 'success',
        'app_name' => $appConfig['app_name'],
        'package_name' => $appConfig['package_name'],
        'latest_version' => $latestVersion,
        'update_type' => $updateType,
        'min_required_version' => $minRequiredVersion,
        'message' => $appConfig['message'],
        'release_notes' => $appConfig['release_notes'],
        'release_date' => $appConfig['release_date'],
        'play_store_url' => $appConfig['play_store_url'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Jika butuh update, tambahkan info khusus
    if ($updateType !== 'none') {
        $response['update_required'] = true;
        $response['update_message'] = $appConfig['message'];
    } else {
        $response['update_required'] = false;
        $response['message'] = 'Aplikasi sudah versi terbaru';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
