<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/StorageService.php';

final class ProductionFileExperience
{
    private const ROUTES = ['/files','/files/view','/files/download','/admin/files','/admin/files/edit'];
    private const AUDIENCES = [
        'production_all' => 'Everyone in production',
        'production_adults' => 'Production adults',
        'production_students' => 'Students / cast',
        'production_guardians' => 'Guardians',
        'production_staff' => 'Production staff',
    ];

    public static function handles(string $route): bool { return in_array($route,self::ROUTES,true); }

    public static function render(string $route,string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect($basePath . '/login');
        $admin = str_starts_with($route,'/admin/files');
        if ($admin && !AccessPolicy::canManageResources($user)) self::forbidden();
        $_SESSION['file_library_csrf'] ??= bin2hex(random_bytes(24));

        if ($route === '/files/download') self::download($db,$user);

        $production = ProductionContext::selected($db,$user);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) self::handlePost($db,$user,$production,$route,$basePath);

        $id = filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT) ?: 0;
        $file = null;
        if ($id > 0 && in_array($route,['/files/view','/admin/files/edit'],true)) {
            $file = self::file($db,(int)$id);
            if ($file && (!$production || (int)$file['production_id'] !== (int)$production['id'])) $file = null;
            if ($file && !$admin && !self::canRead($db,$user,$file)) $file = null;
            if ($file) $file['versions'] = StorageService::versions($db,(int)$file['stored_file_id']);
        }
        $files = $production ? self::files($db,$user,(int)$production['id'],$admin) : [];
        self::page($route,$basePath,$user,$production,$files,$file,$admin);
    }

    private static function handlePost(PDO $db,array $user,?array $production,string $route,string $basePath): never
    {
        if (!hash_equals((string)($_SESSION['file_library_csrf'] ?? ''),(string)($_POST['csrf_token'] ?? ''))) {
            self::flash('error','Your session token expired. Please try again.');
            self::redirect($basePath . '/admin/files');
        }
        if (!$production) {
            self::flash('error','Select an active production before managing files.');
            self::redirect($basePath . '/production');
        }
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save') {
                $id = filter_input(INPUT_POST,'production_file_id',FILTER_VALIDATE_INT) ?: 0;
                $saved = self::save($db,$user,(int)$production['id'],(int)$id,$_POST,$_FILES['file_upload'] ?? []);
                self::flash('success',$id ? 'File resource updated.' : 'File uploaded to the selected production.');
                self::redirect($basePath . '/admin/files/edit?id=' . $saved);
            }
            if ($action === 'archive') {
                $id = filter_input(INPUT_POST,'production_file_id',FILTER_VALIDATE_INT) ?: 0;
                self::toggleArchive($db,$user,(int)$production['id'],(int)$id);
                self::flash('success','File availability updated.');
                self::redirect($basePath . '/admin/files');
            }
            throw new RuntimeException('Choose a valid file operation.');
        } catch (RuntimeException $e) {
            self::flash('error',$e->getMessage());
            $id = filter_input(INPUT_POST,'production_file_id',FILTER_VALIDATE_INT) ?: 0;
            self::redirect($basePath . ($id ? '/admin/files/edit?id=' . (int)$id : '/admin/files'));
        }
    }

    private static function save(PDO $db,array $actor,int $productionId,int $productionFileId,array $input,array $upload): int
    {
        $title = trim((string)($input['title'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $pinned = isset($input['pinned']) ? 1 : 0;
        $audiences = array_values(array_unique(array_filter((array)($input['audiences'] ?? []),static fn($v):bool => isset(self::AUDIENCES[(string)$v]))));
        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Enter a title no longer than 190 characters.');
        if ($category === '' || mb_strlen($category) > 100) throw new RuntimeException('Enter a category no longer than 100 characters.');
        if (mb_strlen($description) > 500) throw new RuntimeException('Keep the description under 500 characters.');
        if (!$audiences) throw new RuntimeException('Choose at least one audience.');

        $stored = null;
        $db->beginTransaction();
        try {
            if ($productionFileId > 0) {
                $stmt = $db->prepare('SELECT * FROM production_files WHERE id=:id AND production_id=:production FOR UPDATE');
                $stmt->execute(['id'=>$productionFileId,'production'=>$productionId]);
                $before = $stmt->fetch();
                if (!$before) throw new RuntimeException('That file resource no longer exists in this production.');
                $hasUpload = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($hasUpload) $stored = StorageService::store($db,dirname(__DIR__),(int)$actor['id'],$upload,(int)$before['stored_file_id']);
                $update = $db->prepare('UPDATE production_files SET title=:title,category=:category,description=:description,audiences_json=:audiences,pinned=:pinned WHERE id=:id');
                $update->execute(['title'=>$title,'category'=>$category,'description'=>$description ?: null,'audiences'=>json_encode($audiences,JSON_THROW_ON_ERROR),'pinned'=>$pinned,'id'=>$productionFileId]);
                self::audit($db,(int)$actor['id'],$stored ? 'file.version_uploaded' : 'file.updated','production_file',$productionFileId,$stored ? 'Uploaded a new production file version.' : 'Updated production file metadata.',[
                    'production_id'=>$productionId,'version_number'=>$stored['version_number'] ?? null,'original_name'=>$stored['original_name'] ?? null,'sha256'=>$stored['sha256'] ?? null
                ]);
            } else {
                if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a file to upload.');
                $stored = StorageService::store($db,dirname(__DIR__),(int)$actor['id'],$upload,null);
                $insert = $db->prepare("INSERT INTO production_files (production_id,stored_file_id,created_by_user_id,title,category,description,audiences_json,pinned,status) VALUES (:production,:stored,:actor,:title,:category,:description,:audiences,:pinned,'active')");
                $insert->execute(['production'=>$productionId,'stored'=>(int)$stored['stored_file_id'],'actor'=>(int)$actor['id'],'title'=>$title,'category'=>$category,'description'=>$description ?: null,'audiences'=>json_encode($audiences,JSON_THROW_ON_ERROR),'pinned'=>$pinned]);
                $productionFileId = (int)$db->lastInsertId();
                self::audit($db,(int)$actor['id'],'file.created','production_file',$productionFileId,'Uploaded a production file.',[
                    'production_id'=>$productionId,'version_number'=>1,'original_name'=>$stored['original_name'],'mime_type'=>$stored['mime_type'],'byte_size'=>$stored['byte_size'],'sha256'=>$stored['sha256']
                ]);
            }
            $db->commit();
            return $productionFileId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($stored) StorageService::deletePhysical(dirname(__DIR__),$stored);
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The file could not be saved.');
        }
    }

    private static function toggleArchive(PDO $db,array $actor,int $productionId,int $id): void
    {
        if ($id < 1) throw new RuntimeException('That file could not be found.');
        $stmt = $db->prepare('SELECT id,title,status FROM production_files WHERE id=:id AND production_id=:production LIMIT 1');
        $stmt->execute(['id'=>$id,'production'=>$productionId]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('That file no longer exists in this production.');
        $next = $row['status'] === 'active' ? 'archived' : 'active';
        $db->prepare('UPDATE production_files SET status=:status WHERE id=:id')->execute(['status'=>$next,'id'=>$id]);
        self::audit($db,(int)$actor['id'],$next === 'active' ? 'file.restored' : 'file.archived','production_file',$id,$next === 'active' ? 'Restored production file.' : 'Archived production file.',['production_id'=>$productionId,'title'=>$row['title']]);
    }

    private static function download(PDO $db,array $user): never
    {
        $id = filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT) ?: 0;
        $versionId = filter_input(INPUT_GET,'version',FILTER_VALIDATE_INT) ?: null;
        $file = self::file($db,(int)$id);
        if (!$file || !self::canRead($db,$user,$file)) { http_response_code(404); exit('File not found'); }
        $version = StorageService::version($db,(int)$file['stored_file_id'],$versionId ? (int)$versionId : null);
        if (!$version) { http_response_code(404); exit('File not found'); }
        self::audit($db,(int)$user['id'],'file.downloaded','production_file',(int)$file['id'],'Downloaded production file.',['production_id'=>(int)$file['production_id'],'version_id'=>(int)$version['id'],'version_number'=>(int)$version['version_number']]);
        StorageService::stream(dirname(__DIR__),$version,true);
    }

    private static function files(PDO $db,array $user,int $productionId,bool $admin): array
    {
        $stmt = $db->prepare("SELECT pf.*,CONCAT(u.first_name,' ',u.last_name) creator,v.id version_id,v.version_number,v.original_name,v.mime_type,v.byte_size,v.created_at version_created_at FROM production_files pf LEFT JOIN users u ON u.id=pf.created_by_user_id LEFT JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=pf.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) WHERE pf.production_id=:production " . ($admin ? '' : "AND pf.status='active' ") . "ORDER BY pf.status='active' DESC,pf.pinned DESC,pf.category,pf.title");
        $stmt->execute(['production'=>$productionId]);
        $rows = $stmt->fetchAll();
        if ($admin) return $rows;
        return array_values(array_filter($rows,static fn(array $row):bool => self::canRead($db,$user,$row)));
    }

    private static function file(PDO $db,int $id): ?array
    {
        if ($id < 1) return null;
        $stmt = $db->prepare("SELECT pf.*,p.is_active production_active,CONCAT(u.first_name,' ',u.last_name) creator,v.id version_id,v.version_number,v.original_name,v.mime_type,v.byte_size,v.sha256,v.created_at version_created_at FROM production_files pf JOIN productions p ON p.id=pf.production_id LEFT JOIN users u ON u.id=pf.created_by_user_id LEFT JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=pf.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) WHERE pf.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    private static function canRead(PDO $db,array $user,array $file): bool
    {
        if (!(bool)($file['production_active'] ?? true)) return false;
        if (AccessPolicy::canManageResources($user)) return true;
        if (($file['status'] ?? '') !== 'active') return false;
        if (!ProductionContext::isActiveMember($db,(int)$user['id'],(int)$file['production_id'])) return false;
        $audiences = json_decode((string)$file['audiences_json'],true);
        if (!is_array($audiences) || !$audiences) return false;
        $stmt = $db->prepare("SELECT audience_type FROM production_memberships WHERE production_id=:production AND user_id=:user AND status='active'");
        $stmt->execute(['production'=>(int)$file['production_id'],'user'=>(int)$user['id']]);
        $types = array_values(array_unique($stmt->fetchAll(PDO::FETCH_COLUMN)));
        if (!$types) return false;
        if (in_array('production_all',$audiences,true)) return true;
        if (in_array('production_students',$audiences,true) && in_array('student',$types,true)) return true;
        if (in_array('production_guardians',$audiences,true) && in_array('guardian',$types,true)) return true;
        if (in_array('production_staff',$audiences,true) && in_array('staff',$types,true)) return true;
        if (in_array('production_adults',$audiences,true) && !AccessPolicy::isStudent($user)) return true;
        return false;
    }

    private static function labels(string $json): array
    {
        $a = json_decode($json,true); if (!is_array($a)) return [];
        $out = []; foreach ($a as $key) if (isset(self::AUDIENCES[$key])) $out[] = self::AUDIENCES[$key]; return $out;
    }

    private static function page(string $route,string $basePath,array $user,?array $production,array $files,?array $file,bool $admin): never
    {
        $url = static fn(string $p):string => ($basePath ?: '') . $p;
        $e = static fn(string $v):string => htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $flash = $_SESSION['file_library_flash'] ?? null; unset($_SESSION['file_library_flash']);
        $editing = $route === '/admin/files/edit'; $detail = $route === '/files/view';
        $title = $admin ? ($editing ? ($file['title'] ?? 'File') : 'File Library') : ($detail ? ($file['title'] ?? 'File') : 'Files');
        $selected = $file ? (json_decode((string)$file['audiences_json'],true) ?: []) : ['production_all'];
        header('Content-Type:text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/file-library.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath,[['label'=>'Resources','href'=>'/resources','active'=>false],['label'=>'Files','href'=>'/files','active'=>!$admin],['label'=>'Manage files','href'=>'/admin/files','active'=>$admin]]);?><div class="file-page">
        <?php if($flash):?><div class="file-flash <?= $e($flash['type']) ?>"><?= $e($flash['message']) ?></div><?php endif;?>
        <?php if(!$production):?><section class="file-empty"><h2>No active production selected.</h2><p>Select an active production to open its file library.</p><a class="button" href="<?= $url('/production') ?>">Production workspace</a></section>
        <?php elseif($admin && !$editing):?><section class="file-hero"><div><small><?= $e(strtoupper($production['title'])) ?> · PRIVATE FILE STORAGE</small><h2>Documents people can actually find.</h2><p>Files are stored privately and served only after CTSMD re-checks production and audience access.</p></div><a class="button" href="<?= $url('/admin/files/edit') ?>">Upload file</a></section><div class="file-list"><?php foreach($files as $row):?><a class="file-row<?= $row['status']==='archived'?' archived':'' ?>" href="<?= $url('/admin/files/edit?id='.(int)$row['id']) ?>"><div class="file-icon"><?= $e(strtoupper((string)pathinfo($row['original_name'] ?? 'FILE',PATHINFO_EXTENSION))) ?></div><div><small><?= $e(strtoupper($row['category'])) ?> · V<?= (int)$row['version_number'] ?></small><h3><?= $e($row['title']) ?></h3><p><?= $e($row['original_name'] ?? 'Stored file') ?> · <?= $e(StorageService::humanSize((int)($row['byte_size'] ?? 0))) ?></p></div><span><?= $row['status']==='active'?'Manage →':'Archived' ?></span></a><?php endforeach;?><?php if(!$files):?><section class="file-empty"><b>No production files yet.</b><p>Upload the first document, image, or reference file.</p></section><?php endif;?></div>
        <?php elseif($admin):?><?php if(!$file && filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)):?><section class="file-empty"><h2>File not found.</h2></section><?php else:$f=$file??['id'=>0,'title'=>'','category'=>'','description'=>'','audiences_json'=>'["production_all"]','pinned'=>0,'status'=>'active','versions'=>[]];?><section class="file-edit-head"><div><small><?= $file?'FILE SETTINGS':'NEW FILE' ?></small><h2><?= $file?$e($file['title']):'Upload production file' ?></h2><p><?= $file?'Uploading a replacement creates a new immutable version; old versions remain in history.':'Choose who can see the file before publishing it.' ?></p></div><a href="<?= $url('/admin/files') ?>">← File library</a></section><form class="file-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['file_library_csrf']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="production_file_id" value="<?= (int)$f['id'] ?>"><label>Title<input name="title" maxlength="190" required value="<?= $e($f['title']) ?>"></label><div class="file-pair"><label>Category<input name="category" maxlength="100" required value="<?= $e($f['category']) ?>" placeholder="Rehearsal materials"></label><label>File <?= $file?'<span>optional replacement</span>':'' ?><input type="file" name="file_upload" <?= $file?'':'required' ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp"></label></div><label>Description<textarea name="description" maxlength="500" rows="3"><?= $e((string)$f['description']) ?></textarea></label><fieldset><legend>Who can access this file?</legend><?php foreach(self::AUDIENCES as $key=>$label):?><label class="file-check"><input type="checkbox" name="audiences[]" value="<?= $e($key) ?>"<?= in_array($key,$selected,true)?' checked':'' ?>><span><?= $e($label) ?></span></label><?php endforeach;?></fieldset><label class="file-check"><input type="checkbox" name="pinned" value="1"<?= (bool)$f['pinned']?' checked':'' ?>><span>Pin near the top of the file library</span></label><button class="button" type="submit"><?= $file?'Save file':'Upload file' ?></button></form><?php if($file):?><section class="file-history"><header><div><small>VERSION HISTORY</small><h3><?= count($file['versions']) ?> stored version<?= count($file['versions'])===1?'':'s' ?></h3></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['file_library_csrf']) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="production_file_id" value="<?= (int)$file['id'] ?>"><button type="submit"><?= $file['status']==='active'?'Archive file':'Restore file' ?></button></form></header><?php foreach($file['versions'] as $v):?><article><div><b>Version <?= (int)$v['version_number'] ?> · <?= $e($v['original_name']) ?></b><small><?= $e(StorageService::humanSize((int)$v['byte_size'])) ?> · <?= $e(date('M j, Y · g:i A',strtotime($v['created_at']))) ?><?= $v['uploader']?' · '.$e($v['uploader']):'' ?></small></div><a href="<?= $url('/files/download?id='.(int)$file['id'].'&version='.(int)$v['id']) ?>">Download</a></article><?php endforeach;?></section><?php endif;?><?php endif;?>
        <?php elseif($detail):?><?php if(!$file):?><section class="file-empty"><h2>File unavailable.</h2><p>It may be archived or outside your production access.</p></section><?php else:?><section class="file-detail"><small><?= $e(strtoupper($file['category'])) ?></small><h2><?= $e($file['title']) ?></h2><p><?= $e((string)$file['description']) ?></p><div class="file-current"><div class="file-icon large"><?= $e(strtoupper((string)pathinfo($file['original_name'] ?? 'FILE',PATHINFO_EXTENSION))) ?></div><div><b><?= $e($file['original_name']) ?></b><span>Version <?= (int)$file['version_number'] ?> · <?= $e(StorageService::humanSize((int)$file['byte_size'])) ?></span></div><a class="button" href="<?= $url('/files/download?id='.(int)$file['id']) ?>">Download</a></div><div class="file-audience"><?php foreach(self::labels((string)$file['audiences_json']) as $label):?><span><?= $e($label) ?></span><?php endforeach;?></div></section><?php endif;?>
        <?php else:?><section class="file-hero"><div><small><?= $e(strtoupper($production['title'])) ?> · FILES</small><h2>Your production documents, without the scavenger hunt.</h2><p>Only files available to your production role appear here.</p></div></section><div class="file-list"><?php foreach($files as $row):?><a class="file-row" href="<?= $url('/files/view?id='.(int)$row['id']) ?>"><div class="file-icon"><?= $e(strtoupper((string)pathinfo($row['original_name'] ?? 'FILE',PATHINFO_EXTENSION))) ?></div><div><small><?= $e(strtoupper($row['category'])) ?></small><h3><?= $e($row['title']) ?></h3><p><?= $e($row['original_name'] ?? 'Stored file') ?> · <?= $e(StorageService::humanSize((int)($row['byte_size'] ?? 0))) ?></p></div><span>Open →</span></a><?php endforeach;?><?php if(!$files):?><section class="file-empty"><b>No files are available to you yet.</b></section><?php endif;?></div><?php endif;?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta): void
    {
        $stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');
        $stmt->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
    }
    private static function flash(string $type,string $message): void { $_SESSION['file_library_flash']=['type'=>$type,'message'=>$message]; }
    private static function redirect(string $url): never { header('Location: '.$url,true,303);exit; }
    private static function forbidden(): never { http_response_code(403);exit('Restricted'); }
}
