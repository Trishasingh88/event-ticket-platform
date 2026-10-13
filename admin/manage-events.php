
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['eventx_csrf'], $token)) {
        $_SESSION['eventx_flash'] = [
            'type' => 'error',
            'message' => 'Invalid session token. Please try again.'
        ];
    } else {
        $eventId = filter_var(
            $_POST['event_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $action = $_POST['action'] ?? '';

        try {
            if (!$eventId || $eventId < 1) {
                throw new RuntimeException('Invalid event ID.');
            }

            if ($action === 'toggle') {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT id, status
                    FROM events
                    WHERE id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$eventId]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$event) {
                    throw new RuntimeException('Event not found.');
                }

                $newStatus = $event['status'] === 'active'
                    ? 'inactive'
                    : 'active';

                // Do not deactivate an event with an active seat lock.
                if ($newStatus === 'inactive') {
                    $lockCheck = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM seats
                        WHERE event_id = ?
                          AND status = 'locked'
                          AND locked_until > NOW()
                    ");
                    $lockCheck->execute([$eventId]);

                    if ((int)$lockCheck->fetchColumn() > 0) {
                        throw new RuntimeException(
                            'This event has active seat reservations. Try again after they expire.'
                        );
                    }
                }

                $update = $pdo->prepare("
                    UPDATE events SET status = ? WHERE id = ?
                ");
                $update->execute([$newStatus, $eventId]);

                $pdo->commit();

                $_SESSION['eventx_flash'] = [
                    'type' => 'success',
                    'message' => 'Event status changed to ' . $newStatus . '.'
                ];
            } elseif ($action === 'delete') {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT id FROM events WHERE id = ? FOR UPDATE
                ");
                $stmt->execute([$eventId]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException('Event not found.');
                }

                $bookingCheck = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM bookings
                    WHERE event_id = ?
                ");
                $bookingCheck->execute([$eventId]);

                if ((int)$bookingCheck->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'This event has booking records and cannot be deleted. Deactivate it instead.'
                    );
                }

                $delete = $pdo->prepare("
                    DELETE FROM events WHERE id = ?
                ");
                $delete->execute([$eventId]);

                $pdo->commit();

                $_SESSION['eventx_flash'] = [
                    'type' => 'success',
                    'message' => 'Event and its seat inventory were deleted.'
                ];
            } else {
                throw new RuntimeException('Unsupported action.');
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['eventx_flash'] = [
                'type' => 'error',
                'message' => $ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'The operation failed. Please try again.'
            ];

            if (!($ex instanceof RuntimeException)) {
                error_log('Event management error: ' . $ex->getMessage());
            }
        }
    }

    header('Location: manage-events.php');
    exit;
}

$flash = $_SESSION['eventx_flash'] ?? null;
unset($_SESSION['eventx_flash']);

$search = trim($_GET['search'] ?? '');
$search = mb_substr($search, 0, 100);

$sql = "
    SELECT
        e.id,
        e.title,
        e.venue,
        e.city,
        e.event_date,
        e.event_time,
        e.price,
        e.total_seats,
        e.status,
        e.created_at,
        (SELECT COUNT(*)
         FROM seats s
         WHERE s.event_id = e.id AND s.status = 'booked'
        ) AS booked_seats,
        (SELECT COUNT(*)
         FROM bookings b
         WHERE b.event_id = e.id
        ) AS booking_count
    FROM events e
";

$params = [];

if ($search !== '') {
    $sql .= "
        WHERE e.title LIKE :title
           OR e.city LIKE :city
           OR e.venue LIKE :venue
    ";

    $keyword = '%' . $search . '%';
    $params = [
        'title' => $keyword,
        'city' => $keyword,
        'venue' => $keyword
    ];
}

$sql .= " ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventX | Manage Events</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-white">
<div class="min-h-screen md:flex">

    <aside class="w-full md:w-64 bg-slate-900 border-r border-white/10 p-6">
        <a href="dashboard.php" class="text-2xl font-black">
            <span class="text-indigo-400">Event</span>X
        </a>

        <p class="text-xs uppercase tracking-widest text-slate-500 mt-2">
            Admin panel
        </p>

        <nav class="mt-10 space-y-2">
            <a href="dashboard.php"
               class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">
                Dashboard
            </a>
            <a href="add-event.php"
               class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">
                + Add Event
            </a>
            <a href="manage-events.php"
               class="block rounded-xl bg-indigo-600 px-4 py-3 font-semibold">
                Manage Events
            </a>
            <a href="../index.php"
               class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">
                View Website ↗
            </a>
        </nav>
    </aside>

    <main class="min-w-0 flex-1 p-5 md:p-10">
        <header class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div>
                <p class="text-sm text-indigo-400 font-semibold">EVENT MANAGEMENT</p>
                <h1 class="text-3xl md:text-4xl font-black mt-2">Manage Events</h1>
                <p class="text-slate-400 mt-2">Search and manage your event inventory.</p>
            </div>

            <a href="add-event.php"
               class="rounded-xl bg-indigo-600 px-5 py-3 font-semibold hover:bg-indigo-500">
                + Add New Event
            </a>
        </header>

        <?php if ($flash): ?>
            <div role="status"
                 class="mb-6 rounded-xl border p-4
                 <?= $flash['type'] === 'success'
                     ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                     : 'border-red-500/30 bg-red-500/10 text-red-300' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <section class="rounded-3xl border border-white/10 bg-white/[0.03] overflow-hidden">
            <div class="p-5 md:p-6 border-b border-white/10">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input
                        type="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search by event, venue or city..."
                        class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-indigo-500"
                    >
                    <button type="submit"
                            class="rounded-xl bg-indigo-600 px-6 py-3 font-semibold hover:bg-indigo-500">
                        Search
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="manage-events.php"
                           class="rounded-xl border border-white/10 px-5 py-3 text-center hover:bg-white/5">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <p class="text-sm text-slate-500 mt-4">
                    <?= count($events) ?> matching event(s)
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white/[0.03] text-slate-400">
                    <tr>
                        <th class="p-4">Event</th>
                        <th class="p-4">Date & Venue</th>
                        <th class="p-4">Price</th>
                        <th class="p-4">Seats</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                    <?php if (!$events): ?>
                        <tr>
                            <td colspan="6" class="p-10 text-center text-slate-400">
                                No events found. Try another search or create an event.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($events as $event): ?>
                        <tr class="align-top hover:bg-white/[0.03]">
                            <td class="p-4 min-w-48">
                                <p class="font-bold"><?= e($event['title']) ?></p>
                                <p class="text-xs text-slate-500 mt-1">
                                    #<?= (int)$event['id'] ?> · <?= e($event['city']) ?>
                                </p>
                            </td>

                            <td class="p-4 min-w-48">
                                <p><?= e(date('d M Y', strtotime($event['event_date']))) ?></p>
                                <p class="text-xs text-slate-400 mt-1">
                                    <?= e(date('h:i A', strtotime($event['event_time']))) ?>
                                </p>
                                <p class="text-xs text-slate-500 mt-1">
                                    <?= e($event['venue']) ?>
                                </p>
                            </td>

                            <td class="p-4 whitespace-nowrap">
                                ₹<?= number_format((float)$event['price'], 2) ?>
                            </td>

                            <td class="p-4 whitespace-nowrap">
                                <p><?= (int)$event['booked_seats'] ?> / <?= (int)$event['total_seats'] ?></p>
                                <p class="text-xs text-slate-500 mt-1">Booked / Total</p>
                                <p class="text-xs text-slate-500 mt-1">
                                    <?= (int)$event['booking_count'] ?> booking record(s)
                                </p>
                            </td>

                            <td class="p-4">
                                <span class="inline-block rounded-full px-3 py-1 text-xs
                                    <?= $event['status'] === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-400'
                                        : 'bg-slate-500/20 text-slate-300' ?>">
                                    <?= e(ucfirst($event['status'])) ?>
                                </span>
                            </td>

                            <td class="p-4 min-w-40">
                                <div class="flex flex-col items-start gap-3">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['eventx_csrf']) ?>">
                                        <input type="hidden" name="event_id"
                                               value="<?= (int)$event['id'] ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <button type="submit"
                                                class="text-indigo-400 hover:text-indigo-300 font-semibold">
                                            <?= $event['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <?php if ((int)$event['booking_count'] === 0): ?>
                                        <form method="POST"
                                              onsubmit="return confirm('Delete this event and all its seats? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION['eventx_csrf']) ?>">
                                            <input type="hidden" name="event_id"
                                                   value="<?= (int)$event['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit"
                                                    class="text-red-400 hover:text-red-300 font-semibold">
                                                Delete Event
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500">
                                            Delete unavailable: bookings exist
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-slate-600 mt-6">
            EventX Admin · Changes to event availability affect public listings.
        </p>
    </main>
</div>
</body>
</html>
