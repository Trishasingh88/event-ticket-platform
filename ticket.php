<?php

require_once "config/database.php";

$name =
trim($_POST['name'] ?? '');

$email =
trim($_POST['email'] ?? '');

$phone =
trim($_POST['phone'] ?? '');

$bookingToken =
$_POST['booking_id'] ?? '';


if (!$name || !$email || !$bookingToken) {

die("Invalid booking.");

}


try {

$pdo->beginTransaction();


/*
Find locked seats
*/

$stmt = $pdo->prepare("
    SELECT *

    FROM seats

    WHERE locked_by = ?

    AND status = 'locked'

    AND locked_until > NOW()

    FOR UPDATE
");

$stmt->execute([
    $bookingToken
]);

$seats =
$stmt->fetchAll(PDO::FETCH_ASSOC);


if (!$seats) {

throw new Exception(
    "Your seat reservation has expired."
);

}


$eventId =
$seats[0]['event_id'];


$total = 0;


foreach ($seats as $seat) {

$total +=
floatval($seat['price']);

}


/*
Generate booking reference
*/

$reference =
"EVT-" .
date("Ymd") .
"-" .
strtoupper(
    substr(
        bin2hex(
            random_bytes(5)
        ),
        0,
        8
    )
);


/*
Create booking
*/

$stmt = $pdo->prepare("
    INSERT INTO bookings

    (
        booking_reference,
        event_id,
        customer_name,
        customer_email,
        customer_phone,
        total_amount,
        status
    )

    VALUES (?, ?, ?, ?, ?, ?, 'confirmed')
");


$stmt->execute([

    $reference,
    $eventId,
    $name,
    $email,
    $phone,
    $total

]);


$bookingId =
$pdo->lastInsertId();


/*
Attach seats
*/

$bookingSeat =
$pdo->prepare("
    INSERT INTO booking_seats

    (
        booking_id,
        seat_id,
        price
    )

    VALUES (?, ?, ?)
");


$updateSeat =
$pdo->prepare("
    UPDATE seats

    SET status = 'booked',
        locked_by = NULL,
        locked_until = NULL

    WHERE id = ?
");


foreach ($seats as $seat) {

$bookingSeat->execute([

    $bookingId,
    $seat['id'],
    $seat['price']

]);


$updateSeat->execute([
    $seat['id']
]);

}


$pdo->commit();

}

catch (Exception $e) {

if ($pdo->inTransaction()) {

$pdo->rollBack();

}

die($e->getMessage());

}


/*
Get event
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM events
    WHERE id = ?
");

$stmt->execute([
    $eventId
]);

$event =
$stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Ticket <?= $reference ?></title>

<script src="https://cdn.tailwindcss.com"></script>

</head>


<body class="bg-slate-950
             text-white
             min-h-screen
             flex items-center
             justify-center px-6">


<div class="w-full max-w-xl">


<div class="bg-white
            text-slate-900
            rounded-3xl
            overflow-hidden
            shadow-2xl">


<div class="bg-indigo-600
            text-white
            p-8">

<p class="uppercase
          tracking-widest
          text-sm
          opacity-80">

EventX Ticket

</p>

<h1 class="text-3xl
           font-black mt-2">

Booking Confirmed 🎉

</h1>

</div>


<div class="p-8">


<div class="flex
            justify-between
            gap-5">

<div>

<p class="text-slate-500 text-sm">
Event
</p>

<h2 class="text-xl font-bold">

<?= htmlspecialchars(
    $event['title']
) ?>

</h2>

</div>


<div class="text-right">

<p class="text-slate-500 text-sm">
Booking ID
</p>

<strong>
<?= $reference ?>
</strong>

</div>

</div>


<div class="grid grid-cols-2
            gap-6 mt-8">


<div>

<p class="text-slate-500 text-sm">
Date
</p>

<strong>

<?= date(
    "d M Y",
    strtotime(
        $event['event_date']
    )
) ?>

</strong>

</div>


<div>

<p class="text-slate-500 text-sm">
Time
</p>

<strong>

<?= date(
    "h:i A",
    strtotime(
        $event['event_time']
    )
) ?>

</strong>

</div>


<div>

<p class="text-slate-500 text-sm">
Venue
</p>

<strong>

<?= htmlspecialchars(
    $event['venue']
) ?>

</strong>

</div>


<div>

<p class="text-slate-500 text-sm">
Seats
</p>

<strong>

<?= implode(
    ", ",
    array_column(
        $seats,
        "seat_number"
    )
) ?>

</strong>

</div>

</div>


<div class="border-t
            border-dashed
            mt-8 pt-6
            flex justify-between">

<span>
Total Paid
</span>

<strong class="text-2xl">

₹<?= number_format($total) ?>

</strong>

</div>


<button
onclick="window.print()"
class="w-full mt-7
       bg-slate-900
       text-white
       py-4
       rounded-xl
       font-bold">

Print / Save Ticket

</button>


</div>

</div>


<p class="text-center
          text-slate-500
          text-sm mt-6">

Ticket Reference:
<?= $reference ?>

</p>

</div>

</body>

</html>