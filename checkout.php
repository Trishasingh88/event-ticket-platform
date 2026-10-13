<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Checkout</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>


<body class="bg-slate-950 text-white">


<div class="min-h-screen
            flex items-center
            justify-center px-6">


<div class="w-full max-w-lg
            bg-white/[0.04]
            border border-white/10
            rounded-3xl p-8">


<h1 class="text-3xl font-black">

Complete Booking

</h1>


<p class="text-slate-400 mt-2">

Enter your details to generate your ticket.

</p>


<form
action="ticket.php"
method="POST"
class="space-y-5 mt-8">


<div>

<label class="text-sm
              text-slate-400">

Full Name

</label>

<input
name="name"
required
class="w-full mt-2
       bg-slate-900
       border border-white/10
       rounded-xl p-4 outline-none
       focus:border-indigo-500">

</div>


<div>

<label class="text-sm
              text-slate-400">

Email Address

</label>

<input
type="email"
name="email"
required
class="w-full mt-2
       bg-slate-900
       border border-white/10
       rounded-xl p-4 outline-none
       focus:border-indigo-500">

</div>


<div>

<label class="text-sm
              text-slate-400">

Phone Number

</label>

<input
name="phone"
class="w-full mt-2
       bg-slate-900
       border border-white/10
       rounded-xl p-4 outline-none">

</div>


<input
type="hidden"
name="booking_id"
id="bookingId">


<button
class="w-full
       bg-indigo-600
       hover:bg-indigo-500
       rounded-xl py-4
       font-bold">

Confirm Booking →

</button>

</form>

</div>

</div>


<script>

document.getElementById(
"bookingId"
).value =
sessionStorage.getItem(
"bookingId"
);

</script>

</body>

</html>