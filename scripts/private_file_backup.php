<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/bootstrap.php';

use Agile\AttachmentVault;

function privateBackupHelp():never {
    fwrite(STDERR,"Usage:\n php scripts/private_file_backup.php backup\n"
      ." php scripts/private_file_backup.php verify /absolute/path/archive.afb\n"
      ." AGILE_ALLOW_ISOLATED_RESTORE=true php scripts/private_file_backup.php restore /absolute/path/archive.afb /absolute/path/agile_restore_files_TEST\n");
    exit(2);
}
$operation=$argv[1]??'';
if(!in_array($operation,['backup','verify','restore'],true))privateBackupHelp();

try{
    $key=AttachmentVault::keyFromEnvironment();
    if($operation==='verify'){
        $file=$argv[2]??'';
        if($file===''||!is_file($file)||is_link($file))privateBackupHelp();
        $result=AttachmentVault::inspect($file,$key);
        echo "Authenticated private-file archive: ".json_encode($result,JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    if($operation==='backup'){
        $dir=\envValue('AGILE_FILE_BACKUP_DIR',dirname(__DIR__).'/storage/backups');
        if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir))
            throw new RuntimeException('Cannot create protected backup directory.');
        $root=realpath($dir);
        $public=realpath(dirname(__DIR__).'/public');
        if($root===false||($public!==false &&
            ($root===$public||str_starts_with($root,$public.DIRECTORY_SEPARATOR))))
            throw new RuntimeException('Backups may not be placed inside public web root.');
        $path=$root.'/agile-files-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.afb';
        $source=\envValue('AGILE_PRIVATE_FILES_DIR',dirname(__DIR__).'/storage/private');
        $summary=AttachmentVault::create($source,$path,$key);
        try {
            $check=AttachmentVault::inspect($path,$key);
            if($summary!==$check)throw new RuntimeException('Private archive verification did not match source file inventory.');
        }catch(\Throwable $e){@unlink($path);throw $e;}
        echo "Encrypted private-file archive created and checked: $path\n";
        echo "Inventory: ".json_encode($summary,JSON_THROW_ON_ERROR)."\n";
        echo "Pair with an encrypted database backup from the same approved snapshot window.\n";
        exit(0);
    }
    $file=$argv[2]??'';$dest=$argv[3]??'';
    if($file===''||!is_file($file)||is_link($file)||$dest===''||
       \envValue('AGILE_ALLOW_ISOLATED_RESTORE','false')!=='true')
        throw new DomainException('Restore is allowed only with an explicitly enabled isolated target.');
    // Verification pass authenticates ALL frames before files are created.
    $verified=AttachmentVault::inspect($file,$key);
    $restored=AttachmentVault::inspect($file,$key,$dest);
    if($verified!==$restored)throw new RuntimeException('Restored private-file inventory did not match.');
    echo "Restored private files into isolated folder $dest, inventory: "
       .json_encode($restored,JSON_THROW_ON_ERROR)."\n";
}catch(\Throwable $e){
    fwrite(STDERR,"Private file backup operation failed: ".$e->getMessage()."\n");
    exit(1);
}
