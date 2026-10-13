
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// TODO: Require an authenticated administrator here.

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$totalEvents = (int)$pdo->query("
    SELECT COUNT(*) FROM events
")->fetchColumn();

$activeEvents = (int)$pdo->query("
    SELECT COUNT(*) FROM events WHERE status = 'active'
")->fetchColumn();

$totalBookings = (int)$pdo->query("
    SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'
")->fetchColumn();

$totalRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM bookings
    WHERE status = 'confirmed'
")->fetchColumn();

$recentBookings = $pdo->query("
    SELECT
        b.id,
        b.booking_reference,
        b.customer_name,
        b.customer_email,
        b.total_amount,
        b.status,
        b.created_at,
        e.title AS event_title
    FROM bookings b
    INNER JOIN events e ON e.id = b.event_id
    ORDER BY b.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventX | Admin Dashboard</title>
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
               class="block rounded-xl bg-indigo-600 px-4 py-3 font-semibold">
                Dashboard
            </a>
            <a href="add-event.php"
               class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">
                + Add Event
            </a>
            <a href="manage-events.php"
               class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">
                Manage Events
            </a>
            <a href="../index.php"
               class="block rounded-xl px-4 py-3 text-slate-300 hover:bg-white/5">
                View Website ↗
            </a>
        </nav>
    </aside>

    <main class="min-w-0 flex-1 p-5 md:p-10">
        <header class="flex flex-wrap items-center justify-between gap-4 mb-10">
            <div>
                <p class="text-sm text-indigo-400 font-semibold">OVERVIEW</p>
                <h1 class="text-3xl md:text-4xl font-black mt-2">
                    Admin Dashboard
                </h1>
                <p class="text-slate-400 mt-2">
                    Monitor your events and booking activity.
                </p>
            </div>
            <a href="add-event.php"
               class="rounded-xl bg-indigo-600 px-5 py-3 font-semibold hover:bg-indigo-500">
                + Create Event
            </a>
        </header>

        <section class="grid sm:grid-cols-2 xl:grid-cols-4 gap-5">
            <?php
            $stats = [
                ['Total Events', $totalEvents, 'All events'],
                ['Active Events', $activeEvents, 'Currently listed'],
                ['Confirmed Bookings', $totalBookings, 'Successful bookings'],
                ['Confirmed Revenue', '₹'.number_format($totalRevenue, 2), 'Booking totals']
            ];
            foreach ($stats as $stat):
            ?>
                <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-6">
                    <p class="text-sm text-slate-400"><?= e($stat[0]) ?></p>
                    <p class="text-3xl font-black mt-4 break-words"><?= e($stat[1]) ?></p>
                    <p class="text-xs text-slate-500 mt-3"><?= e($stat[2]) ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="mt-10 rounded-2xl border border-white/10 bg-white/[0.03] overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-4 p-6 border-b border-white/10">
                <div>
                    <h2 class="text-xl font-bold">Recent Bookings</h2>
                    <p class="text-sm text-slate-400 mt-1">Latest customer reservations</p>
                </div>
                <a href="manage-events.php"
                   class="text-sm text-indigo-400 hover:text-indigo-300">
                    Manage Events →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-white/[0.03] text-slate-400">
                    <tr>
                        <th class="p-4">Reference</th>
                        <th class="p-4">Customer</th>
                        <th class="p-4">Event</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Date</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                    <?php if (!$recentBookings): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                No bookings yet.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($recentBookings as $booking): ?>
                        <tr class="hover:bg-white/[0.03]">
                            <td class="p-4 font-semibold"><?= e($booking['booking_reference']) ?></td>
                            <td class="p-4">
                                <div><?= e($booking['customer_name']) ?></div>
                                <div class="text-xs text-slate-500 mt-1">
                                    <?= e($booking['customer_email']) ?>
                                </div>
                            </td>
                            <td class="p-4"><?= e($booking['event_title']) ?></td>
                            <td class="p-4">₹<?= number_format((float)$booking['total_amount'], 2) ?></td>
                            <td class="p-4">
                                <span class="rounded-full px-3 py-1 text-xs
                                    <?= $booking['status'] === 'confirmed'
                                        ? 'bg-emerald-500/10 text-emerald-400'
                                        : ($booking['status'] === 'cancelled'
                                            ? 'bg-red-500/10 text-red-400'
                                            : 'bg-amber-500/10 text-amber-400') ?>">
                                    <?= e(ucfirst($booking['status'])) ?>
                                </span>
                            </td>
                            <td class="p-4 text-slate-400 whitespace-nowrap">
                                <?= e(date('d M Y', strtotime($booking['created_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="text-center text-xs text-slate-600 mt-10">
            EventX Admin · Event management and ticketing
        </footer>
    </main>
</div>
</body>
</html>
