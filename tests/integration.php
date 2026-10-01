<?php
require dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI!=='cli') exit(1);
$base=getenv('TEST_URL') ?: 'http://127.0.0.1:8765';
$adminEmail=getenv('ADMIN_EMAIL'); $adminPass=getenv('ADMIN_PASSWORD');
if (!$adminEmail || !$adminPass) { fwrite(STDERR,"ADMIN_EMAIL dan ADMIN_PASSWORD diperlukan.\n"); exit(1); }
function check(bool $ok,string $label): void { if (!$ok) { fwrite(STDERR,"GAGAL: $label\n"); exit(1); } echo "OK: $label\n"; }
function client_session(string $name): array { $cookie=dirname(__DIR__).'/work/'.$name.'-cookie.txt'; $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie]); return [$ch,$cookie]; }
function request($ch,string $base,string $path,?array $post=null): array { curl_setopt($ch,CURLOPT_URL,$base.$path); if ($post===null) curl_setopt($ch,CURLOPT_HTTPGET,true); else { curl_setopt($ch,CURLOPT_POST,true); curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post)); } $body=curl_exec($ch); if ($body===false) throw new RuntimeException(curl_error($ch)); return [curl_getinfo($ch,CURLINFO_RESPONSE_CODE),$body,curl_getinfo($ch,CURLINFO_REDIRECT_URL)]; }
function token(string $body): string { if (!preg_match('/name="_csrf" value="([a-f0-9]+)"/',$body,$m)) throw new RuntimeException('CSRF tidak ditemukan'); return $m[1]; }
$suffix=bin2hex(random_bytes(4)); [$admin,$adminCookie]=client_session('admin-'.$suffix);
[$code,$html]=request($admin,$base,'/login'); check($code===200 && str_contains($html,'LEGALES PROGRESS'),'Login tampil'); $csrf=token($html);
[$code]=request($admin,$base,'/login',['_csrf'=>$csrf,'identity'=>$adminEmail,'password'=>$adminPass]); check($code===302,'Admin login');
[$code,$html]=request($admin,$base,'/'); check($code===200 && str_contains($html,'Ringkasan operasional'),'Dashboard database tampil');
[$code]=request($admin,$base,'/users',['_csrf'=>$csrf,'action'=>'create_user','name'=>'Solver Uji '.$suffix,'username'=>'solver'.$suffix,'email'=>'solver'.$suffix.'@test.local','role'=>'SOLVER','password'=>'Solver-Test-Strong-4627','active'=>'on']); check($code===302,'Admin membuat Solver');
$solver=(int)one('SELECT id FROM users WHERE username=?',['solver'.$suffix])['id'];
[$code]=request($admin,$base,'/clients',['_csrf'=>$csrf,'action'=>'create_client','name'=>'PT Uji '.$suffix,'pic_name'=>'PIC Uji','whatsapp'=>'081234567890','city'=>'Jakarta']); check($code===302,'Admin membuat klien');
$client=(int)one('SELECT id FROM clients WHERE name=?',['PT Uji '.$suffix])['id']; $service=(int)one('SELECT id FROM services WHERE name="IDAK"')['id'];
[$code]=request($admin,$base,'/projects',['_csrf'=>$csrf,'action'=>'create_project','client_id'=>$client,'service_id'=>$service,'solver_id'=>$solver,'tanggal_mulai'=>date('Y-m-d')]); check($code===302,'Admin membuat project');
$p=one('SELECT * FROM projects WHERE client_id=? AND service_id=?',[$client,$service]); $id=(int)$p['id'];
check((bool)$p['code'] && (int)one('SELECT COUNT(*) n FROM project_milestones WHERE project_id=?',[$id])['n']>0,'Kode otomatis dan snapshot milestone');
[$code]=request($admin,$base,'/projects/'.$id); check($code===200,'Admin membuka project');
[$solverSession,$solverCookie]=client_session('solver-'.$suffix); [$code,$html]=request($solverSession,$base,'/login'); $solverCsrf=token($html); [$code]=request($solverSession,$base,'/login',['_csrf'=>$solverCsrf,'identity'=>'solver'.$suffix,'password'=>'Solver-Test-Strong-4627']); check($code===302,'Solver login');
[$code]=request($solverSession,$base,'/projects/'.$id); check($code===200,'Solver membuka project miliknya');
[$code]=request($admin,$base,'/users',['_csrf'=>$csrf,'action'=>'create_user','name'=>'Solver Lain '.$suffix,'username'=>'other'.$suffix,'email'=>'other'.$suffix.'@test.local','role'=>'SOLVER','password'=>'Other-Test-Strong-4627','active'=>'on']); check($code===302,'Admin membuat Solver kedua');
[$otherSession,$otherCookie]=client_session('other-'.$suffix); [$code,$html]=request($otherSession,$base,'/login'); $otherCsrf=token($html); [$code]=request($otherSession,$base,'/login',['_csrf'=>$otherCsrf,'identity'=>'other'.$suffix,'password'=>'Other-Test-Strong-4627']); check($code===302,'Solver kedua login');
[$code]=request($otherSession,$base,'/projects/'.$id); check($code===403,'Solver lain mendapat HTTP 403');
$milestone=(int)one('SELECT id FROM project_milestones WHERE project_id=? ORDER BY sort_order LIMIT 1',[$id])['id'];
[$code]=request($solverSession,$base,'/projects/'.$id,['_csrf'=>$solverCsrf,'action'=>'progress','update_at'=>date('Y-m-d\TH:i'),'milestone_id'=>$milestone,'status'=>'Proses','waiting_status'=>'Tidak Ada Kendala','development'=>'Dokumen awal diterima','next_action'=>'Review dokumen']); check($code===302,'Update internal tersimpan');
check((int)one('SELECT COUNT(*) n FROM client_updates WHERE project_id=?',[$id])['n']===0,'Update internal tidak menjadi update klien');
[$code]=request($solverSession,$base,'/projects/'.$id,['_csrf'=>$solverCsrf,'action'=>'progress','update_at'=>date('Y-m-d\TH:i'),'milestone_id'=>$milestone,'status'=>'Proses','waiting_status'=>'Menunggu Klien','development'=>'Perkembangan dikirim','to_client'=>'1','communicated_at'=>date('Y-m-d\TH:i'),'method'=>'WhatsApp','summary'=>'Perkembangan telah disampaikan']); check($code===302,'Update klien tersimpan');
[$start,$end]=week_bounds(); check(weekly_count($id,$start,$end)===1,'Komunikasi klien dihitung 1/3');
[$code]=request($solverSession,$base,'/projects/'.$id,['_csrf'=>$solverCsrf,'action'=>'progress','update_at'=>date('Y-m-d\TH:i'),'milestone_id'=>$milestone,'status'=>'Proses','waiting_status'=>'Menunggu Klien','development'=>'Update kedua hari yang sama','to_client'=>'1','communicated_at'=>date('Y-m-d\TH:i'),'method'=>'WhatsApp','summary'=>'Update kedua']); check($code===302 && weekly_count($id,$start,$end)===1,'Dua komunikasi hari sama tetap 1/3');
[$code,$needPage]=request($solverSession,$base,'/needs-update'); check($code===200 && str_contains($needPage,$p['code']),'Daftar perlu update Solver tampil');
[$code,$historyPage]=request($solverSession,$base,'/history'); check($code===200 && str_contains($historyPage,'Perkembangan dikirim'),'Riwayat update Solver tampil');
[$code]=request($admin,$base,'/users',['_csrf'=>$csrf,'action'=>'create_user','name'=>'Direktur Uji '.$suffix,'username'=>'director'.$suffix,'email'=>'director'.$suffix.'@test.local','role'=>'DIREKTUR','password'=>'Director-Test-Strong-4627','active'=>'on']); check($code===302,'Admin membuat Direktur');
[$directorSession,$directorCookie]=client_session('director-'.$suffix); [$code,$html]=request($directorSession,$base,'/login'); $directorCsrf=token($html); [$code]=request($directorSession,$base,'/login',['_csrf'=>$directorCsrf,'identity'=>'director'.$suffix,'password'=>'Director-Test-Strong-4627']); check($code===302,'Direktur login');
[$code]=request($directorSession,$base,'/projects/'.$id); check($code===200,'Direktur melihat seluruh project');
[$code,$detail]=request($directorSession,$base,'/solvers/'.$solver); check($code===200 && str_contains($detail,'Monitoring Solver'),'Detail monitoring Solver tampil');
[$code]=request($directorSession,$base,'/users'); check($code===403,'Direktur tidak mengelola user');
[$code]=request($admin,$base,'/projects/'.$id,['_csrf'=>$csrf,'action'=>'edit_project','solver_id'=>(int)one('SELECT id FROM users WHERE username=?',['other'.$suffix])['id'],'reason'=>'Pengujian perpindahan','target_selesai'=>'','notes'=>'']); check($code===302,'Admin memindahkan Solver');
check((int)one('SELECT COUNT(*) n FROM project_solver_history WHERE project_id=?',[$id])['n']===1,'Histori perpindahan Solver tersimpan');
[$code]=request($solverSession,$base,'/projects/'.$id); check($code===403,'Solver lama kehilangan akses');
[$code]=request($admin,$base,'/users',['_csrf'=>$csrf,'action'=>'archive_user','id'=>$solver]); check($code===302 && !one('SELECT active FROM users WHERE id=?',[$solver])['active'],'Admin mengarsipkan Solver tanpa project aktif');
[$code]=request($admin,$base,'/services',['_csrf'=>$csrf,'action'=>'create_service','name'=>'Layanan Uji '.$suffix,'category'=>'Pengujian']); check($code===302,'Admin membuat layanan');
$testService=(int)one('SELECT id FROM services WHERE name=?',['Layanan Uji '.$suffix])['id'];
[$code]=request($admin,$base,'/services',['_csrf'=>$csrf,'action'=>'archive_service','id'=>$testService]); check($code===302 && (bool)one('SELECT archived_at FROM services WHERE id=?',[$testService])['archived_at'],'Admin mengarsipkan layanan');
[$code,$csv]=request($admin,$base,'/reports.csv?type=weekly'); check($code===200 && str_contains($csv,'Kode,Klien'),'Laporan CSV tersedia');
echo "Pengujian integrasi lulus. Data uji dengan sufiks $suffix tersimpan di database uji.\n";
