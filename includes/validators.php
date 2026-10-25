<?php
function post_string(string $key, int $max=2000): string { $v=trim((string)($_POST[$key] ?? '')); if (mb_strlen($v)>$max) throw new InvalidArgumentException("The {$key} field is too long."); return $v; }
function valid_email(string $email): bool { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
