<?php
declare(strict_types=1);
namespace Agile;

use DomainException;

final class Attachments {
    private const MAX_BYTES=5*1024*1024;
    private const ACCEPT=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];

    public static function validateUpload(array $file): void {
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
            !isset($file['tmp_name'],$file['size'],$file['name']) ||
            !is_uploaded_file((string)$file['tmp_name']) ||
            $file['size']<1||$file['size']>self::MAX_BYTES) {
            throw new DomainException('Upload failed or file is larger than the 5 MB limit.');
        }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        if (!isset(self::ACCEPT[$mime])) throw new DomainException('Upload PDF, JPG or PNG only.');
    }
    public static function store(array $file,string $ownerType,int $ownerId,?array $actor=null): int {
        if (!in_array($ownerType,['membership_application','welfare_case','academic_verification','academic_review_request'],true)||$ownerId<1)
            throw new DomainException('Invalid private attachment destination.');
        self::validateUpload($file);
        if ($ownerType==='academic_verification'&&($actor['role']??'')!=='msw_head')
            throw new DomainException('Academic files require an authorized verifier.');
        if ($ownerType==='academic_review_request') {
            if ($actor===null) throw new DomainException('Academic appeal evidence requires a verified account.');
            AcademicCasework::authorizeEvidence($ownerId,$actor,true);
        }
        if ($ownerType==='welfare_case'&&$actor!==null) {
            Welfare::findForActor($ownerId,$actor);
        }
        if ($ownerType==='membership_application'&&$actor!==null) {
            ApplicationWorkflow::findForActor($ownerId,$actor);
        }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        $key=bin2hex(random_bytes(20));
        $dir=dirname(__DIR__).'/storage/private';
        if (!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Private storage unavailable.');
        $target=$dir.'/'.$key.'.'.self::ACCEPT[$mime];
        if (!move_uploaded_file((string)$file['tmp_name'],$target))throw new \RuntimeException('Could not save private attachment.');
        chmod($target,0600);
        try {
            $q=\db()->prepare('INSERT INTO private_attachments (storage_key,owner_type,owner_id,original_name,mime_type,byte_size,uploaded_by) VALUES(?,?,?,?,?,?,?)');
            $name=mb_substr(basename((string)$file['name']),0,255);
            $q->execute([$key,$ownerType,$ownerId,$name,$mime,(int)$file['size'],$actor['id']??null]);
            $id=(int)\db()->lastInsertId();
            \audit($actor['id']??null,'attachment.uploaded','private_attachment',$id);
            return $id;
        }catch(\Throwable $e){@unlink($target);throw $e;}
    }
    /**
     * Record-level permission + completed malware scanning + byte-for-byte
     * integrity verification, used by all download endpoints.
     */
    public static function authorizeDownload(int $id,array $actor): array {
        $q=\db()->prepare('SELECT * FROM private_attachments WHERE id=?');
        $q->execute([$id]);$file=$q->fetch();
        if (!$file)throw new DomainException('Document not available.');
        $type=$file['owner_type'];
        if ($type==='academic_verification' && ($actor['role']??'')!=='msw_head')
            throw new DomainException('Restricted academic document.');
        if ($type==='academic_review_request')
            AcademicCasework::authorizeEvidence((int)$file['owner_id'],$actor,false);
        if ($type==='welfare_case') Welfare::findForActor((int)$file['owner_id'],$actor);
        if ($type==='membership_application') ApplicationWorkflow::findForActor((int)$file['owner_id'],$actor);
        if ($file['scan_status']!=='clean' ||
            !is_string($file['content_sha256']) ||
            !preg_match('/^[a-f0-9]{64}$/D',$file['content_sha256'])) {
            throw new DomainException('Document awaiting security clearance or unavailable.');
        }
        $path=AttachmentScanner::filePath($file);
        if (filesize($path)!==(int)$file['byte_size'] ||
            !hash_equals($file['content_sha256'],hash_file('sha256',$path))) {
            throw new DomainException('Document integrity check failed. Contact MSW.');
        }
        $file['authorized_path']=$path;
        return $file;
    }

    public static function retrieve(int $id,array $actor): never {
        try {$file=self::authorizeDownload($id,$actor);}
        catch(DomainException|\RuntimeException $e){
            http_response_code(403);
            header('Cache-Control: no-store');
            exit('Document unavailable or awaiting security clearance.');
        }
        \audit((int)$actor['id'],'attachment.downloaded','private_attachment',$id);
        header('Content-Type: '.$file['mime_type']);
        header('Content-Disposition: attachment; filename="document.'.self::ACCEPT[$file['mime_type']].'"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($file['authorized_path']);
        exit;
    }
}
