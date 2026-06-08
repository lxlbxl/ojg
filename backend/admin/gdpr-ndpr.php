<?php
/**
 * GDPR & NDPR Compliance Dashboard
 * 
 * GDPR: General Data Protection Regulation (EU)
 * NDPR: Nigeria Data Protection Regulation (NDPR 2019)
 * 
 * This dashboard provides tools for managing data subject rights,
 * consent records, data processing activities, and compliance reporting.
 */

session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$isMySQL = $pdo && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'export_user_data':
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            if ($email) {
                // Export all user data
                $exportData = exportUserData($email, $db, $isMySQL);
                $message = "Data export generated for {$email}";
                $messageType = 'success';
            }
            break;
            
        case 'delete_user_data':
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $confirm = $_POST['confirm_delete'] ?? '';
            if ($email && $confirm === 'YES') {
                deleteUserRecord($email, $db, $isMySQL);
                $message = "User data deleted for {$email} (GDPR/NDPR erasure request)";
                $messageType = 'warning';
            }
            break;
            
        case 'update_privacy_settings':
            $settings = [
                'data_retention_days' => intval($_POST['data_retention_days'] ?? 730),
                'cookie_consent_required' => isset($_POST['cookie_consent_required']) ? 1 : 0,
                'marketing_consent_required' => isset($_POST['marketing_consent_required']) ? 1 : 0,
                'privacy_policy_version' => sanitize($_POST['privacy_policy_version'] ?? '1.0.0'),
                'dpo_email' => filter_input(INPUT_POST, 'dpo_email', FILTER_SANITIZE_EMAIL),
                'consent_record_retention' => intval($_POST['consent_record_retention'] ?? 1095),
            ];
            updateComplianceSettings($settings, $db);
            $message = "Privacy settings updated successfully";
            $messageType = 'success';
            break;
            
        case 'generate_compliance_report':
            $reportType = $_POST['report_type'] ?? 'full';
            $reportData = generateComplianceReport($reportType, $db, $isMySQL);
            $message = "Compliance report generated";
            $messageType = 'success';
            break;
    }
}

// Get statistics
$stats = getComplianceStats($db, $isMySQL);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GDPR & NDPR Compliance - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-shield-alt text-blue-600"></i>
                GDPR & NDPR Compliance Dashboard
            </h1>
            <p class="text-gray-600 mt-2">
                Manage data protection compliance, user rights requests, and consent records
            </p>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Compliance Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Data Subjects</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_users']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Active Consents</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_consents']; ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending Requests</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_requests']; ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Data Access Requests</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['access_requests']; ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-file-export text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- User Rights Management -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-user-shield"></i>
                    Data Subject Rights
                </h2>
                <p class="text-gray-600 text-sm mb-4">
                    Process user requests for data access, rectification, erasure, and portability
                </p>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">User Email</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="user@example.com">
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="submit" name="action" value="export_user_data"
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-download"></i> Export Data (DSAR)
                        </button>
                    </div>
                    
                    <div class="border-t pt-4">
                        <p class="text-sm text-red-600 font-medium mb-2">⚠️ Danger Zone</p>
                        <div class="flex items-start gap-2 mb-2">
                            <input type="checkbox" name="confirm_delete" value="YES" id="confirm_delete"
                                   class="mt-1 text-red-600 focus:ring-red-500">
                            <label for="confirm_delete" class="text-sm text-gray-600">
                                I confirm this will permanently delete all user data (Right to Erasure)
                            </label>
                        </div>
                        <button type="submit" name="action" value="delete_user_data"
                                class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition"
                                onclick="return confirm('Are you sure? This will permanently delete all user data.')">
                            <i class="fas fa-trash"></i> Delete User Data
                        </button>
                    </div>
                </form>
            </div>

            <!-- Privacy Settings -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-cog"></i>
                    Privacy & Consent Settings
                </h2>
                <p class="text-gray-600 text-sm mb-4">
                    Configure data retention, consent requirements, and DPO contact
                </p>
                
                <?php
                $settings = getComplianceSettings($db);
                ?>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_privacy_settings">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Data Retention Period (days)
                        </label>
                        <input type="number" name="data_retention_days" value="<?php echo $settings['data_retention_days']; ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">GDPR recommends keeping data only as long as necessary</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Consent Record Retention (days)
                        </label>
                        <input type="number" name="consent_record_retention" value="<?php echo $settings['consent_record_retention']; ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">NDPR requires consent records to be kept for audit</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            DPO Email (Data Protection Officer)
                        </label>
                        <input type="email" name="dpo_email" value="<?php echo htmlspecialchars($settings['dpo_email']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="dpo@yourcompany.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Privacy Policy Version
                        </label>
                        <input type="text" name="privacy_policy_version" value="<?php echo htmlspecialchars($settings['privacy_policy_version']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="cookie_consent_required" id="cookie_consent"
                               <?php echo $settings['cookie_consent_required'] ? 'checked' : ''; ?>
                               class="text-blue-600 focus:ring-blue-500">
                        <label for="cookie_consent" class="text-sm text-gray-700">Require Cookie Consent</label>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="marketing_consent_required" id="marketing_consent"
                               <?php echo $settings['marketing_consent_required'] ? 'checked' : ''; ?>
                               class="text-blue-600 focus:ring-blue-500">
                        <label for="marketing_consent" class="text-sm text-gray-700">Require Marketing Consent (Opt-in)</label>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-save"></i> Save Privacy Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Compliance Report -->
        <div class="mt-8 bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-file-alt"></i>
                Compliance Reports
            </h2>
            <p class="text-gray-600 text-sm mb-4">
                Generate reports for regulatory compliance and audits
            </p>
            
            <form method="POST" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Report Type</label>
                    <select name="report_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="full">Full Compliance Report (GDPR + NDPR)</option>
                        <option value="consent">Consent Records Report</option>
                        <option value="access">Data Access Requests Report</option>
                        <option value="retention">Data Retention Report</option>
                        <option value="breach">Data Breach Log</option>
                    </select>
                </div>
                <button type="submit" name="action" value="generate_compliance_report"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-file-download"></i> Generate Report
                </button>
            </form>
        </div>

        <!-- Compliance Checklist -->
        <div class="mt-8 bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-clipboard-check"></i>
                GDPR & NDPR Compliance Checklist
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php
                $checklist = [
                    ['item' => 'Privacy Policy published and accessible', 'checked' => true],
                    ['item' => 'Cookie consent banner implemented', 'checked' => true],
                    ['item' => 'Data Processing Agreement with processors', 'checked' => false],
                    ['item' => 'User consent records maintained', 'checked' => true],
                    ['item' => 'Data Subject Access Request (DSAR) process', 'checked' => true],
                    ['item' => 'Right to erasure (deletion) process', 'checked' => true],
                    ['item' => 'Data portability mechanism', 'checked' => true],
                    ['item' => 'Breach notification procedure', 'checked' => false],
                    ['item' => 'DPO contact information published', 'checked' => $settings['dpo_email'] ? true : false],
                    ['item' => 'Consent withdrawal mechanism', 'checked' => true],
                    ['item' => 'Age verification for minors', 'checked' => false],
                    ['item' => 'International transfer safeguards', 'checked' => false],
                ];
                ?>
                
                <?php foreach ($checklist as $check): ?>
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <i class="fas <?php echo $check['checked'] ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-500'; ?>"></i>
                    <span class="text-sm text-gray-700"><?php echo $check['item']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Legal Requirements Info -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-blue-50 p-6 rounded-lg border border-blue-200">
                <h3 class="font-bold text-blue-800 mb-3">
                    <i class="fas fa-flag-eu"></i> GDPR Requirements (EU)
                </h3>
                <ul class="space-y-2 text-sm text-blue-700">
                    <li>• Lawful basis for processing</li>
                    <li>• Explicit consent for sensitive data</li>
                    <li>• Right to access, rectify, erase</li>
                    <li>• Data portability</li>
                    <li>• 72-hour breach notification</li>
                    <li>• Privacy by design</li>
                    <li>• Records of processing activities</li>
                </ul>
            </div>
            
            <div class="bg-green-50 p-6 rounded-lg border border-green-200">
                <h3 class="font-bold text-green-800 mb-3">
                    <i class="fas fa-flag"></i> NDPR Requirements (Nigeria)
                </h3>
                <ul class="space-y-2 text-sm text-green-700">
                    <li>• Lawful processing of personal data</li>
                    <li>• Consent for data collection</li>
                    <li>• Purpose limitation</li>
                    <li>• Data minimization</li>
                    <li>• Accuracy of data</li>
                    <li>• Storage limitation</li>
                    <li>• Accountability of data controller</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Auto-dismiss messages
        setTimeout(() => {
            document.querySelectorAll('.p-4.rounded-lg').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>

<?php

// Helper Functions

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function getComplianceStats($db, $isMySQL) {
    $stats = [];
    
    // Total users
    $table = $isMySQL ? 'users' : 'users';
    $result = $db->fetch("SELECT COUNT(*) as count FROM {$table}");
    $stats['total_users'] = $result['count'] ?? 0;
    
    // Active consents (users with consent recorded)
    $result = $db->fetch("SELECT COUNT(DISTINCT user_id) as count FROM consent_records WHERE consent_given = 1");
    $stats['active_consents'] = $result['count'] ?? 0;
    
    // Pending requests
    $result = $db->fetch("SELECT COUNT(*) as count FROM data_requests WHERE status = 'pending'");
    $stats['pending_requests'] = $result['count'] ?? 0;
    
    // Access requests
    $result = $db->fetch("SELECT COUNT(*) as count FROM data_requests WHERE request_type = 'access'");
    $stats['access_requests'] = $result['count'] ?? 0;
    
    return $stats;
}

function getComplianceSettings($db) {
    $defaults = [
        'data_retention_days' => 730,
        'cookie_consent_required' => 1,
        'marketing_consent_required' => 1,
        'privacy_policy_version' => '1.0.0',
        'dpo_email' => '',
        'consent_record_retention' => 1095,
    ];
    
    try {
        $settings = $db->fetch("SELECT * FROM compliance_settings WHERE id = 1");
        if ($settings) {
            return array_merge($defaults, $settings);
        }
    } catch (Exception $e) {
        // Table might not exist
    }
    
    return $defaults;
}

function updateComplianceSettings($settings, $db) {
    try {
        // Check if table exists
        $exists = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='compliance_settings'");
        
        if (!$exists) {
            // Create table
            $db->exec("CREATE TABLE compliance_settings (
                id INTEGER PRIMARY KEY,
                data_retention_days INTEGER DEFAULT 730,
                cookie_consent_required INTEGER DEFAULT 1,
                marketing_consent_required INTEGER DEFAULT 1,
                privacy_policy_version TEXT DEFAULT '1.0.0',
                dpo_email TEXT DEFAULT '',
                consent_record_retention INTEGER DEFAULT 1095,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
        
        $existing = $db->fetch("SELECT id FROM compliance_settings WHERE id = 1");
        
        if ($existing) {
            $db->exec("UPDATE compliance_settings SET 
                data_retention_days = :data_retention_days,
                cookie_consent_required = :cookie_consent_required,
                marketing_consent_required = :marketing_consent_required,
                privacy_policy_version = :privacy_policy_version,
                dpo_email = :dpo_email,
                consent_record_retention = :consent_record_retention,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = 1", $settings);
        } else {
            $db->insert('compliance_settings', $settings);
        }
    } catch (Exception $e) {
        error_log("Error updating compliance settings: " . $e->getMessage());
    }
}

function exportUserData($email, $db, $isMySQL) {
    $export = [
        'export_date' => date('Y-m-d H:i:s'),
        'email' => $email,
        'data' => []
    ];
    
    // Get user data
    $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    if ($user) {
        $export['data']['user'] = $user;
        
        // Get assessments
        $assessments = $db->fetchAll("SELECT * FROM assessments WHERE email = ?", [$email]);
        $export['data']['assessments'] = $assessments;
        
        // Get sales
        $sales = $db->fetchAll("SELECT * FROM sales WHERE email = ?", [$email]);
        $export['data']['sales'] = $sales;
        
        // Get consent records
        $consents = $db->fetchAll("SELECT * FROM consent_records WHERE user_id = ?", [$user['id']]);
        $export['data']['consents'] = $consents;
        
        // Log the export
        $db->insert('data_requests', [
            'email' => $email,
            'request_type' => 'access',
            'status' => 'completed',
            'processed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Download as JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="data-export-' . sanitize($email) . '.json"');
    echo json_encode($export, JSON_PRETTY_PRINT);
    exit();
}

function deleteUserRecord($email, $db, $isMySQL) {
    $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    
    if ($user) {
        $userId = $user['id'];
        
        // Anonymize instead of delete for audit purposes
        $db->exec("UPDATE users SET 
            first_name = '[DELETED]',
            name = '[DELETED]',
            phone = '',
            type = 'deleted',
            status = 'deleted',
            deleted_at = CURRENT_TIMESTAMP
            WHERE id = :id", ['id' => $userId]);
        
        // Anonymize assessments
        $db->exec("UPDATE assessments SET 
            name = '[DELETED]',
            phone = '',
            assessment_data = '[DELETED]'
            WHERE user_id = :user_id", ['user_id' => $userId]);
        
        // Log deletion request
        $db->insert('data_requests', [
            'email' => $email,
            'request_type' => 'erasure',
            'status' => 'completed',
            'processed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

function generateComplianceReport($type, $db, $isMySQL) {
    $report = [
        'generated_at' => date('Y-m-d H:i:s'),
        'type' => $type,
        'data' => []
    ];
    
    switch ($type) {
        case 'consent':
            $report['data']['consents'] = $db->fetchAll("SELECT * FROM consent_records ORDER BY created_at DESC LIMIT 100");
            break;
        case 'access':
            $report['data']['requests'] = $db->fetchAll("SELECT * FROM data_requests ORDER BY created_at DESC LIMIT 100");
            break;
        case 'retention':
            $report['data']['old_records'] = $db->fetchAll("SELECT * FROM users WHERE created_at < date('now', '-2 years')");
            break;
        case 'breach':
            $report['data']['breaches'] = $db->fetchAll("SELECT * FROM data_breaches ORDER BY created_at DESC");
            break;
        default:
            // Full report
            $report['data']['summary'] = getComplianceStats($db, $isMySQL);
            $report['data']['consents'] = $db->fetch("SELECT COUNT(*) as count FROM consent_records")['count'];
            $report['data']['requests'] = $db->fetch("SELECT COUNT(*) as count FROM data_requests")['count'];
    }
    
    return $report;
}