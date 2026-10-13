<?php
require_once "config/database.php";

$stmt = $pdo->query("
    SELECT *
    FROM events
    WHERE status = 'active'
    ORDER BY event_date ASC
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>EventX — Event Tickets</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-slate-950 text-white">

<!-- NAVBAR -->

<nav class="border-b border-white/10 bg-slate-950/90 backdrop-blur">

<div class="max-w-7xl mx-auto px-6 py-5
            flex items-center justify-between">

<h1 class="text-2xl font-black">

<span class="text-indigo-400">Event</span>X

</h1>

<div class="hidden md:flex gap-8 text-sm text-slate-300">

<a href="#" class="hover:text-white">Events</a>
<a href="#" class="hover:text-white">My Tickets</a>
<a href="#" class="hover:text-white">About</a>

</div>

<button class="bg-indigo-600 px-5 py-2 rounded-xl">
Login
</button>

</div>

</nav>


<!-- HERO -->

<section class="relative overflow-hidden">

<div class="absolute inset-0
            bg-gradient-to-br
            from-indigo-900/40
            via-purple-900/20
            to-transparent">
</div>

<div class="relative max-w-7xl mx-auto
            px-6 py-20">

<p class="text-indigo-400 font-semibold mb-4">
LIVE EVENTS • EASY BOOKING
</p>

<h2 class="text-5xl md:text-7xl font-black
           max-w-4xl leading-tight">

Experience Events.
<br>

<span class="text-transparent bg-clip-text
             bg-gradient-to-r
             from-indigo-400 to-purple-400">

Book Your Seat.

</span>

</h2>

<p class="text-slate-400 text-lg mt-6 max-w-xl">

Discover concerts, conferences, comedy shows
and exclusive events around you.

</p>

<!-- SEARCH -->

<div class="mt-10 max-w-2xl">

<div class="relative">

<input
id="searchInput"
type="text"
placeholder="Search events, cities or venues..."
class="w-full bg-white/5
       border border-white/10
       rounded-2xl
       px-6 py-5
       outline-none
       focus:border-indigo-500
       transition">

<span class="absolute right-5 top-5 text-xl">
⌕
</span>

</div>

</div>

</div>

</section>


<!-- EVENTS -->

<section class="max-w-7xl mx-auto px-6 py-16">

<div class="flex justify-between items-center mb-10">

<div>

<p class="text-indigo-400 text-sm font-semibold">
DISCOVER
</p>

<h3 class="text-3xl font-bold mt-2">
Upcoming Events
</h3>

</div>

<span id="eventCount"
      class="text-slate-500">
<?= count($events) ?> Events
</span>

</div>


<div id="eventGrid"
     class="grid md:grid-cols-2 lg:grid-cols-3 gap-7">

<?php foreach ($events as $event): ?>

<article
class="event-card group bg-white/[0.04]
       border border-white/10
       rounded-3xl overflow-hidden
       hover:-translate-y-2
       transition duration-300"
data-search="<?= strtolower(
    htmlspecialchars(
        $event['title']." ".
        $event['city']." ".
        $event['venue']
    )
) ?>">

<div class="h-52 overflow-hidden">

<img
src="<?= htmlspecialchars($event['image']) ?>"
class="w-full h-full object-cover
       group-hover:scale-110 transition duration-500">

</div>


<div class="p-6">

<div class="flex justify-between items-start">

<div>

<h4 class="text-xl font-bold">

<?= htmlspecialchars($event['title']) ?>

</h4>

<p class="text-slate-400 text-sm mt-2">

📍 <?= htmlspecialchars($event['venue']) ?>

</p>

</div>

<span class="text-indigo-400 font-bold">

₹<?= number_format($event['price']) ?>

</span>

</div>


<div class="flex justify-between
            text-sm text-slate-400 mt-6">

<span>
📅 <?= date(
    "d M Y",
    strtotime($event['event_date'])
) ?>
</span>

<span>
⏰ <?= date(
    "h:i A",
    strtotime($event['event_time'])
) ?>
</span>

</div>


<a
href="event.php?id=<?= $event['id'] ?>"
class="block text-center
       bg-indigo-600 hover:bg-indigo-500
       rounded-xl py-3 mt-6
       font-semibold transition">

Select Seats →

</a>

</div>

</article>

<?php endforeach; ?>

</div>

</section>


<script src="assets/js/app.js"></script>

</body>
</html>