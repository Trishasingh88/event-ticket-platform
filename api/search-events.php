<?php

require_once "../config/database.php";

header("Content-Type: application/json");

$query =
trim($_GET['q'] ?? '');

$limit = 20;


if ($query === '') {

$stmt = $pdo->prepare("
    SELECT *
    FROM events
    WHERE status = 'active'
    ORDER BY event_date ASC
    LIMIT ?
");

$stmt->bindValue(
    1,
    $limit,
    PDO::PARAM_INT
);

$stmt->execute();

}

else {

$search =
"%{$query}%";


$stmt = $pdo->prepare("
    SELECT *
    FROM events

    WHERE status = 'active'

    AND (
        title LIKE ?
        OR city LIKE ?
        OR venue LIKE ?
    )

    ORDER BY event_date ASC

    LIMIT ?
");


$stmt->bindValue(
    1,
    $search
);

$stmt->bindValue(
    2,
    $search
);

$stmt->bindValue(
    3,
    $search
);

$stmt->bindValue(
    4,
    $limit,
    PDO::PARAM_INT
);

$stmt->execute();

}


echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);