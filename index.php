<?php
// Serve the static homepage through PHP so Vercel routes the root correctly.
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
