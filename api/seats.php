
<?php

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');

try {
    // Validate event ID
    $eventId = filter_var(
        $_GET['event_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$eventId || $eventId < 1) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'A valid event_id is required.'
        ]);
        exit;
    }

    // Check that the event exists and is active
    $eventStmt = $pdo->prepare("
        SELECT id, title, venue, event_date, event_time
        FROM events
        WHERE id = :event_id
          AND status = 'active'
        LIMIT 1
    ");

    $eventStmt->execute([
        'event_id' => $eventId
    ]);

    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Event not found or inactive.'
        ]);
        exit;
    }

    /*
     * Release expired locks for this event.
     * Only expired reservations are released.
     */
    $releaseStmt = $pdo->prepare("
        UPDATE seats
        SET status = 'available',
            locked_by = NULL,
            locked_until = NULL
        WHERE event_id = :event_id
          AND status = 'locked'
          AND locked_until <= NOW()
    ");

    $releaseStmt->execute([
        'event_id' => $eventId
    ]);

    /*
     * Fetch all seats for the event.
     */
    $seatStmt = $pdo->prepare("
        SELECT
            id,
            event_id,
            seat_number,
            seat_row,
            seat_type,
            price,
            CASE
                WHEN status = 'locked'
                     AND locked_until <= NOW()
                THEN 'available'
                ELSE status
            END AS status
        FROM seats
        WHERE event_id = :event_id
        ORDER BY seat_row ASC, seat_number ASC
    ");

    $seatStmt->execute([
        'event_id' => $eventId
    ]);

    $seats = $seatStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group seats by row for the seat matrix UI.
    $seatRows = [];

    foreach ($seats as $seat) {
        $row = $seat['seat_row'] ?? 'Other';

        $seatRows[$row][] = [
            'id' => (int) $seat['id'],
            'seat_number' => $seat['seat_number'],
            'seat_type' => $seat['seat_type'],
            'price' => (float) $seat['price'],
            'status' => $seat['status']
        ];
    }

    // Calculate availability counts.
    $available = 0;
    $locked = 0;
    $booked = 0;

    foreach ($seats as $seat) {
        switch ($seat['status']) {
            case 'available':
                $available++;
                break;

            case 'locked':
                $locked++;
                break;

            case 'booked':
                $booked++;
                break;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Seats fetched successfully.',
        'event' => $event,
        'summary' => [
            'total_seats' => count($seats),
            'available' => $available,
            'locked' => $locked,
            'booked' => $booked
        ],
        'rows' => $seatRows,
        'data' => $seats
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (PDOException $e) {
    error_log('Seats API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch seats. Please try again later.'
    ]);
}
