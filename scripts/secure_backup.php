<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\BackupCipher;

function backupHelp(): never {
    fwrite(STDERR,"Usage:\n  php scripts/secure_backup.php backup\n");
    fwrite(STDERR,"  php scripts/secure_backup.php verify /absolute/path/file.abk\n");
    fwrite(STDERR,"  php scripts/secure_backup.php restore /absolute/path/file.abk agile_restore_NAME\n");
    exit(2);
}
function mysqlOption(string $value): string {
    if (str_contains($value,"\r") || str_contains($value,"\n")) {
        throw new RuntimeException('Invalid MySQL configuration value.');
    }
    return '"'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"';
}
function temporaryMySqlCredentials(): string {
    $file=tempnam(sys_get_temp_dir(),'agile_mycfg_');
    if ($file===false) throw new RuntimeException('Cannot create temporary MySQL credentials file.');
    chmod($file,0600);
    $contents="[client]\n"
        ."host=".mysqlOption(envValue('DB_HOST','127.0.0.1'))."\n"
        ."port=".(int)envValue('DB_PORT','3306')."\n"
        ."user=".mysqlOption(envValue('DB_USER','agile_app'))."\n"
        ."password=".mysqlOption(envValue('DB_PASSWORD'))."\n";
    if (file_put_contents($file,$contents,LOCK_EX)===false) {
        @unlink($file);
        throw new RuntimeException('Cannot write temporary MySQL client credentials.');
    }
    return $file;
}
function process($args,array &$pipes) {
    $descriptors=[
        0=>['pipe','r'],
        1=>['pipe','w'],
        2=>['pipe','w']
    ];
    $proc=proc_open($args,$descriptors,$pipes,null,null,['bypass_shell'=>true]);
    if (!is_resource($proc)) throw new RuntimeException('MySQL command failed to start.');
    stream_set_blocking($pipes[2],false);
    return $proc;
}
function protectedBackupFolder(): string {
    $dir=envValue('AGILE_BACKUP_DIR',dirname(__DIR__).'/storage/backups');
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create private backup directory.');
    }
    $dir=realpath($dir);
    $public=realpath(dirname(__DIR__).'/public');
    if ($dir===false || ($public!==false &&
         ($dir===$public || str_starts_with($dir,$public.DIRECTORY_SEPARATOR)))) {
        throw new RuntimeException('Backup destination must be outside the public web root.');
    }
    return $dir;
}
function ensureHealthyBackup(string $file,string $key): int {
    $fp=fopen($file,'rb');
    if (!$fp)throw new RuntimeException('Encrypted backup file is unreadable.');
    try {return BackupCipher::decrypt($fp,null,$key);}
    finally {fclose($fp);}
}

$operation=$argv[1]??'';
if (!in_array($operation,['backup','verify','restore'],true)) backupHelp();
$credentials=null;
$key=null;
try {
    $key=BackupCipher::keyFromEnvironment();
    if ($operation==='verify') {
        $file=$argv[2]??'';
        if ($file==='' || !is_file($file)) backupHelp();
        $bytes=ensureHealthyBackup($file,$key);
        echo "Verified authenticated encrypted backup ($bytes plaintext bytes). No plaintext file created.\n";
        exit(0);
    }

    if ($operation==='backup') {
        $dir=protectedBackupFolder();
        $file=$dir.'/agile-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5)).'.abk';
        $output=fopen($file,'x+b');
        if ($output===false)throw new RuntimeException('Unable to create private backup.');
        chmod($file,0600);
        try {
            $credentials=temporaryMySqlCredentials();
            $cmd=['mysqldump','--defaults-extra-file='.$credentials,
              '--single-transaction','--quick','--hex-blob','--no-tablespaces',
              '--default-character-set=utf8mb4',envValue('DB_NAME','agile_ous')];
            $proc=process($cmd,$pipes);
            fclose($pipes[0]);
            try {
                $bytes=BackupCipher::encrypt($pipes[1],$output,$key);
                fclose($pipes[1]);
                $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
                $exit=proc_close($proc);
                if ($exit!==0) throw new RuntimeException('mysqldump exited unsuccessfully; ensure client is installed and DB permissions are valid.');
                if ($bytes===0)throw new RuntimeException('Empty database dump.');
            }catch(\Throwable $e){
                if(is_resource($pipes[1]))fclose($pipes[1]);
                if(is_resource($pipes[2]))fclose($pipes[2]);
                if(is_resource($proc))proc_terminate($proc);
                throw $e;
            }
        }finally{
            fclose($output);
            if ($credentials!==null) {@unlink($credentials);$credentials=null;}
        }
        try {$verified=ensureHealthyBackup($file,$key);}
        catch(\Throwable $e){@unlink($file);throw $e;}
        if ($verified!==$bytes) {@unlink($file);throw new RuntimeException('Backup integrity verification mismatch.');}
        echo "Encrypted backup created and verified: $file\n";
        echo "Encrypted backup covers the MySQL database only; private attachments require a separate encrypted storage backup.\n";
        exit(0);
    }

    // Restore is intentionally limited to a separately provisioned, disposable DB.
    $file=$argv[2]??'';
    $target=$argv[3]??'';
    if ($file==='' || !is_file($file) ||
        !preg_match('/^agile_restore_[a-z0-9_]{1,45}$/',$target) ||
        $target===envValue('DB_NAME','agile_ous') ||
        envValue('AGILE_ALLOW_ISOLATED_RESTORE','false')!=='true') {
        throw new RuntimeException('Restore requires an existing isolated agile_restore_* DB and explicit AGILE_ALLOW_ISOLATED_RESTORE=true.');
    }
    $bytes=ensureHealthyBackup($file,$key); // verify before modifying target
    $credentials=temporaryMySqlCredentials();
    $proc=process(['mysql','--defaults-extra-file='.$credentials,'--default-character-set=utf8mb4',$target],$pipes);
    fclose($pipes[1]);
    $input=fopen($file,'rb');
    if (!$input)throw new RuntimeException('Backup file cannot be reopened.');
    try {
        $written=BackupCipher::decrypt($input,$pipes[0],$key);
        fclose($pipes[0]);fclose($input);
        $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
        $code=proc_close($proc);
        if ($code!==0 || $written!==$bytes) throw new RuntimeException('Restore failed. Isolated restore database may require cleanup.');
    }finally{
        if(is_resource($pipes[0]))fclose($pipes[0]);
        if(is_resource($pipes[2]))fclose($pipes[2]);
        if(is_resource($input))fclose($input);
    }
    echo "Encrypted backup restored to isolated test database: $target\n";
    exit(0);
}catch(\Throwable $e){
    fwrite(STDERR,"Backup/restore operation failed: ".$e->getMessage()."\n");
    exit(1);
}finally{
    if($credentials!==null)@unlink($credentials);
}
