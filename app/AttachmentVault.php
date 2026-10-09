<?php
declare(strict_types=1);
namespace Agile;

use DomainException;
use RuntimeException;

/**
 * Authenticated streaming backup for opaque private-file objects.
 * No plaintext archive is written to disk. Never restore over live storage.
 */
final class AttachmentVault {
    private const MAGIC='AGILEFS1';
    private const CHUNK=32768;
    private const MAX_FILE=5*1024*1024;
    private const MAX_FILES=20000;
    private const NAME_PATTERN='/^[a-f0-9]{40}\\.(pdf|jpg|png)$/D';

    public static function keyFromEnvironment():string {
        $encoded=\envValue('AGILE_FILE_BACKUP_KEY_B64');
        $key=base64_decode($encoded,true);
        if(!is_string($key) || strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)
            throw new RuntimeException('A separate 32-byte private-file backup key must be configured.');
        return $key;
    }
    private static function writeAll($fd,string $bytes):void {
        $sent=0;$length=strlen($bytes);
        while($sent<$length){
            $w=fwrite($fd,substr($bytes,$sent));
            if($w===false||$w===0)throw new RuntimeException('Encrypted archive write failed.');
            $sent+=$w;
        }
    }
    private static function readBytes($fd,int $amount):string {
        $out='';
        while(strlen($out)<$amount){
            $s=fread($fd,$amount-strlen($out));
            if($s===false || ($s===''&&feof($fd)))throw new RuntimeException('Encrypted archive is incomplete.');
            if($s==='')continue;
            $out.=$s;
        }
        return $out;
    }
    private static function frame(&$state,$fd,string $text,int $tag=SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE):void {
        $cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$text,'',$tag);
        self::writeAll($fd,pack('N',strlen($cipher)).$cipher);
    }
    private static function safeDirectory(string $path):string {
        $real=realpath($path);
        $public=realpath(dirname(__DIR__).'/public');
        if($real===false||!is_dir($real)||is_link($path)||
            ($public!==false&&($real===$public||str_starts_with($real,$public.DIRECTORY_SEPARATOR)))) {
            throw new RuntimeException('Private archive folders must be existing directories outside public/.');
        }
        return $real;
    }

    public static function create(string $source,string $destination,string $key):array {
        if(strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)
            throw new RuntimeException('Invalid private-file backup key.');
        $source=self::safeDirectory($source);
        $files=scandir($source);
        if($files===false)throw new RuntimeException('Unable to list private documents.');
        $names=[];
        foreach($files as $name){
            if($name==='.'||$name==='..')continue;
            if(!preg_match(self::NAME_PATTERN,$name) || is_link($source.'/'.$name) ||
               !is_file($source.'/'.$name)) {
                throw new RuntimeException('Unexpected private-file entry: backup cannot silently skip it.');
            }
            if(filesize($source.'/'.$name)>self::MAX_FILE)
                throw new RuntimeException('Private-file size exceeds archive limit.');
            $names[]=$name;
        }
        sort($names,SORT_STRING);
        if(count($names)>self::MAX_FILES)throw new RuntimeException('Too many private-file objects.');
        $fd=fopen($destination,'x+b');
        if($fd===false)throw new RuntimeException('Unable to create protected encrypted archive.');
        chmod($destination,0600);
        $finished=false;$total=0;
        try{
            [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            self::writeAll($fd,self::MAGIC.$header);
            foreach($names as $name){
                $path=$source.'/'.$name;
                $size=filesize($path);
                $originalHash=hash_file('sha256',$path);
                if(!is_string($originalHash))throw new RuntimeException('Could not hash source document.');
                self::frame($state,$fd,'F'.json_encode([
                    'name'=>$name,'size'=>$size,'sha256'=>$originalHash
                ],JSON_THROW_ON_ERROR));
                $read=fopen($path,'rb');
                if($read===false)throw new RuntimeException('Private document could not be opened.');
                $ctx=hash_init('sha256');$readBytes=0;
                try{
                    while(!feof($read)){
                        $data=fread($read,self::CHUNK);
                        if($data===false)throw new RuntimeException('Private document read failed.');
                        if($data==='')continue;
                        hash_update($ctx,$data);
                        $readBytes+=strlen($data);
                        if($readBytes>$size)throw new RuntimeException('Private document changed during snapshot.');
                        self::frame($state,$fd,'D'.$data);
                    }
                }finally{fclose($read);}
                if($readBytes!==$size || !hash_equals($originalHash,hash_final($ctx)))
                    throw new RuntimeException('Private document was modified during backup.');
                $total+=$size;
                self::frame($state,$fd,'E');
            }
            self::frame($state,$fd,'Z',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
            fflush($fd);
            $finished=true;
        }finally{
            fclose($fd);
            if(!$finished)@unlink($destination);
        }
        return ['files'=>count($names),'bytes'=>$total];
    }

    /** Verify encrypted contents; optional restore only into a fresh empty dir. */
    public static function inspect(string $archive,string $key,?string $restoreDir=null):array {
        if(strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)
            throw new RuntimeException('Invalid private-file backup key.');
        $target=null;$created=[];
        if($restoreDir!==null){
            $target=self::safeDirectory($restoreDir);
            if(!preg_match('/^agile_restore_files_[a-z0-9_]{1,45}$/D',basename($target)))
                throw new DomainException('Only isolated agile_restore_files_* destinations may be restored.');
            $files=scandir($target);
            if($files===false||count($files)!==2)
                throw new DomainException('Isolated private-file restore directory must be empty.');
        }
        $fd=fopen($archive,'rb');
        if($fd===false)throw new RuntimeException('Encrypted private archive not readable.');
        $open=null;$hash=null;$expected=0;$expectedHash='';$written=0;
        $count=0;$total=0;$previous='';
        try{
            if(self::readBytes($fd,strlen(self::MAGIC))!==self::MAGIC)
                throw new RuntimeException('Invalid encrypted private-file archive format.');
            $header=self::readBytes($fd,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull($header,$key);
            while(true){
                $size=unpack('Nlen',self::readBytes($fd,4))['len'];
                if($size<SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES+1 ||
                   $size>self::CHUNK+1+SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES+4096)
                    throw new RuntimeException('Invalid encrypted private-file frame length.');
                $decrypted=sodium_crypto_secretstream_xchacha20poly1305_pull($state,self::readBytes($fd,$size));
                if($decrypted===false)throw new RuntimeException('Encrypted private-file authentication failed.');
                [$frame,$tag]=$decrypted;
                $kind=$frame[0]??'';
                $payload=substr($frame,1);
                if($kind==='Z'){
                    if($open!==null || $hash!==null ||
                       $tag!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL ||
                       $payload!=='' || fread($fd,1)!=='')
                        throw new RuntimeException('Invalid encrypted archive final marker.');
                    return ['files'=>$count,'bytes'=>$total];
                }
                if($tag!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE)
                    throw new RuntimeException('Non-final frame has invalid authentication tag.');
                if($kind==='F'){
                    if($hash!==null || $count>=self::MAX_FILES)
                        throw new RuntimeException('Unexpected private-file metadata entry.');
                    $meta=json_decode($payload,true,16,JSON_THROW_ON_ERROR);
                    $name=$meta['name']??'';
                    $expected=$meta['size']??null;
                    $expectedHash=$meta['sha256']??'';
                    if(!is_string($name)||!preg_match(self::NAME_PATTERN,$name)||
                       strcmp($name,$previous)<=0 ||
                       !is_int($expected)||$expected<0||$expected>self::MAX_FILE||
                       !is_string($expectedHash)||!preg_match('/^[a-f0-9]{64}$/D',$expectedHash))
                        throw new RuntimeException('Unsafe or invalid archive file metadata.');
                    $previous=$name;$hash=hash_init('sha256');$written=0;
                    if($target!==null){
                        $dest=$target.'/'.$name;
                        $open=fopen($dest,'x+b');
                        if($open===false)throw new RuntimeException('Restore will not overwrite existing documents.');
                        chmod($dest,0600);
                        $created[]=$dest;
                    }
                }elseif($kind==='D'){
                    if($hash===null||strlen($payload)>self::CHUNK)
                        throw new RuntimeException('Unexpected private-file content frame.');
                    $written+=strlen($payload);
                    if($written>$expected)throw new RuntimeException('Archive document exceeds declared size.');
                    hash_update($hash,$payload);
                    if($open!==null)self::writeAll($open,$payload);
                }elseif($kind==='E'){
                    if($hash===null||$payload!==''||$written!==$expected||
                       !hash_equals($expectedHash,hash_final($hash)))
                        throw new RuntimeException('Archive document digest mismatch.');
                    if($open!==null){fflush($open);fclose($open);$open=null;}
                    $hash=null;$count++;$total+=$written;
                }else{
                    throw new RuntimeException('Unrecognized archive frame type.');
                }
            }
        }catch(\Throwable $e){
            if(is_resource($open))fclose($open);
            foreach($created as $file)@unlink($file);
            throw $e;
        }finally{fclose($fd);}
    }
}
