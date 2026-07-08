<?php
// Redirect to the static homepage so Vercel serves index.html and all static assets normally.
header("Location: /index.html");
exit;
