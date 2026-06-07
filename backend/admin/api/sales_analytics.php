<?php
require_once '../auth.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
header('Content-Type: application/json');

// Get time range filter
$range = $_GET['range'] ?? '30d';

// Calculate start date
$startDate = new DateTime();
if ($range === '7d') {
    $startDate->modify('-7 days');
} elseif ($range === '90d') {
    $startDate->modify('-90 days');
} else {
    $startDate->modify('-30 days'); // Default 30d
}
$startTimestamp = $startDate->getTimestamp();

$sales = $db->getSales();

// Filter and Aggregate
$filteredSales = [];
$totalRevenue = 0;
$totalOrders = 0;
$revenueByMonth = [];
$counts = ['completed' => 0, 'pending' => 0, 'failed' => 0, 'refunded' => 0];

// Multi-currency Breakdowns
$revenueBreakdown = [];
$totalOrdersBreakdown = [];
$avgOrderValueBreakdown = [];
$revenueByMonthBreakdown = [];

foreach ($sales as $s) {
    $date = strtotime($s['created_at']);

    // Filter by date range
    if ($date < $startTimestamp)
        continue;

    $filteredSales[] = $s;
    $status = $s['payment_status'] ?? 'pending';

    // Normalize status for counts
    if ($status === 'successful')
        $status = 'completed';
    if (!isset($counts[$status]))
        $counts[$status] = 0;
    $counts[$status]++;

    if ($status === 'completed') {
        $amount = (float) ($s['amount'] ?? 0);
        $currency = strtoupper($s['currency'] ?? 'NGN');
        if (empty($currency)) $currency = 'NGN';

        $totalRevenue += $amount;
        $totalOrders++;

        // Multi-currency breakdowns
        if (!isset($revenueBreakdown[$currency])) {
            $revenueBreakdown[$currency] = 0;
            $totalOrdersBreakdown[$currency] = 0;
        }
        $revenueBreakdown[$currency] += $amount;
        $totalOrdersBreakdown[$currency]++;

        if ($range === '7d') {
            $key = date('D d', $date); // Mon 01
        } else {
            $key = date('M d', $date); // Jan 01
        }

        if (!isset($revenueByMonth[$key]))
            $revenueByMonth[$key] = 0;
        $revenueByMonth[$key] += $amount;

        // Month breakdown
        if (!isset($revenueByMonthBreakdown[$currency])) {
            $revenueByMonthBreakdown[$currency] = [];
        }
        if (!isset($revenueByMonthBreakdown[$currency][$key])) {
            $revenueByMonthBreakdown[$currency][$key] = 0;
        }
        $revenueByMonthBreakdown[$currency][$key] += $amount;
    }
}

// Reverse chart data chronological order for chart display
$revChartData = array_reverse($revenueByMonth);

$revChartDataBreakdown = [];
foreach ($revenueByMonthBreakdown as $currency => $monthData) {
    $revChartDataBreakdown[$currency] = array_reverse($monthData);
}

// Calculate averages
foreach ($revenueBreakdown as $currency => $totalAmt) {
    $count = $totalOrdersBreakdown[$currency];
    $avgOrderValueBreakdown[$currency] = $count > 0 ? $totalAmt / $count : 0;
}

$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

echo json_encode([
    'totalRevenue' => $totalRevenue,
    'totalOrders' => $totalOrders,
    'avgOrderValue' => $avgOrderValue,
    'revenueByMonth' => $revChartData,
    'revenueByMonthBreakdown' => $revChartDataBreakdown,
    'revenueBreakdown' => $revenueBreakdown,
    'totalOrdersBreakdown' => $totalOrdersBreakdown,
    'avgOrderValueBreakdown' => $avgOrderValueBreakdown,
    'counts' => $counts
]);

