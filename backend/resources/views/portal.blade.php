<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NCC — Job Application Portal</title>
    @vite(['resources/css/app.css', 'resources/js/portal.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-800 antialiased" data-skill="{{ $skill ?? '' }}">
    <div id="portal-app"></div>
</body>
</html>