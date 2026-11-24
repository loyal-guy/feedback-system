<?php
/* Plain page with no database calls, because the error may have come from the database. */
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Something went wrong</title>
<link rel="stylesheet" href="<?= htmlspecialchars(function_exists('url') ? url('assets/css/style.css') : '/assets/css/style.css', ENT_QUOTES) ?>">
</head><body><main class="container main"><div class="card error-page">
<p class="error-code">500</p><h1>Something went wrong</h1>
<p class="muted">An unexpected error occurred. It has been logged and the team will look into it. Please try again later.</p>
</div></main></body></html>
