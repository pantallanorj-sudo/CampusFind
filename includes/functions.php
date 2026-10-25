<?php
declare(strict_types=1);
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'] = ['type'=>$type, 'message'=>$message]; }
function get_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): void { $sent = $_POST['csrf_token'] ?? ''; if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) { http_response_code(419); exit('Invalid or expired form token. Go back and try again.'); } }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!current_user()) { flash('error', 'Please log in first.'); redirect('login.php'); } }
function require_role(array $roles): void { require_login(); if (!in_array(current_user()['role'] ?? '', $roles, true)) { http_response_code(403); exit('403 — You do not have permission to access this page.'); } }
function notify_user(PDO $pdo, int $userId, string $type, string $message, ?string $relatedType=null, ?int $relatedId=null): void {
 $s=$pdo->prepare('INSERT INTO notifications(user_id,type,message,related_type,related_id) VALUES(?,?,?,?,?)'); $s->execute([$userId,$type,$message,$relatedType,$relatedId]);
}
