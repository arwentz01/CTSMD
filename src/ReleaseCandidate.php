<?php
declare(strict_types=1);

/** Offline manifest and read-only schema inspection. No deployment or SQL runner. */
final class ReleaseCandidate
{
    public static function category(string $path):string
    {
        if(preg_match('#(^|/)(\.env(?:\..*)?|\.git|node_modules|error_log|storage)(/|$)#',$path))return 'excluded';
        if(str_starts_with($path,'database/migrations/'))return 'migration_review_only';
        if(str_starts_with($path,'database/'))return 'excluded'; // Never package reset/demo SQL.
        if(str_starts_with($path,'mobile/'))return 'native_source_not_distributable';
        if(str_starts_with($path,'bin/'))return 'operator_only_not_public';
        if(preg_match('#^(src/|assets/|public/|my-theatre/|production/|cgi-bin/)#',$path)||in_array($path,['.htaccess','front.php','index.php','manifest.webmanifest','robots.txt','service-worker.js'],true))return 'web_runtime';
        return 'evidence_not_public';
    }

    private static function git(string $root,array $args):string
    {
        $pipes=[];$process=proc_open(array_merge(['git','-C',$root],$args),[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Git unavailable.');
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0)throw new RuntimeException('Git inspection failed.');
        return $out;
    }

    public static function manifest(string $root):array
    {
        if(trim(self::git($root,['status','--porcelain']))!=='')throw new RuntimeException('Commit or preserve all changes first; release manifest requires a clean checkout.');
        $revision=trim(self::git($root,['rev-parse','HEAD']));$files=[];
        foreach(explode("\0",self::git($root,['ls-tree','-rz','HEAD'])) as $entry){
            if($entry==='')continue;
            if(!preg_match('/^(\d+) blob ([a-f0-9]+)\t(.+)$/s',$entry,$m))throw new RuntimeException('Unsupported tree entry.');
            $category=self::category($m[3]);
            if($category==='excluded')continue;
            if($m[1]==='120000')throw new RuntimeException('Symlink requires explicit release review.');
            $bytes=self::git($root,['cat-file','blob',$m[2]]);
            $files[]=['path'=>$m[3],'category'=>$category,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];
        }
        return ['format'=>1,'candidate'=>'CTSMD-RC1','repository'=>'arwentz01/CTSMD','revision'=>$revision,'branch'=>trim(self::git($root,['branch','--show-current'])),'deployment_authorized'=>false,'workers_enabled'=>false,'migration_execution'=>'manual_review_only','files'=>$files];
    }

    public static function verifyFiles(string $root,array $manifest,bool $webOnly=false):array
    {
        if(($manifest['format']??null)!==1||!preg_match('/^[a-f0-9]{40}$/',(string)($manifest['revision']??''))||empty($manifest['files']))throw new RuntimeException('Invalid manifest.');
        $errors=[];$seen=[];
        foreach($manifest['files'] as $file){
            $path=(string)($file['path']??'');
            if(!preg_match('#^[a-zA-Z0-9_.()/@\[\]+ -]+$#',$path)||str_contains($path,'..')||str_starts_with($path,'/')||isset($seen[$path])||self::category($path)!==($file['category']??''))throw new RuntimeException('Unsafe or duplicate manifest path/category.');
            $seen[$path]=true;
            if($file['category']==='excluded'||!preg_match('/^[a-f0-9]{64}$/',(string)($file['sha256']??'')))throw new RuntimeException('Invalid manifest entry.');
            if($webOnly&&$file['category']!=='web_runtime')continue;
            if(!in_array($file['category'],['web_runtime','operator_only_not_public','migration_review_only'],true))continue;
            $full=$root.'/'.$path;$resolved=realpath($full);
            if(!$resolved||!str_starts_with($resolved,realpath($root).DIRECTORY_SEPARATOR)||is_link($full)||!is_file($full)||!hash_equals((string)$file['sha256'],hash_file('sha256',$full)))$errors[]=$path;
        }
        foreach(['front.php','.htaccess','src/Auth.php','src/MobileApi.php','src/SafeguardedConversationPolicy.php'] as $required)if(!isset($seen[$required]))throw new RuntimeException('Incomplete web manifest.');
        // Detect the class of unmanaged executable involved in the earlier host incident.
        $rootExecutables=array_filter(glob($root.'/*')?:[],static fn(string $file):bool=>is_file($file)&&(bool)preg_match('/\.(?:php\d*|phtml|phar)$/i',$file));
        foreach(array_merge($rootExecutables,self::phpFiles($root.'/assets'),self::phpFiles($root.'/public'),self::phpFiles($root.'/src'),self::phpFiles($root.'/cgi-bin'),self::phpFiles($root.'/production'),self::phpFiles($root.'/my-theatre')) as $full){
            $relative=substr($full,strlen($root)+1);if(!isset($seen[$relative]))$errors[]='unmanaged executable: '.$relative;
        }
        return array_values(array_unique($errors));
    }

    private static function phpFiles(string $directory):array
    {
        if(!is_dir($directory))return [];$files=[];
        $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS));
        foreach($walk as $file)if($file->isFile()&&preg_match('/^(?:php\d*|phtml|phar)$/i',$file->getExtension()))$files[]=$file->getPathname();
        return $files;
    }

    public static function normalizeSql(string $sql):string
    {
        $sql=preg_replace('/--[^\n]*|\/\*.*?\*\//s','',$sql);
        return strtolower(preg_replace('/\s+/','',str_replace('`','',trim((string)$sql))));
    }

    /** Source inventory is evidence of declarations, never proof a migration was applied. */
    public static function inventory(string $root):array
    {
        $tables=[];$columns=[];$triggers=[];$dropped=[];$migrations=[];
        foreach(array_merge([$root.'/database/schema.sql'],glob($root.'/database/migrations/*.sql')) as $file){
            $sql=file_get_contents($file);$relative=substr($file,strlen($root)+1);
            preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\((.*?)\)\s*ENGINE/is',$sql,$matches,PREG_SET_ORDER);
            foreach($matches as $match){$tables[$match[1]]=true;foreach(explode("\n",$match[2]) as $line)if(preg_match('/^\s*`?(\w+)`?\s+(?:BIGINT|INT|TINYINT|SMALLINT|VARCHAR|CHAR|TEXT|LONGTEXT|MEDIUMTEXT|JSON|DATETIME|TIMESTAMP|DATE|TIME|DECIMAL|ENUM|BOOLEAN|DOUBLE|FLOAT|BINARY|VARBINARY)\b/i',$line,$c))$columns[$match[1]][$c[1]]=true;}
            preg_match_all('/ALTER TABLE\s+`?(\w+)`?\s+(.*?);/is',$sql,$matches,PREG_SET_ORDER);
            foreach($matches as $match){preg_match_all('/ADD COLUMN\s+`?(\w+)/i',$match[2],$cs);foreach($cs[1] as $column)$columns[$match[1]][$column]=true;}
            preg_match_all('/DROP TRIGGER IF EXISTS\s+(\w+)/i',$sql,$drops);foreach($drops[1] as $name){unset($triggers[$name]);$dropped[$name]=true;}
            preg_match_all('/CREATE TRIGGER\s+(\w+)\s+(BEFORE|AFTER)\s+(INSERT|UPDATE|DELETE)\s+ON\s+(\w+)\s+FOR EACH ROW\s+(.*?END)\$\$/is',$sql,$ts,PREG_SET_ORDER);
            foreach($ts as $t){$triggers[$t[1]]=['timing'=>strtoupper($t[2]),'event'=>strtoupper($t[3]),'table'=>$t[4],'body_hash'=>hash('sha256',self::normalizeSql($t[5]))];unset($dropped[$t[1]]);}
            if(str_contains($relative,'/migrations/'))$migrations[]=['path'=>$relative,'sha256'=>hash('sha256',$sql),'decision'=>'manual_review_required','has_data_changes'=>(bool)preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/im',$sql),'has_ddl'=>(bool)preg_match('/^\s*(CREATE|ALTER|DROP)\b/im',$sql)];
        }
        return compact('tables','columns','triggers','dropped','migrations');
    }

    public static function inspect(PDO $db,string $root):array
    {
        $expected=self::inventory($root);$missing=[];$columns=[];$tables=[];$triggers=[];
        foreach($db->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $row){$tables[$row['TABLE_NAME']]=true;if($row['ENGINE']!=='InnoDB')$missing[]='non-InnoDB table: '.$row['TABLE_NAME'];}
        foreach($db->query('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $row)$columns[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;
        foreach($expected['tables'] as $table=>$_)if(!isset($tables[$table]))$missing[]='table: '.$table;
        foreach($expected['columns'] as $table=>$cs)foreach($cs as $column=>$_)if(!isset($columns[$table][$column]))$missing[]='column: '.$table.'.'.$column;
        foreach($db->query('SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $t)$triggers[$t['TRIGGER_NAME']]=['timing'=>$t['ACTION_TIMING'],'event'=>$t['EVENT_MANIPULATION'],'table'=>$t['EVENT_OBJECT_TABLE'],'body_hash'=>hash('sha256',self::normalizeSql($t['ACTION_STATEMENT']))];
        foreach($expected['triggers'] as $name=>$definition)if(($triggers[$name]??null)!==$definition)$missing[]='trigger missing/drifted: '.$name;
        foreach($expected['dropped'] as $name=>$_)if(isset($triggers[$name]))$missing[]='obsolete trigger: '.$name;
        foreach(['users.date_of_birth'=>'date','conversation_participants.history_through_message_id'=>'bigint unsigned','conversation_participants.access_revoked_at'=>'datetime'] as $key=>$type){[$table,$column]=explode('.',$key);$actual=$columns[$table][$column]??[];if(($actual['COLUMN_TYPE']??'')!==$type||($actual['IS_NULLABLE']??'')!=='YES')$missing[]='critical type/nullability: '.$key;}
        return ['schema_matches_declared_contracts'=>!$missing,'issues'=>$missing,'table_count'=>count($tables),'trigger_count'=>count($triggers),'migration_review'=>$expected['migrations'],'limitations'=>['Presence is not an applied-migration ledger; backfills, permissions, indexes, FK definitions and all type/default changes require review.','Trigger hashes are lexical drift checks, not proof of concurrency correctness.','No migration or delivery worker was executed.']];
    }
}
