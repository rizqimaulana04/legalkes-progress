<?php
require dirname(__DIR__).'/app/bootstrap.php';
$assert=function(bool $condition,string $name): void { if (!$condition) { fwrite(STDERR,"GAGAL: $name\n"); exit(1); } echo "OK: $name\n"; };
[$monday,$next]=week_bounds('2026-09-23');
$assert($monday==='2026-09-21 00:00:00' && $next==='2026-09-28 00:00:00','Minggu Senin sampai Minggu WIB');
$assert(counted_days(['2026-09-21 09:00:00'],$monday,$next)===1,'Update Senin 1/3');
$assert(counted_days(['2026-09-21 09:00:00','2026-09-23 09:00:00'],$monday,$next)===2,'Senin Rabu 2/3');
$assert(counted_days(['2026-09-21 09:00:00','2026-09-23 09:00:00','2026-09-25 09:00:00'],$monday,$next)===3,'Senin Rabu Jumat 3/3');
$assert(counted_days(['2026-09-25 09:00:00','2026-09-25 11:00:00','2026-09-25 15:00:00'],$monday,$next)===1,'Tiga komunikasi satu hari tetap 1/3');
$assert(counted_days([],$monday,$next)===0,'Update internal tidak dihitung');
$assert(!weekly_eligible(['project_active_at'=>'2026-09-15 00:00:00','closed_at'=>'2026-09-20 17:00:00'],$monday,$next),'Project selesai tidak wajib minggu berikutnya');
$assert(!weekly_eligible(['project_active_at'=>'2026-09-25 00:00:00','closed_at'=>null],'2026-09-14 00:00:00','2026-09-21 00:00:00'),'Project baru tanpa histori sebelumnya');
$assert(weekly_eligible(['project_active_at'=>'2026-09-25 00:00:00','closed_at'=>null],$monday,$next),'Project aktif tengah minggu');
$assert(!can_view_project('SOLVER',2,3),'Solver lain ditolak');
$assert(can_view_project('DIREKTUR',2,3),'Direktur melihat semua project');
$assert(can_view_project('ADMIN',2,3),'Admin melihat semua project');
$assert(counted_days(['2026-09-21 09:00:00'],'2026-09-28 00:00:00','2026-10-05 00:00:00')===0,'Minggu baru memisahkan hitungan');
$assert(date_default_timezone_get()==='Asia/Jakarta','Timezone Asia/Jakarta');
echo "Semua pengujian bisnis lulus.\n";
