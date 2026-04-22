<?php
declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/includes/db.php';

const PRODUCTS_PER_CATEGORY = 50;
const IMAGE_PROVIDER_PEXELS = 'pexels';
const IMAGE_PROVIDER_UNSPLASH = 'unsplash';

$categoryBlueprints = [
    [
        'name' => 'Electronics',
        'keywords' => ['electronics', 'gadgets', 'smart device'],
        'brands' => ['Sony', 'Samsung', 'JBL', 'boAt', 'Philips', 'Anker', 'Noise'],
        'types' => ['Wireless Bluetooth Headphones', 'Smart LED TV 42 Inch', 'Bluetooth Speaker', 'Smartwatch', 'Power Bank', 'Soundbar'],
        'features' => ['Pro Edition', 'Noise Cancelling', 'Fast Charge', 'Premium Audio', 'Touch Control', 'Long Battery'],
        'description_tags' => ['daily use', 'travel', 'premium finish', 'immersive sound'],
        'price' => ['min' => 1000, 'max' => 50000],
        'stock' => ['min' => 5, 'max' => 80],
        'image_terms' => ['headphones', 'speaker', 'smartwatch', 'television', 'electronics'],
    ],
    [
        'name' => 'Mobiles',
        'keywords' => ['mobile', 'smartphone', 'android phone'],
        'brands' => ['Samsung', 'Xiaomi', 'Realme', 'OnePlus', 'Vivo', 'Oppo', 'Motorola'],
        'types' => ['Android Smartphone', '5G Mobile', 'Camera Phone', 'Gaming Phone', 'Battery Phone', 'Budget Smartphone'],
        'features' => ['8GB RAM', '128GB Storage', '50MP Camera', '5000mAh Battery', 'Fast Charging', '120Hz Display'],
        'description_tags' => ['smooth performance', 'sharp display', 'fast charging', 'great cameras'],
        'price' => ['min' => 5000, 'max' => 80000],
        'stock' => ['min' => 4, 'max' => 60],
        'image_terms' => ['smartphone', 'mobile phone', 'android phone', 'phone'],
    ],
    [
        'name' => 'Fashion',
        'keywords' => ['fashion', 'tshirt', 'clothing'],
        'brands' => ['Roadster', 'H&M', 'Zara', 'Levis', 'Allen Solly', 'Van Heusen', 'Wrogn'],
        'types' => ['Men Casual T-Shirt', 'Women Stylish Kurti', 'Slim Fit Shirt', 'Casual Hoodie', 'Denim Jacket', 'Polo T-Shirt'],
        'features' => ['Classic Fit', 'Summer Wear', 'Minimal Design', 'Street Style', 'Everyday Comfort', 'Premium Fabric'],
        'description_tags' => ['soft fabric', 'modern silhouette', 'all-day comfort', 'easy wash'],
        'price' => ['min' => 200, 'max' => 3000],
        'stock' => ['min' => 10, 'max' => 100],
        'image_terms' => ['fashion clothing', 'tshirt', 'kurti', 'shirt'],
    ],
    [
        'name' => 'Sports',
        'keywords' => ['sports', 'cricket', 'fitness'],
        'brands' => ['SG', 'Nivia', 'Cosco', 'Yonex', 'Boldfit', 'Vector X', 'Adrenex'],
        'types' => ['Cricket Bat Professional', 'Football Training Ball', 'Yoga Mat', 'Badminton Racket', 'Gym Gloves', 'Skipping Rope'],
        'features' => ['Training Edition', 'Premium Grip', 'Lightweight', 'Match Ready', 'All Surface', 'Pro Series'],
        'description_tags' => ['practice sessions', 'game day', 'durable build', 'active training'],
        'price' => ['min' => 300, 'max' => 10000],
        'stock' => ['min' => 5, 'max' => 100],
        'image_terms' => ['cricket bat', 'football', 'yoga mat', 'sports equipment'],
    ],
    [
        'name' => 'Appliances',
        'keywords' => ['appliances', 'kitchen appliance', 'home appliance'],
        'brands' => ['Philips', 'Prestige', 'Bajaj', 'Havells', 'Morphy Richards', 'LG', 'Samsung'],
        'types' => ['Mixer Grinder', 'Air Fryer', 'Microwave Oven', 'Induction Cooktop', 'Vacuum Cleaner', 'Electric Kettle'],
        'features' => ['Energy Efficient', 'Fast Heating', 'Compact Design', 'Digital Control', 'High Power', 'Family Size'],
        'description_tags' => ['modern kitchens', 'daily home use', 'time-saving performance', 'easy operation'],
        'price' => ['min' => 1000, 'max' => 40000],
        'stock' => ['min' => 3, 'max' => 60],
        'image_terms' => ['home appliance', 'microwave oven', 'air fryer', 'electric kettle'],
    ],
    [
        'name' => 'Books',
        'keywords' => ['books', 'reading', 'novel'],
        'brands' => ['Penguin', 'HarperCollins', 'Bloomsbury', 'Rupa', 'Westland', 'Fingerprint', 'Scholastic'],
        'types' => ['Self Help Book', 'Business Strategy Book', 'Motivational Book', 'Thriller Novel', 'Exam Prep Guide', 'Productivity Book'],
        'features' => ['Bestseller', 'Updated Edition', 'Reader Favorite', 'Expert Author', 'Easy Language', 'New Release'],
        'description_tags' => ['daily reading', 'skill building', 'engaging storytelling', 'practical insights'],
        'price' => ['min' => 100, 'max' => 1000],
        'stock' => ['min' => 10, 'max' => 100],
        'image_terms' => ['book cover', 'books', 'novel', 'reading book'],
    ],
];

$imageConfig = [
    'provider' => getenv('PRODUCT_IMAGE_PROVIDER') ?: IMAGE_PROVIDER_PEXELS,
    'pexels_key' => getenv('PEXELS_API_KEY') ?: '',
    'unsplash_key' => getenv('UNSPLASH_ACCESS_KEY') ?: '',
];

try {
    $schema = loadProductSchema($conn);
    $categoryMap = loadCategoryIdMap($conn);

    echo "Products table columns: " . implode(', ', array_keys($schema)) . PHP_EOL;

    foreach ($categoryBlueprints as $blueprint) {
        seedCategoryProducts($conn, $schema, $categoryMap, $blueprint, $imageConfig);
    }

    echo PHP_EOL . "Done. Product generation completed." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Generation failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

function loadProductSchema(PDO $conn): array
{
    $stmt = $conn->query('SHOW COLUMNS FROM products');
    $columns = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = $column;
    }

    if (empty($columns)) {
        throw new RuntimeException('Could not inspect the products table.');
    }

    return $columns;
}

function loadCategoryIdMap(PDO $conn): array
{
    $tablesStmt = $conn->query('SHOW TABLES');
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('categories', $tables, true)) {
        return [];
    }

    $columnsStmt = $conn->query('SHOW COLUMNS FROM categories');
    $columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $nameColumn = null;

    foreach (['name', 'title', 'category_name'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $nameColumn = $candidate;
            break;
        }
    }

    if ($nameColumn === null || !in_array('id', $columns, true)) {
        return [];
    }

    $stmt = $conn->query("SELECT id, {$nameColumn} AS category_name FROM categories");
    $map = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[mb_strtolower(trim((string) $row['category_name']))] = (int) $row['id'];
    }

    return $map;
}

function seedCategoryProducts(PDO $conn, array $schema, array $categoryMap, array $blueprint, array $imageConfig): void
{
    $categoryName = $blueprint['name'];
    $existingCount = countExistingProducts($conn, $schema, $categoryName, $categoryMap);
    $toCreate = max(0, PRODUCTS_PER_CATEGORY - $existingCount);

    echo PHP_EOL . "[{$categoryName}] existing: {$existingCount}, generating: {$toCreate}" . PHP_EOL;

    if ($toCreate === 0) {
        return;
    }

    $existingNames = loadExistingNames($conn, $schema, $categoryName, $categoryMap);
    $inserted = 0;
    $attempts = 0;
    $maxAttempts = PRODUCTS_PER_CATEGORY * 12;

    while ($inserted < $toCreate && $attempts < $maxAttempts) {
        $attempts++;
        $product = buildProductPayload($blueprint, $schema, $categoryMap, $existingNames, $imageConfig);

        if ($product === null) {
            continue;
        }

        insertProduct($conn, $schema, $product);
        $existingNames[mb_strtolower($product['name'])] = true;
        $inserted++;

        echo "  + {$product['name']}" . PHP_EOL;
    }

    if ($inserted < $toCreate) {
        echo "  Warning: generated {$inserted} of {$toCreate} requested products for {$categoryName}." . PHP_EOL;
    }
}

function countExistingProducts(PDO $conn, array $schema, string $categoryName, array $categoryMap): int
{
    if (isset($schema['category'])) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
        $stmt->execute([$categoryName]);
        return (int) $stmt->fetchColumn();
    }

    if (isset($schema['category_id'])) {
        $categoryId = resolveCategoryId($categoryName, $categoryMap);

        if ($categoryId === null) {
            throw new RuntimeException("Category ID not found for {$categoryName}.");
        }

        $stmt = $conn->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        return (int) $stmt->fetchColumn();
    }

    return 0;
}

function loadExistingNames(PDO $conn, array $schema, string $categoryName, array $categoryMap): array
{
    if (isset($schema['category'])) {
        $stmt = $conn->prepare('SELECT name FROM products WHERE category = ?');
        $stmt->execute([$categoryName]);
    } elseif (isset($schema['category_id'])) {
        $categoryId = resolveCategoryId($categoryName, $categoryMap);

        if ($categoryId === null) {
            throw new RuntimeException("Category ID not found for {$categoryName}.");
        }

        $stmt = $conn->prepare('SELECT name FROM products WHERE category_id = ?');
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $conn->query('SELECT name FROM products');
    }

    $names = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $names[mb_strtolower(trim((string) $name))] = true;
    }

    return $names;
}

function buildProductPayload(array $blueprint, array $schema, array $categoryMap, array $existingNames, array $imageConfig): ?array
{
    $title = generateUniqueTitle($blueprint, $existingNames);

    if ($title === null) {
        return null;
    }

    $description = generateDescription($blueprint, $title);
    $price = randomPrice($blueprint['price']['min'], $blueprint['price']['max']);
    $stock = random_int($blueprint['stock']['min'], $blueprint['stock']['max']);
    $image = fetchAndStoreProductImage($title, $blueprint, $imageConfig);

    $payload = [
        'name' => $title,
        'description' => $description,
        'price' => $price,
        'image' => $image,
    ];

    if (isset($schema['category'])) {
        $payload['category'] = $blueprint['name'];
    }

    if (isset($schema['category_id'])) {
        $categoryId = resolveCategoryId($blueprint['name'], $categoryMap);

        if ($categoryId === null) {
            throw new RuntimeException("Category ID not found for {$blueprint['name']}.");
        }

        $payload['category_id'] = $categoryId;
    }

    if (isset($schema['stock'])) {
        $payload['stock'] = $stock;
    } elseif (isset($schema['stock_quantity'])) {
        $payload['stock_quantity'] = $stock;
    } elseif (isset($schema['quantity'])) {
        $payload['quantity'] = $stock;
    }

    if (isset($schema['rating'])) {
        $payload['rating'] = random_int(10, 50) / 10;
    }

    if (isset($schema['discount'])) {
        $payload['discount'] = random_int(0, 35);
    } elseif (isset($schema['discount_percentage'])) {
        $payload['discount_percentage'] = random_int(0, 35);
    }

    if (isset($schema['created_at']) && valueRequired($schema['created_at'])) {
        $payload['created_at'] = date('Y-m-d H:i:s');
    }

    if (isset($schema['updated_at']) && valueRequired($schema['updated_at'])) {
        $payload['updated_at'] = date('Y-m-d H:i:s');
    }

    return $payload;
}

function generateUniqueTitle(array $blueprint, array $existingNames): ?string
{
    for ($i = 0; $i < 40; $i++) {
        $title = trim(sprintf(
            '%s %s %s',
            pickOne($blueprint['brands']),
            pickOne($blueprint['features']),
            pickOne($blueprint['types'])
        ));

        $key = mb_strtolower($title);
        if (!isset($existingNames[$key])) {
            return $title;
        }
    }

    return null;
}

function generateDescription(array $blueprint, string $title): string
{
    $sentences = [
        "{$title} is built for " . pickOne($blueprint['description_tags']) . '.',
        'It delivers reliable performance with a focus on style, comfort, and long-term usability.',
        'A great fit for customers who want value, realistic features, and strong everyday performance.',
    ];

    return implode(' ', $sentences);
}

function randomPrice(int $min, int $max): float
{
    return (float) random_int($min, $max);
}

function pickOne(array $values): string
{
    return $values[array_rand($values)];
}

function resolveCategoryId(string $categoryName, array $categoryMap): ?int
{
    $key = mb_strtolower(trim($categoryName));
    return $categoryMap[$key] ?? null;
}

function valueRequired(array $column): bool
{
    return strtoupper((string) ($column['Null'] ?? 'YES')) === 'NO'
        && strpos(strtolower((string) ($column['Extra'] ?? '')), 'auto_increment') === false
        && ($column['Default'] ?? null) === null;
}

function insertProduct(PDO $conn, array $schema, array $payload): void
{
    $allowedColumns = [];
    $placeholders = [];
    $values = [];

    foreach ($payload as $column => $value) {
        if (isset($schema[$column])) {
            $allowedColumns[] = $column;
            $placeholders[] = '?';
            $values[] = $value;
        }
    }

    if (empty($allowedColumns)) {
        throw new RuntimeException('No compatible columns were found for product insertion.');
    }

    $sql = 'INSERT INTO products (' . implode(', ', $allowedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    $stmt->execute($values);
}

function fetchAndStoreProductImage(string $title, array $blueprint, array $imageConfig): string
{
    $query = pickOne($blueprint['image_terms']);
    $remoteUrl = fetchRemoteImageUrl($query, $imageConfig);

    if ($remoteUrl !== null) {
        return $remoteUrl;
    }

    return createLocalPlaceholderImage($title, $blueprint['name']);
}

function fetchRemoteImageUrl(string $query, array $imageConfig): ?string
{
    $provider = strtolower((string) ($imageConfig['provider'] ?? IMAGE_PROVIDER_PEXELS));

    if ($provider === IMAGE_PROVIDER_PEXELS && !empty($imageConfig['pexels_key'])) {
        return fetchPexelsImageUrl($query, $imageConfig['pexels_key']);
    }

    if ($provider === IMAGE_PROVIDER_UNSPLASH && !empty($imageConfig['unsplash_key'])) {
        return fetchUnsplashImageUrl($query, $imageConfig['unsplash_key']);
    }

    if (!empty($imageConfig['pexels_key'])) {
        return fetchPexelsImageUrl($query, $imageConfig['pexels_key']);
    }

    if (!empty($imageConfig['unsplash_key'])) {
        return fetchUnsplashImageUrl($query, $imageConfig['unsplash_key']);
    }

    return null;
}

function fetchPexelsImageUrl(string $query, string $apiKey): ?string
{
    $url = 'https://api.pexels.com/v1/search?query=' . rawurlencode($query) . '&per_page=1&page=' . random_int(1, 10);
    $response = curlJson($url, [
        'Authorization: ' . $apiKey,
    ]);

    if ($response === null || empty($response['photos'][0]['src']['large'])) {
        return null;
    }

    return (string) $response['photos'][0]['src']['large'];
}

function fetchUnsplashImageUrl(string $query, string $apiKey): ?string
{
    $url = 'https://api.unsplash.com/search/photos?query=' . rawurlencode($query) . '&per_page=1&page=' . random_int(1, 10) . '&orientation=squarish';
    $response = curlJson($url, [
        'Authorization: Client-ID ' . $apiKey,
        'Accept-Version: v1',
    ]);

    if ($response === null || empty($response['results'][0]['urls']['regular'])) {
        return null;
    }

    return (string) $response['results'][0]['urls']['regular'];
}

function curlJson(string $url, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '' || $statusCode >= 400) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function createLocalPlaceholderImage(string $title, string $categoryName): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeCategory = htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8');
    $fileName = 'seed_placeholder_' . time() . '_' . bin2hex(random_bytes(3)) . '.svg';
    $destination = __DIR__ . '/uploads/' . $fileName;

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#10203a"/>
      <stop offset="100%" stop-color="#ff7a18"/>
    </linearGradient>
  </defs>
  <rect width="800" height="800" fill="url(#bg)"/>
  <circle cx="650" cy="140" r="120" fill="rgba(255,255,255,0.15)"/>
  <circle cx="170" cy="640" r="160" fill="rgba(255,255,255,0.12)"/>
  <text x="60" y="150" fill="#ffffff" font-size="42" font-family="Arial, sans-serif">{$safeCategory}</text>
  <text x="60" y="280" fill="#ffffff" font-size="58" font-weight="700" font-family="Arial, sans-serif">{$safeTitle}</text>
  <text x="60" y="360" fill="#ffe7d2" font-size="28" font-family="Arial, sans-serif">Demo image placeholder</text>
</svg>
SVG;

    file_put_contents($destination, $svg);
    return $fileName;
}
