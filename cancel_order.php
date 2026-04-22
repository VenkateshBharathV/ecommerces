<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/order_system.php';

ensureOrderSystemSchema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST requests are allowed.',
    ]);
    exit();
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
if ($orderId === false || $orderId === null || $orderId <= 0) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid order id.',
    ]);
    exit();
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Please login to cancel your order.',
    ]);
    exit();
}

$order = getOrderWithItems($conn, $orderId, $userId);
if (!$order) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Order not found for this account.',
    ]);
    exit();
}

$currentStatus = (string) ($order['order_status'] ?? $order['status'] ?? 'Pending');
if (in_array($currentStatus, ['Delivered', 'Cancelled'], true)) {
    http_response_code(409);
    echo json_encode([
        'status' => 'error',
        'message' => 'This order can no longer be cancelled.',
    ]);
    exit();
}

try {
    $conn->beginTransaction();

    if (!updateOrderStatus($conn, $orderId, 'Cancelled', 'Cancelled by customer from My Orders page')) {
        throw new RuntimeException('Unable to update the order status.');
    }

    $legacyStatusColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($legacyStatusColumn) {
        $legacyStmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ? AND user_id = ?');
        $legacyStmt->execute(['Cancelled', $orderId, $userId]);
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Order cancelled successfully.',
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Cancel order failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to cancel the order right now. Please try again.',
    ]);
}
