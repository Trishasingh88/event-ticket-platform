<?php

require_once "../config/database.php";

header("Content-Type: application/json");

$data =
json_decode(
file_get_contents("php://input"),
true
);

$eventId =
intval($data['event_id'] ?? 0);

$seatIds =
$data['seat_ids'] ?? [];


if (!$eventId || empty($seatIds)) {

echo json_encode([
    "success" => false,
    "message" => "Invalid seat selection."
]);

exit;

}


try {

$pdo->beginTransaction();


/*
Remove expired locks
*/

$pdo->exec("
    UPDATE seats

    SET status = 'available',
        locked_by = NULL,
        locked_until = NULL

    WHERE status = 'locked'

    AND locked_until < NOW()
");


/*
FOR UPDATE locks selected rows.

This prevents two transactions from
booking the same seat simultaneously.
*/

$placeholders =
implode(
    ',',
    array_fill(
        0,
        count($seatIds),
        '?'
    )
);


$sql = "
SELECT *

FROM seats

WHERE event_id = ?

AND id IN ($placeholders)

FOR UPDATE
";


$params =
array_merge(
    [$eventId],
    $seatIds
);


$stmt =
$pdo->prepare($sql);

$stmt->execute($params);

$seats =
$stmt->fetchAll(PDO::FETCH_ASSOC);


if (count($seats) !== count($seatIds)) {

throw new Exception(
    "One or more seats are invalid."
);

}


/*
Check availability
*/

foreach ($seats as $seat) {

if ($seat['status'] !== 'available') {

throw new Exception(
    "Seat {$seat['seat_number']} is no longer available."
);

}

}


/*
Temporary lock
*/

$lockToken =
bin2hex(random_bytes(16));

$lockUntil =
date(
    'Y-m-d H:i:s',
    time() + 300
);


$update =
$pdo->prepare("
    UPDATE seats

    SET status = 'locked',
        locked_by = ?,
        locked_until = ?

    WHERE id = ?
");


foreach ($seatIds as $seatId) {

$update->execute([
    $lockToken,
    $lockUntil,
    $seatId
]);

}


$pdo->commit();


echo json_encode([

    "success" => true,

    "message" =>
        "Seats locked successfully.",

    "booking_id" =>
        $lockToken,

    "expires_at" =>
        $lockUntil

]);


}

catch (Exception $e) {

if ($pdo->inTransaction()) {

$pdo->rollBack();

}


echo json_encode([

    "success" => false,

    "message" => $e->getMessage()

]);

}