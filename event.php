<?php

require_once "config/database.php";

$eventId = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT *
    FROM events
    WHERE id = ?
");

$stmt->execute([$eventId]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found.");
}


/*
Generate seats automatically
*/

$seatCheck = $pdo->prepare("
    SELECT COUNT(*)
    FROM seats
    WHERE event_id = ?
");

$seatCheck->execute([$eventId]);

$seatCount = $seatCheck->fetchColumn();


if ($seatCount == 0) {

    $insertSeat = $pdo->prepare("
        INSERT INTO seats
        (
            event_id,
            seat_number,
            seat_row,
            seat_type,
            price
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    for ($row = 1; $row <= 10; $row++) {

        foreach (range('A', 'J') as $letter) {

            $seatNumber = $letter . $row;

            $type = "regular";
            $price = $event['price'];

            if ($row <= 2) {
                $type = "vip";
                $price = $event['price'] * 2;
            }

            elseif ($row <= 4) {
                $type = "premium";
                $price = $event['price'] * 1.5;
            }

            $insertSeat->execute([
                $eventId,
                $seatNumber,
                $row,
                $type,
                $price
            ]);
        }
    }
}


$stmt = $pdo->prepare("
    SELECT *
    FROM seats
    WHERE event_id = ?
    ORDER BY id
");

$stmt->execute([$eventId]);

$seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($event['title']) ?></title>

<script src="https://cdn.tailwindcss.com"></script>

</head>


<body class="bg-slate-950 text-white">


<div class="max-w-7xl mx-auto px-6 py-10">


<a href="index.php"
class="text-slate-400 hover:text-white">

← Back to events

</a>


<div class="mt-8">

<p class="text-indigo-400 font-semibold">
SELECT YOUR SEATS
</p>

<h1 class="text-4xl font-black mt-2">

<?= htmlspecialchars($event['title']) ?>

</h1>

<p class="text-slate-400 mt-3">

📍 <?= htmlspecialchars($event['venue']) ?>

</p>

</div>


<div class="grid lg:grid-cols-[1fr_350px]
            gap-10 mt-12">


<!-- SEAT AREA -->

<div class="bg-white/[0.03]
            border border-white/10
            rounded-3xl p-8">


<div class="max-w-xl mx-auto">


<div class="bg-gradient-to-r
            from-indigo-600
            to-purple-600
            rounded-xl py-3
            text-center mb-12">

STAGE

</div>


<div id="seatGrid"
     class="grid grid-cols-10 gap-2">

<?php foreach ($seats as $seat): ?>

<button
type="button"

data-id="<?= $seat['id'] ?>"
data-seat="<?= $seat['seat_number'] ?>"
data-price="<?= $seat['price'] ?>"

class="seat
<?= $seat['status'] === 'booked'
    ? 'bg-red-500/20 text-red-400 cursor-not-allowed'
    : 'bg-slate-800 hover:bg-indigo-600'
?> rounded-lg py-3 text-xs"

<?= $seat['status'] === 'booked'
    ? 'disabled'
    : ''
?>>

<?= htmlspecialchars($seat['seat_number']) ?>

</button>

<?php endforeach; ?>

</div>


<div class="flex flex-wrap
            justify-center gap-6
            mt-10 text-xs text-slate-400">

<div>
<span class="inline-block w-3 h-3
             bg-slate-800 rounded mr-2">
</span>
Available
</div>

<div>
<span class="inline-block w-3 h-3
             bg-indigo-600 rounded mr-2">
</span>
Selected
</div>

<div>
<span class="inline-block w-3 h-3
             bg-red-500/30 rounded mr-2">
</span>
Booked
</div>

</div>

</div>

</div>


<!-- BOOKING SUMMARY -->

<aside class="bg-white/[0.04]
              border border-white/10
              rounded-3xl p-7
              h-fit">

<h2 class="text-xl font-bold">
Booking Summary
</h2>


<div class="mt-6">

<p class="text-sm text-slate-400">
Selected Seats
</p>

<div id="selectedSeats"
     class="mt-3 text-slate-300">

No seats selected

</div>

</div>


<div class="border-t border-white/10
            mt-7 pt-6">

<div class="flex justify-between">

<span class="text-slate-400">
Total
</span>

<strong id="totalPrice"
        class="text-2xl">

₹0

</strong>

</div>

</div>


<button
id="continueBtn"

class="w-full bg-indigo-600
       hover:bg-indigo-500
       rounded-xl py-4
       mt-7 font-bold
       disabled:opacity-40">

Continue

</button>

</aside>

</div>

</div>


<script>

const selected = [];

const buttons =
document.querySelectorAll(".seat");

const selectedContainer =
document.getElementById("selectedSeats");

const totalContainer =
document.getElementById("totalPrice");

const continueBtn =
document.getElementById("continueBtn");


buttons.forEach(button => {

button.addEventListener("click", () => {

const id = button.dataset.id;

const index =
selected.findIndex(
seat => seat.id === id
);


if (index !== -1) {

selected.splice(index, 1);

button.classList.remove(
"bg-indigo-600"
);

button.classList.add(
"bg-slate-800"
);

}

else {

selected.push({
id: id,
seat: button.dataset.seat,
price: Number(button.dataset.price)
});

button.classList.remove(
"bg-slate-800"
);

button.classList.add(
"bg-indigo-600"
);

}


updateSummary();

});

});


function updateSummary() {

if (!selected.length) {

selectedContainer.innerHTML =
"No seats selected";

continueBtn.disabled = true;

}

else {

selectedContainer.innerHTML =
selected.map(
seat => seat.seat
).join(", ");

continueBtn.disabled = false;

}


const total =
selected.reduce(
(sum, seat) => sum + seat.price,
0
);

totalContainer.innerText =
"₹" + total.toLocaleString();

}


continueBtn.addEventListener(
"click",
async () => {

if (!selected.length) return;

const seatIds =
selected.map(
seat => seat.id
);

const response =
await fetch(
"api/lock-seats.php",
{
method: "POST",

headers: {
"Content-Type":
"application/json"
},

body: JSON.stringify({
event_id: <?= $eventId ?>,
seat_ids: seatIds
})

});


const data =
await response.json();


if (data.success) {

sessionStorage.setItem(
"selectedSeats",
JSON.stringify(selected)
);

sessionStorage.setItem(
"bookingId",
data.booking_id
);

window.location.href =
"checkout.php";

}

else {

alert(data.message);

location.reload();

}

});

</script>

</body>
</html>