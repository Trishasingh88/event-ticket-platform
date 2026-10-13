
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// TODO: Require an authenticated administrator here.

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['eventx_csrf'])) {
    $_SESSION['eventx_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

$title = '';
$description = '';
$venue = '';
$city = '';
$eventDate = '';
$eventTime = '';
$price = '';
$totalSeats = '100';
$image = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf) ||
        !hash_equals($_SESSION['eventx_csrf'], $csrf)) {
        $error = 'Your session token is invalid. Please refresh and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        $eventTime = $_POST['event_time'] ?? '';
        $price = $_POST['price'] ?? '';
        $totalSeats = $_POST['total_seats'] ?? '100';
        $image = trim($_POST['image'] ?? '');

        $priceValue = filter_var($price, FILTER_VALIDATE_FLOAT);
        $seatCount = filter_var($totalSeats, FILTER_VALIDATE_INT);

        $validDate = DateTime::createFromFormat('!Y-m-d', $eventDate);
        $validTime = DateTime::createFromFormat('!H:i', $eventTime);

        if (
            $title === '' || $venue === '' || $city === '' ||
            !$validDate || $validDate->format('Y-m-d') !== $eventDate ||
            !$validTime || $validTime->format('H:i') !== $eventTime ||
            $priceValue === false || $priceValue < 0 ||
            !$seatCount || $seatCount < 1 || $seatCount > 500
        ) {
            $error = 'Please enter valid event details. Seat count must be between 1 and 500.';
        } elseif (mb_strlen($title) > 255 ||
                  mb_strlen($venue) > 255 ||
                  mb_strlen($city) > 100 ||
                  mb_strlen($image) > 500) {
            $error = 'One or more fields exceed their allowed length.';
        } elseif ($image !== '' &&
                  (!filter_var($image, FILTER_VALIDATE_URL) ||
                   !in_array(strtolower((string)parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $error = 'Please enter a valid HTTP or HTTPS image URL.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO events
                        (title, description, venue, city, event_date,
                         event_time, price, total_seats, image, status)
                    VALUES
                        (:title, :description, :venue, :city, :event_date,
                         :event_time, :price, :total_seats, :image, 'active')
                ");

                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'venue' => $venue,
                    'city' => $city,
                    'event_date' => $eventDate,
                    'event_time' => $eventTime . ':00',
                    'price' => $priceValue,
                    'total_seats' => $seatCount,
                    'image' => $image !== '' ? $image : null
                ]);

                $eventId = (int)$pdo->lastInsertId();

                $seatStmt = $pdo->prepare("
                    INSERT INTO seats
                        (event_id, seat_number, seat_row, seat_type, price)
                    VALUES
                        (:event_id, :seat_number, :seat_row, :seat_type, :price)
                ");

                $columns = range('A', 'J');

                for ($i = 0; $i < $seatCount; $i++) {
                    $rowNumber = intdiv($i, 10) + 1;
                    $columnLetter = $columns[$i % 10];

                    if ($rowNumber <= 2) {
                        $seatType = 'vip';
                        $seatPrice = $priceValue * 2;
                    } elseif ($rowNumber <= 4) {
                        $seatType = 'premium';
                        $seatPrice = $priceValue * 1.5;
                    } else {
                        $seatType = 'regular';
                        $seatPrice = $priceValue;
                    }

                    $seatStmt->execute([
                        'event_id' => $eventId,
                        'seat_number' => $columnLetter . $rowNumber,
                        'seat_row' => (string)$rowNumber,
                        'seat_type' => $seatType,
                        'price' => $seatPrice
                    ]);
                }

                $pdo->commit();

                $_SESSION['eventx_csrf'] = bin2hex(random_bytes(32));
                header('Location: manage-events.php?created=1');
                exit;
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Event creation failed: ' . $ex->getMessage());
                $error = 'Could not create the event. Please check the database and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventX | Add Event</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-white">
<div class="min-h-screen md:flex">
    <aside class="w-full md:w-64 bg-slate-900 border-r border-white/10 p-6">
        <a href="dashboard.php" class="text-2xl font-black">
            <span class="text-indigo-400">Event</span>X
        </a>
        <nav class="mt-10 space-y-2">
            <a href="dashboard.php" class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">Dashboard</a>
            <a href="add-event.php" class="block rounded-xl bg-indigo-600 px-4 py-3 font-semibold">+ Add Event</a>
            <a href="manage-events.php" class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">Manage Events</a>
            <a href="../index.php" class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">View Website ↗</a>
        </nav>
    </aside>

    <main class="min-w-0 flex-1 p-5 md:p-10">
        <div class="max-w-4xl mx-auto">
            <a href="dashboard.php" class="text-sm text-slate-400 hover:text-white">← Back to dashboard</a>
            <h1 class="text-3xl md:text-4xl font-black mt-5">Create an Event</h1>
            <p class="text-slate-400 mt-2">Add an event and initialize its seat inventory.</p>

            <?php if ($error): ?>
                <div role="alert" class="mt-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-red-300">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="mt-8 rounded-3xl border border-white/10 bg-white/[0.03] p-6 md:p-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['eventx_csrf']) ?>">

                <div>
                    <label for="title" class="block text-sm text-slate-300 mb-2">Event title *</label>
                    <input id="title" name="title" required maxlength="255" value="<?= e($title) ?>"
                           class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label for="description" class="block text-sm text-slate-300 mb-2">Description</label>
                    <textarea id="description" name="description" rows="4"
                              class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500"><?= e($description) ?></textarea>
                </div>

                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label for="venue" class="block text-sm text-slate-300 mb-2">Venue *</label>
                        <input id="venue" name="venue" required maxlength="255" value="<?= e($venue) ?>"
                               class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="city" class="block text-sm text-slate-300 mb-2">City *</label>
                        <input id="city" name="city" required maxlength="100" value="<?= e($city) ?>"
                               class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="event_date" class="block text-sm text-slate-300 mb-2">Event date *</label>
                        <input id="event_date" type="date" name="event_date" required value="<?= e($eventDate) ?>"
                               class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="event_time" class="block text-sm text-slate-300 mb-2">Event time *</label>
                        <input id="event_time" type="time" name="event_time" required value="<?= e($eventTime) ?>"
                               class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="price" class="block text-sm text-slate-300 mb-2">Base ticket price (₹) *</label>
                        <input id="price" type="number" name="price" min="0" step="0.01" required value="<?= e($price) ?>"
                               class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="total_seats" class="block text-sm text-slate-300 mb-2">Total seats (1–500) *</label>
                        <input id="total_seats" type="number" name="total_seats" min="1" max="500" required value="<?= e($totalSeats) ?>"
                               class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="image" class="block text-sm text-slate-300 mb-2">Event image URL (optional)</label>
                    <input id="image" type="url" name="image" maxlength="500" value="<?= e($image) ?>"
                           placeholder="https://example.com/event.jpg"
                           class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500">
                </div>

                <div class="rounded-xl bg-indigo-500/10 border border-indigo-500/20 p-4 text-sm text-indigo-200">
                    Seat pricing: first 2 rows VIP (2× base price), next 2 rows premium (1.5×), remaining seats regular.
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-6 py-3 font-bold hover:bg-indigo-500">
                        Create Event & Seats
                    </button>
                    <a href="manage-events.php" class="rounded-xl border border-white/10 px-6 py-3 font-semibold hover:bg-white/5">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
