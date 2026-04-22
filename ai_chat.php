<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/services/SearchService.php';
require_once __DIR__ . '/includes/services/RecommendationService.php';
require_once __DIR__ . '/includes/order_system.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST requests are allowed.',
    ]);
    exit();
}

$payload = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($payload['message'] ?? ''));

if ($message === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'Please type a message.',
    ]);
    exit();
}

$searchService = new SearchService();
$recommendationService = new RecommendationService();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$appBase = $appBase === '' ? '' : $appBase;

if (!isset($_SESSION['ai_chat_history']) || !is_array($_SESSION['ai_chat_history'])) {
    $_SESSION['ai_chat_history'] = [];
}

$_SESSION['ai_chat_history'][] = [
    'role' => 'user',
    'message' => $message,
    'time' => date('H:i'),
];
$_SESSION['ai_chat_history'] = array_slice($_SESSION['ai_chat_history'], -12);

$searchData = $searchService->searchProducts($conn, $message, 6);
$products = $searchData['products'];
$intent = $searchData['intent'];
$normalized = mb_strtolower($message);

$reply = 'I found a few useful matches for you.';
$cards = [];
$meta = [];

if (preg_match('/\b(order|track|tracking|status|where is my order)\b/i', $message)) {
    if ($userId === null) {
        $reply = 'Please login to track your orders. After login, ask me things like "track my latest order".';
    } else {
        $requestedOrderId = null;
        if (preg_match('/#?(\d{1,10})/', $message, $matches)) {
            $requestedOrderId = (int) $matches[1];
        }

        if ($requestedOrderId !== null && $requestedOrderId > 0) {
            $orderStmt = $conn->prepare('SELECT id, order_status, total, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
            $orderStmt->execute([$requestedOrderId, $userId]);
        } else {
            $orderStmt = $conn->prepare('SELECT id, order_status, total, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1');
            $orderStmt->execute([$userId]);
        }

        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $reply = sprintf(
                'Order #%d is currently %s. You can open the tracking page for live status updates.',
                (int) $order['id'],
                (string) ($order['order_status'] ?? 'Pending')
            );
            $meta['track_url'] = $appBase . '/pages/track_order.php?order_id=' . (int) $order['id'];
            $meta['order'] = [
                'id' => (int) $order['id'],
                'status' => (string) ($order['order_status'] ?? 'Pending'),
                'total' => (float) ($order['total'] ?? 0),
                'created_at' => (string) ($order['created_at'] ?? ''),
            ];
        } else {
            $reply = 'I could not find a matching order for your account yet.';
        }
    }
} elseif (preg_match('/\b(recommend|similar|also like|suggest)\b/i', $message)) {
    $category = $intent['category'];

    if ($category === null && !empty($products[0]['category'])) {
        $category = (string) $products[0]['category'];
    }

    if ($category !== null) {
        $cards = $recommendationService->getSimilarProducts($conn, $category, [], 4);
        $reply = 'You may also like these ' . $category . ' picks.';
    } else {
        $cards = $recommendationService->getRecommendedProducts($conn, $userId, 4);
        $reply = 'Here are some premium picks you may also like.';
    }
} elseif (preg_match('/\b(offer|offers|deal|deals|discount)\b/i', $message)) {
    $offerStmt = $conn->query('SELECT id, name, description, category, price, image FROM products ORDER BY price ASC, id DESC LIMIT 4');
    $cards = $offerStmt->fetchAll(PDO::FETCH_ASSOC);
    $reply = 'These are great low-price options from the current catalog.';
} elseif (!empty($products)) {
    $cards = $products;

    $replyParts = [];
    if (!empty($intent['category'])) {
        $replyParts[] = 'in ' . $intent['category'];
    }
    if ($intent['max_price'] !== null) {
        $replyParts[] = 'under Rs ' . number_format((float) $intent['max_price'], 0);
    }
    if ($intent['min_price'] !== null) {
        $replyParts[] = 'above Rs ' . number_format((float) $intent['min_price'], 0);
    }

    $reply = 'I found ' . count($cards) . ' product match(es)' . ($replyParts ? ' ' . implode(' ', $replyParts) : '') . '.';

    $primaryCategory = (string) ($cards[0]['category'] ?? '');
    if ($primaryCategory !== '') {
        $similar = $recommendationService->getSimilarProducts(
            $conn,
            $primaryCategory,
            array_map(static fn(array $product): int => (int) $product['id'], $cards),
            2
        );
        $cards = array_merge($cards, $similar);
    }
} else {
    $cards = $recommendationService->getRecommendedProducts($conn, $userId, 4);
    $reply = 'I could not find an exact match, so I picked a few strong alternatives for you.';
}

$responseCards = array_map(
    static function (array $product) use ($appBase): array {
        $image = trim((string) ($product['image'] ?? ''));

        if ($image === '') {
            $image = $appBase . '/uploads/default.png';
        } elseif (!preg_match('/^(https?:)?\/\//i', $image) && strpos($image, 'data:image/') !== 0) {
            $uploadPath = __DIR__ . '/uploads/' . $image;
            $adminImagePath = __DIR__ . '/admin/images/' . $image;

            if (file_exists($uploadPath)) {
                $image = $appBase . '/uploads/' . rawurlencode($image);
            } elseif (file_exists($adminImagePath)) {
                $image = $appBase . '/admin/images/' . rawurlencode($image);
            } else {
                $image = $appBase . '/uploads/default.png';
            }
        }

        return [
            'id' => (int) ($product['id'] ?? 0),
            'name' => (string) ($product['name'] ?? 'Product'),
            'description' => (string) ($product['description'] ?? 'No description available.'),
            'category' => (string) ($product['category'] ?? 'Store'),
            'price' => (float) ($product['price'] ?? 0),
            'image' => $image,
            'highlight' => true,
        ];
    },
    array_slice(uniqueProducts($cards), 0, 6)
);

$_SESSION['ai_chat_history'][] = [
    'role' => 'assistant',
    'message' => $reply,
    'time' => date('H:i'),
];
$_SESSION['ai_chat_history'] = array_slice($_SESSION['ai_chat_history'], -12);

echo json_encode([
    'status' => 'success',
    'reply' => $reply,
    'products' => $responseCards,
    'meta' => $meta,
    'history' => $_SESSION['ai_chat_history'],
    'quick_suggestions' => ['Shoes under 1000', 'Mobiles under 15000', 'Show today offers'],
    'debug_intent' => [
        'category' => $intent['category'],
        'min_price' => $intent['min_price'],
        'max_price' => $intent['max_price'],
        'has_order_context' => str_contains($normalized, 'order') || str_contains($normalized, 'track'),
    ],
]);

function uniqueProducts(array $products): array
{
    $seen = [];
    $unique = [];

    foreach ($products as $product) {
        $id = (int) ($product['id'] ?? 0);
        if ($id > 0 && isset($seen[$id])) {
            continue;
        }

        if ($id > 0) {
            $seen[$id] = true;
        }

        $unique[] = $product;
    }

    return $unique;
}
