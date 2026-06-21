<?php
require_once 'auth.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$driver = $db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME);

$intervalExpr = $driver === 'pgsql'
    ? "CURRENT_DATE - INTERVAL '30 days'"
    : "DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

// Leaderboard data
$leaderboard = [];
$summary = ['active_members' => 0, 'total_completed' => 0, 'total_missed' => 0, 'overall_compliance' => 0];

try {
    $leaderboard = $db->fetchAll("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            mp.subscription_status,
            mp.pcos_type,
            mp.subscription_expiry,
            COUNT(al.id)                                                                AS total_activities,
            SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END)                   AS completed,
            SUM(CASE WHEN al.status = 'missed'    THEN 1 ELSE 0 END)                   AS missed,
            COUNT(DISTINCT al.plan_date)                                                AS days_active,
            MAX(al.plan_date)                                                           AS last_active_date,
            ROUND(
                CASE WHEN COUNT(al.id) > 0
                THEN 100.0 * SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END) / COUNT(al.id)
                ELSE 0 END, 1
            )                                                                           AS compliance_rate
        FROM users u
        INNER JOIN member_profiles mp ON mp.user_id = u.id
        LEFT JOIN activity_logs al
            ON al.user_id = u.id
            AND al.plan_date >= {$intervalExpr}
        WHERE u.type = 'customer'
        GROUP BY u.id, u.first_name, u.last_name, u.email,
                 mp.subscription_status, mp.pcos_type, mp.subscription_expiry
        ORDER BY compliance_rate DESC, completed DESC
        LIMIT 100
    ");

    $summary = $db->fetch("
        SELECT
            COUNT(DISTINCT al.user_id)                                               AS active_members,
            COUNT(al.id)                                                             AS total_logs,
            SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END)                AS total_completed,
            SUM(CASE WHEN al.status = 'missed'    THEN 1 ELSE 0 END)                AS total_missed,
            ROUND(
                CASE WHEN COUNT(al.id) > 0
                THEN 100.0 * SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END) / COUNT(al.id)
                ELSE 0 END, 1
            )                                                                        AS overall_compliance
        FROM activity_logs al
        WHERE al.plan_date >= {$intervalExpr}
    ") ?? $summary;
} catch (Exception $e) {
    error_log('member-pulse.php: ' . $e->getMessage());
}

$pageTitle = 'Member Pulse - OJG Admin';
include 'includes/header.php';

$medals = ['🥇', '🥈', '🥉'];
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
    <div>
        <p class="text-[#6B7C70] text-sm font-medium uppercase tracking-wider mb-2">Engagement Analytics</p>
        <h2 class="text-4xl md:text-5xl font-serif text-[#2C3E35]">Member <span class="italic font-light">Pulse</span></h2>
        <p class="text-[#6B7C70] mt-2">30-day compliance, activity streaks &amp; engagement</p>
    </div>
    <div class="flex gap-3">
        <a href="member-pulse.php" class="flex items-center gap-2 px-5 py-2.5 bg-[#2C3E35] text-white rounded-full text-sm font-medium hover:bg-[#3a5246] transition-colors">
            <i class="fas fa-sync-alt text-xs"></i> Refresh
        </a>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-10">
    <!-- Active Members -->
    <div class="luxury-card p-6 flex flex-col gap-3">
        <div class="w-10 h-10 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35]">
            <i class="fas fa-users text-sm"></i>
        </div>
        <div>
            <p class="text-[#6B7C70] text-xs font-medium uppercase tracking-wider">Active Members</p>
            <h3 class="text-3xl font-serif text-[#2C3E35] mt-1"><?php echo number_format($summary['active_members'] ?? 0); ?></h3>
            <p class="text-xs text-[#A4B4A6] mt-0.5">with activity in 30 days</p>
        </div>
    </div>

    <!-- Activities Done -->
    <div class="luxury-card p-6 bg-[#2C3E35] text-white flex flex-col gap-3 border-none">
        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
            <i class="fas fa-check-circle text-sm"></i>
        </div>
        <div>
            <p class="text-[#A4B4A6] text-xs font-medium uppercase tracking-wider">Activities Done</p>
            <h3 class="text-3xl font-serif mt-1"><?php echo number_format($summary['total_completed'] ?? 0); ?></h3>
            <p class="text-xs text-[#A4B4A6] mt-0.5">last 30 days</p>
        </div>
    </div>

    <!-- Activities Missed -->
    <div class="luxury-card p-6 flex flex-col gap-3">
        <div class="w-10 h-10 rounded-full bg-[#FFEBEE] flex items-center justify-center text-[#E57373]">
            <i class="fas fa-times-circle text-sm"></i>
        </div>
        <div>
            <p class="text-[#6B7C70] text-xs font-medium uppercase tracking-wider">Activities Missed</p>
            <h3 class="text-3xl font-serif text-[#2C3E35] mt-1"><?php echo number_format($summary['total_missed'] ?? 0); ?></h3>
            <p class="text-xs text-[#A4B4A6] mt-0.5">last 30 days</p>
        </div>
    </div>

    <!-- Overall Compliance -->
    <?php
    $compliance = floatval($summary['overall_compliance'] ?? 0);
    $compColor = $compliance >= 80 ? '#4CAF50' : ($compliance >= 50 ? '#F57C00' : '#E57373');
    $compBg    = $compliance >= 80 ? '#E8F5E9' : ($compliance >= 50 ? '#FFF3E0' : '#FFEBEE');
    $compText  = $compliance >= 80 ? '#388E3C' : ($compliance >= 50 ? '#E65100' : '#C62828');
    ?>
    <div class="luxury-card p-6 flex flex-col gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background:<?php echo $compBg; ?>; color:<?php echo $compText; ?>">
            <i class="fas fa-chart-line text-sm"></i>
        </div>
        <div>
            <p class="text-[#6B7C70] text-xs font-medium uppercase tracking-wider">Overall Compliance</p>
            <h3 class="text-3xl font-serif mt-1" style="color:<?php echo $compText; ?>"><?php echo $compliance; ?>%</h3>
            <p class="text-xs text-[#A4B4A6] mt-0.5">activity completion rate</p>
        </div>
    </div>
</div>

<!-- Leaderboard Table -->
<div class="luxury-card overflow-hidden mb-8">
    <div class="px-8 py-6 border-b border-[#EAEAE5] flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
            <h3 class="text-xl font-serif text-[#2C3E35]">Member Leaderboard</h3>
            <p class="text-xs text-[#6B7C70] mt-1">Ranked by 30-day activity compliance rate</p>
        </div>
        <span class="text-xs font-bold bg-[#E3E8E1] text-[#2C3E35] px-3 py-1 rounded-full self-start md:self-auto">
            <?php echo count($leaderboard); ?> Members
        </span>
    </div>

    <?php if (empty($leaderboard)): ?>
        <div class="py-20 text-center text-[#6B7C70]">
            <i class="fas fa-trophy text-4xl text-[#E3E8E1] mb-4 block"></i>
            <p class="font-serif text-lg">No activity data yet</p>
            <p class="text-sm mt-1">Members will appear here once they start logging activities.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#F2F4F1]">
                    <tr>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-widest text-[#6B7C70] w-12">#</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Member</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Type</th>
                        <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Compliance</th>
                        <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Done</th>
                        <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Missed</th>
                        <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Days Active</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Last Active</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold uppercase tracking-widest text-[#6B7C70]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F2F4F1]">
                    <?php foreach ($leaderboard as $i => $m):
                        $rank  = $i + 1;
                        $name  = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: $m['email'];
                        $rate  = floatval($m['compliance_rate'] ?? 0);
                        $rColor = $rate >= 80 ? '#388E3C' : ($rate >= 50 ? '#E65100' : '#C62828');
                        $rBg    = $rate >= 80 ? '#E8F5E9' : ($rate >= 50 ? '#FFF3E0' : '#FFEBEE');
                        $barColor = $rate >= 80 ? '#4CAF50' : ($rate >= 50 ? '#F57C00' : '#E57373');
                        $lastActive = $m['last_active_date']
                            ? date('M j', strtotime($m['last_active_date']))
                            : null;
                        $isActive = ($m['subscription_status'] ?? '') === 'active';
                    ?>
                    <tr class="hover:bg-[#FDFCF8] transition-colors">
                        <td class="px-6 py-4 text-center text-base">
                            <?php if ($i < 3): ?>
                                <span class="text-xl"><?php echo $medals[$i]; ?></span>
                            <?php else: ?>
                                <span class="text-[#A4B4A6] font-bold"><?php echo $rank; ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-[#2C3E35]"><?php echo htmlspecialchars($name); ?></div>
                            <div class="text-xs text-[#6B7C70] truncate max-w-[180px]"><?php echo htmlspecialchars($m['email']); ?></div>
                        </td>
                        <td class="px-6 py-4 text-xs text-[#6B7C70]"><?php echo htmlspecialchars($m['pcos_type'] ?? 'General'); ?></td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="text-sm font-bold" style="color:<?php echo $rColor; ?>"><?php echo $rate; ?>%</span>
                                <div class="w-20 h-1.5 bg-[#F2F4F1] rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" style="width:<?php echo min(100,$rate); ?>%;background:<?php echo $barColor; ?>"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center font-bold text-[#4CAF50]"><?php echo intval($m['completed'] ?? 0); ?></td>
                        <td class="px-6 py-4 text-center text-[#E57373]"><?php echo intval($m['missed'] ?? 0); ?></td>
                        <td class="px-6 py-4 text-center text-[#6B7C70]"><?php echo intval($m['days_active'] ?? 0); ?></td>
                        <td class="px-6 py-4 text-[#6B7C70] text-sm">
                            <?php echo $lastActive ? htmlspecialchars($lastActive) : '<span class="text-[#D1D8D0]">Never</span>'; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($isActive): ?>
                                <span class="px-2.5 py-1 bg-[#E8F5E9] text-[#388E3C] text-[10px] font-bold rounded-full uppercase">Active</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 bg-[#FFEBEE] text-[#E57373] text-[10px] font-bold rounded-full uppercase">Expired</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
