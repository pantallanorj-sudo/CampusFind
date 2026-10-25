<?php require_once __DIR__ . '/functions.php';
function require_student(): void { require_role(['student']); }
function require_staff(): void { require_role(['staff','admin']); }
function require_admin(): void { require_role(['admin']); }
