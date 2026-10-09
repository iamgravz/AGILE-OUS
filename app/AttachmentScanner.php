<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use RuntimeException;

/**
 * Fail-closed scanning; no document is readable before an approved clean result.
 * Run from CLI on a trusted worker, never via a public request.
 */
final class AttachmentScanner {
    private const EXT=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];

    public static function filePath(array $row): string {
        $key=(string)($row['storage_key']??'');
        $ext=self::EXT[$row['mime_type']??'']??null;
        if (!preg_match('/^[a-f0-9]{40}$/D',$key)||$ext===null)
            throw new RuntimeException('Stored attachment metadata is invalid.');
        $dir=dirname(__DIR__).'/storage/private';
        $path=$dir.'/'.$key.'.'.$ext;
        if (is_link($path) || !is_file($path))throw new RuntimeException('Private document missing or unsafe.');
        return $path;
    }

    public static function inspect(string $path): string {
        if (!is_file($path)||is_link($path))throw new RuntimeException('Scanner cannot read this file.');
        $driver=\envValue('FILE_SCAN_DRIVER','clamav');
        // Synthetic scanner exists solely for isolated GitHub CI with no real data.
        if ($driver==='synthetic'
           && \envValue('APP_ENV')==='testing'
           && \envValue('RUN_E2E_TESTS')==='yes'
           && \envValue('CI')==='true') {
            $bytes=file_get_contents($path);
            if($bytes===false)throw new RuntimeException('Fixture cannot be read.');
            return str_contains($bytes,'AGILE_SYNTHETIC_INFECTED_MARKER')?'infected':'clean';
        }
        if($driver!=='clamav')throw new RuntimeException('No approved malware scanner configured.');
        if(!function_exists('proc_open'))throw new RuntimeException('ClamAV execution unavailable.');
        $proc=@proc_open(['clamscan','--no-summary','--infected','--',$path],
           [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
           $pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($proc))throw new RuntimeException('ClamAV scanner executable not available.');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1],false);
        stream_set_blocking($pipes[2],false);
        $deadline=microtime(true)+45;
        $lastExit=null;
        do {
            // Drain scanner output without exposing potentially sensitive filenames.
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $status=proc_get_status($proc);
            if(!$status['running']){$lastExit=(int)$status['exitcode'];break;}
            if(microtime(true)>$deadline){
                proc_terminate($proc);
                fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);
                throw new RuntimeException('Malware scan exceeded time limit.');
            }
            usleep(100000);
        }while(true);
        fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($proc);
        $code=$lastExit!==-1?$lastExit:$exit;
        if($code===0)return 'clean';
        if($code===1)return 'infected';
        throw new RuntimeException('ClamAV scan could not be completed.');
    }

    private static function securityEvent(string $key,string $severity,int $id,string $note):void {
        $q=\db()->prepare('INSERT INTO security_events
            (event_key,severity,resource_type,resource_id,details) VALUES (?,?,?,?,?)');
        $q->execute([$key,$severity,'private_attachment',$id,$note]);
    }

    public static function scanOne(int $id): string {
        if (PHP_SAPI!=='cli')throw new DomainException('File scanning can only run in a trusted CLI worker.');
        $pdo=\db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM private_attachments WHERE id=? FOR UPDATE');
            $q->execute([$id]);$row=$q->fetch();
            if(!$row||$row['scan_status']!=='quarantined')
                throw new DomainException('Attachment not available in the quarantine queue.');
            $pdo->prepare("UPDATE private_attachments SET scan_status='scanning',
                scan_started_at=NOW(),scan_attempts=scan_attempts+1 WHERE id=?")->execute([$id]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

        $result='scan_error';$hash=null;
        try {
            $path=self::filePath($row);
            $expected=(int)$row['byte_size'];
            if(filesize($path)!==$expected)throw new RuntimeException('Document size mismatch.');
            $actualMime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
            if($actualMime!==$row['mime_type'])throw new RuntimeException('Document MIME type changed.');
            $beforeHash=hash_file('sha256',$path);
            if(!is_string($beforeHash))throw new RuntimeException('Document hash unavailable.');
            $result=self::inspect($path);
            if($result==='clean') {
                $hash=hash_file('sha256',$path);
                if(!is_string($hash)||!hash_equals($beforeHash,$hash)||
                   filesize($path)!==$expected) {
                    throw new RuntimeException('Document changed during malware scanning.');
                }
            }
        }catch(\Throwable $e){
            $result='scan_error';
            error_log('AGILE private attachment #'.$id.' failed malware screening: '.get_class($e));
        }
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT scan_status FROM private_attachments WHERE id=? FOR UPDATE');
            $q->execute([$id]);$state=$q->fetchColumn();
            if($state!=='scanning')throw new RuntimeException('Attachment scanner claim lost.');
            $note=match($result){
                'clean'=>'Cleared by configured scanner',
                'infected'=>'Malware detected; document blocked',
                default=>'Scanner unavailable/error; document blocked'
            };
            $pdo->prepare('UPDATE private_attachments SET scan_status=?,
                content_sha256=?,scanned_at=NOW(),scan_note=?,scan_started_at=NULL WHERE id=?')
              ->execute([$result,$hash,$note,$id]);
            if($result!=='clean')self::securityEvent('attachment.'.$result,
                $result==='infected'?'critical':'warning',$id,$note);
            \audit(null,'attachment.scan_'.$result,'private_attachment',$id);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return $result;
    }

    public static function scanBatch(int $limit=20): array {
        if(PHP_SAPI!=='cli')throw new DomainException('Trusted CLI only.');
        if($limit<1||$limit>100)throw new DomainException('Invalid scan batch limit.');
        $stale=\db()->exec("UPDATE private_attachments
             SET scan_status='scan_error',scan_note='Previous scan interrupted',
                 scan_started_at=NULL
             WHERE scan_status='scanning'
               AND scan_started_at < DATE_SUB(NOW(),INTERVAL 10 MINUTE)");
        $summary=['clean'=>0,'infected'=>0,'scan_error'=>0,'stale'=>$stale];
        $q=\db()->prepare("SELECT id FROM private_attachments
            WHERE scan_status='quarantined' ORDER BY id LIMIT ?");
        $q->bindValue(1,$limit,\PDO::PARAM_INT);$q->execute();
        foreach($q->fetchAll() as $row){
            try{$result=self::scanOne((int)$row['id']);$summary[$result]++;}
            catch(\DomainException $e){continue;}
        }
        return $summary;
    }
}
