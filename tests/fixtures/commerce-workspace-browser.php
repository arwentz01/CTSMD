<?php
declare(strict_types=1);
require_once __DIR__.'/commerce.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE')))throw new RuntimeException('Disposable loopback schema required.');
$f=json_decode(file_get_contents((string)getenv('CTSMD_COMMERCE_FIXTURE')),true,512,JSON_THROW_ON_ERROR);if(($f['isolated']??false)!==true||$f['database']!==getenv('DB_DATABASE')||!preg_match('/^[a-f0-9]{12}$/',$f['suffix']))throw new RuntimeException('Fixture mismatch.');
$root=(string)getenv('STORAGE_PATH');if($root===''||!str_starts_with($root,'/')||str_starts_with($root,dirname(__DIR__,2).'/'))throw new RuntimeException('Explicit private storage outside the repository required.');$key='commerce-workspace-'.$f['suffix'].'.png';$path=$root.'/'.$key;
if(($argv[1]??'')==='retire'){if(is_file($path))unlink($path);exit;}
if(!is_dir($root))mkdir($root,0700,true);if(is_file($path))throw new RuntimeException('Refusing to overwrite artwork.');
putenv('COMMERCE_SANDBOX_ENABLED=1');putenv('COMMERCE_ORGANIZATION_KEY='.$f['organization_key']);$db=Database::connect(dirname(__DIR__,2));
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf1kAAAAASUVORK5CYII=');file_put_contents($path,$png);
foreach(['A'=>$key,'B'=>'missing-'.$key] as $production=>$storage){$db->prepare("INSERT INTO stored_files(storage_driver,created_by_user_id,status) VALUES('local',?,'active')")->execute([$f['admin']['id']]);$id=$db->lastInsertId();$db->prepare('INSERT INTO stored_file_versions(stored_file_id,version_number,storage_key,original_name,mime_type,extension,byte_size,sha256,uploaded_by_user_id) VALUES(?,1,?,?,?,?,?,?,?)')->execute([$id,$storage,'Synthetic poster.png','image/png','png',strlen($png),hash('sha256',$png),$f['admin']['id']]);$db->prepare("INSERT INTO production_artwork(production_id,role,stored_file_id,updated_by_user_id) VALUES(?,'poster',?,?)")->execute([$f['productions'][$production],$id,$f['admin']['id']]);}
$db->prepare('UPDATE productions SET announcement_published_at=NULL WHERE id=?')->execute([$f['productions']['A']]);$q=$db->prepare('SELECT COUNT(*) FROM commerce_performances WHERE organization_id=? AND production_id=?');$q->execute([$f['org'],$f['productions']['A']]);if((int)$q->fetchColumn()<2)CommerceInventoryService::create($db,$f['admin'],$f['org'],$f['productions']['A'],$f['layout'],date('Y-m-d\TH:i',time()+3*86400),$f['prices']);
echo "Synthetic unpublished, missing-file and multiple-performance artwork states created.\n";
