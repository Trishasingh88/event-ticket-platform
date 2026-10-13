
<?php

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');

try {
    // Fetch a single event by ID if provided
    if (isset($_GET['id'])) {
        $id = filter_var(
            $_GET['id'],
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid event ID.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                description,
                venue,
                city,
                event_date,
                event_time,
                price,
                total_seats,
                image,
                status,
                created_at
            FROM events
            WHERE id = :id
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'Event not found.'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => $event
        ]);
        exit;
    }

    // Pagination
    $page = filter_var(
        $_GET['page'] ?? 1,
        FILTER_VALIDATE_INT
    );

    $limit = filter_var(
        $_GET['limit'] ?? 20,
        FILTER_VALIDATE_INT
    );

    if (!$page || $page < 1 || !$limit || $limit < 1) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Page and limit must be positive integers.'
        ]);
        exit;
    }

    // Prevent excessively large responses
    $limit = min($limit, 100);
    $offset = ($page - 1) * $limit;

    // Optional search by title, city, or venue
    $search = trim($_GET['search'] ?? '');
    $search = mb_substr($search, 0, 100);

    $where = "WHERE status = 'active'";
    $params = [];

    if ($search !== '') {
        $where .= "
            AND (
                title LIKE :title
                OR city LIKE :city
                OR venue LIKE :venue
            )
        ";

        $keyword = '%' . $search . '%';

        $params['title'] = $keyword;
        $params['city'] = $keyword;
        $params['venue'] = $keyword;
    }

    // Count matching events for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM events
        $where
    ");

    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Fetch events
    $sql = "
        SELECT
            id,
            title,
            description,
            venue,
            city,
            event_date,
            event_time,
            price,
            total_seats,
            image,
            status,
            created_at
        FROM events
        $where
        ORDER BY event_date ASC, event_time ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Events fetched successfully.',
        'pagination' => [
            'page' => (int) $page,
            'limit' => (int) $limit,
            'total' => $total,
            'total_pages' => (int) ceil($total / $limit)
        ],
        'data' => $events
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (PDOException $e) {
    error_log('Events API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch events. Please try again later.'
    ]);
}
