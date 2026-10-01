<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', '0');
error_reporting(E_ALL);
spl_autoload_register(function (string $class): void {
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) require $path;
});
set_exception_handler(function (Throwable $e): void {
    error_log($e->__toString(), 3, dirname(__DIR__) . '/storage/logs/app.log');
    abort(500,'Terjadi kesalahan. Silakan coba lagi atau hubungi administrator.');
});
function envv(string $key, string $default = ''): string {
    static $values = null;
    if ($values === null) $values = is_file(dirname(__DIR__) . '/.env') ? (parse_ini_file(dirname(__DIR__) . '/.env', false, INI_SCANNER_RAW) ?: []) : [];
    return (string)($values[$key] ?? getenv($key) ?: $default);
}
ini_set('session.use_strict_mode', '1');
if (PHP_SAPI !== 'cli') { $sessionDir=dirname(__DIR__).'/storage/sessions'; if (!is_dir($sessionDir)) mkdir($sessionDir,0700,true); session_save_path($sessionDir); }
session_name('legales_session');
session_set_cookie_params(['httponly'=>true,'secure'=>envv('SESSION_SECURE') === '1','samesite'=>'Lax','path'=>'/']);
if (PHP_SAPI !== 'cli') session_start();
function db(): PDO {
    static $pdo = null;
    if (!$pdo) { $pdo = new PDO('mysql:host='.envv('DB_HOST','127.0.0.1').';port='.envv('DB_PORT','3306').';dbname='.envv('DB_NAME','legales_progress').';charset=utf8mb4', envv('DB_USER'), envv('DB_PASS'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); $pdo->exec("SET time_zone = '+07:00'"); }
    return $pdo;
}
function q(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function one(string $sql,array $p=[]): ?array { return q($sql,$p)->fetch() ?: null; }
function all(string $sql,array $p=[]): array { return q($sql,$p)->fetchAll(); }
function esc(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['_csrf']??''))) abort(419,'Sesi formulir kedaluwarsa. Muat ulang halaman.'); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_user(): array { if (!user()) { header('Location: /login'); exit; } return user(); }
function role(array $roles): void { $u=require_user(); if (!in_array($u['role'],$roles,true)) abort(403,'Anda tidak memiliki akses ke halaman ini.'); }
function abort(int $code,string $message): never { http_response_code($code); echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc((string)$code).' · LEGALES PROGRESS</title><link rel="stylesheet" href="/assets/app.css"></head><body><div class="error-page"><div class="error-card"><div class="brand"><img src="/assets/images/logo-legalkes.png" alt="" onerror="this.style.display=\'none\'"><div><b>LEGALES</b><small>PROGRESS</small></div></div><p class="error-code">'.esc((string)$code).'</p><h1>'.esc($message).'</h1><a class="primary" href="/">Kembali ke dashboard</a></div></div></body></html>'; exit; }
function redirect(string $path,string $message=''): never { if ($message) $_SESSION['flash']=$message; header('Location: '.$path); exit; }
function audit(string $action,string $type='system',?int $id=null,string $description=''): void { q('INSERT INTO audit_logs(user_id,action,object_type,object_id,description,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)',[user()['id']??null,$action,$type,$id,$description,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]); }
function project(int $id): array { $p=one('SELECT p.*,c.name client_name,c.pic_name client_pic,c.whatsapp client_whatsapp,c.email client_email,c.address client_address,s.name service_name,u.name solver_name FROM projects p JOIN clients c ON c.id=p.client_id JOIN services s ON s.id=p.service_id JOIN users u ON u.id=p.solver_id WHERE p.id=? AND p.archived_at IS NULL',[$id]); if (!$p) abort(404,'Project tidak ditemukan.'); if (!can_view_project(user()['role'],(int)user()['id'],(int)$p['solver_id'])) abort(403,'Anda tidak memiliki akses ke project ini.'); return $p; }
function active_status(string $s): bool { return !in_array($s,['Terbit','Selesai','Dibatalkan'],true); }
function week_bounds(?string $day=null): array { $d=new DateTimeImmutable($day??'now',new DateTimeZone('Asia/Jakarta')); $m=$d->modify('monday this week')->setTime(0,0); return [$m->format('Y-m-d H:i:s'),$m->modify('+7 days')->format('Y-m-d H:i:s')]; }
function weekly_count(int $id,string $start,string $end): int { return (int)one('SELECT COUNT(DISTINCT DATE(communicated_at)) n FROM client_updates WHERE project_id=? AND communicated_at>=? AND communicated_at<?',[$id,$start,$end])['n']; }
function counted_days(array $dates,string $start,string $end): int { $days=[]; foreach ($dates as $date) if ($date >= $start && $date < $end) $days[substr($date,0,10)]=true; return count($days); }
function can_view_project(string $role,int $userId,int $solverId): bool { return $role==='ADMIN' || $role==='DIREKTUR' || ($role==='SOLVER' && $userId===$solverId); }
function weekly_eligible(array $p,string $start,string $end): bool { return ($p['project_active_at']??$p['tanggal_mulai']) < $end && (!$p['closed_at'] || $p['closed_at'] >= $start); }
function percentage(int $projectId): int { $r=one('SELECT COALESCE(SUM(weight),0) total,COALESCE(SUM(CASE WHEN state="Selesai" THEN weight ELSE 0 END),0) done FROM project_milestones WHERE project_id=?',[$projectId]); return (int)round($r['total'] ? 100*$r['done']/$r['total'] : 0); }
function upload_file(int $projectId,string $category,string $field='file'): ?int { if (empty($_FILES[$field]['name'])) return null; $f=$_FILES[$field]; if ($f['error']!==UPLOAD_ERR_OK || $f['size']>10485760) abort(422,'Ukuran berkas maksimum 10 MB.'); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx']; if (!isset($allowed[$mime])) abort(422,'Jenis berkas tidak diizinkan.'); $name=bin2hex(random_bytes(20)).'.'.$allowed[$mime]; $dir=dirname(__DIR__).'/storage/uploads'; if (!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) abort(500,'Berkas gagal disimpan.'); q('INSERT INTO attachments(project_id,original_name,internal_name,category,uploader_id,size_bytes,mime_type) VALUES (?,?,?,?,?,?,?)',[$projectId,substr(basename($f['name']),0,255),$name,$category,user()['id'],$f['size'],$mime]); $id=(int)db()->lastInsertId(); audit('Upload dokumen','attachment',$id); return $id; }
