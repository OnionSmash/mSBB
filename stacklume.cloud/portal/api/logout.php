<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/csrf.php';

sc_session_start();
// CSRF is intentionally not enforced here: logout is idempotent and the worst
// a forged request can do is sign the user out, which is harmless. Requiring a
// fresh token has historically locked users out of logout when their page was
// rendered against a now-stale session csrf.
sc_logout();
header('Location: /login.html');
exit;
