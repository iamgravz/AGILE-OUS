<?php
declare(strict_types=1);
namespace Agile;

use RuntimeException;

/**
 * Authenticated, streaming, encrypted backup container using libsodium
 * secretstream_xchacha20poly1305. Data is never saved as plaintext to disk.
 */
final class BackupCipher {
    private const MAGIC='AGILEBK1';
    private const CHUNK=32768;

    public static function keyFromEnvironment(): string {
        if (!extension_loaded('sodium')) throw new RuntimeException('PHP sodium extension is required.');
        $encoded=\envValue('AGILE_BACKUP_KEY_B64');
        $key=base64_decode($encoded,true);
        if (!is_string($key) || strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Set AGILE_BACKUP_KEY_B64 to a base64-encoded 32-byte private key.');
        }
        return $key;
    }

    private static function writeAll($stream,string $data): void {
        $len=strlen($data);$offset=0;
        while ($offset<$len) {
            $written=fwrite($stream,substr($data,$offset));
            if ($written===false || $written===0) throw new RuntimeException('Could not write encrypted backup stream.');
            $offset+=$written;
        }
    }
    private static function readExactly($stream,int $size): string {
        $data='';
        while (strlen($data)<$size) {
            $chunk=fread($stream,$size-strlen($data));
            if ($chunk===false || $chunk==='' && feof($stream)) {
                throw new RuntimeException('Backup is truncated or unreadable.');
            }
            if ($chunk==='') { usleep(1000);continue; }
            $data.=$chunk;
        }
        return $data;
    }

    public static function encrypt($source,$destination,string $key): int {
        if (strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Invalid backup encryption key.');
        }
        [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        self::writeAll($destination,self::MAGIC.$header);
        $total=0;
        while (!feof($source)) {
            $plain=fread($source,self::CHUNK);
            if ($plain===false) throw new RuntimeException('Database dump read failed.');
            if ($plain==='') {usleep(1000);continue;}
            $total+=strlen($plain);
            $encrypted=sodium_crypto_secretstream_xchacha20poly1305_push(
                $state,$plain,'',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
            );
            self::writeAll($destination,pack('N',strlen($encrypted)).$encrypted);
        }
        $final=sodium_crypto_secretstream_xchacha20poly1305_push(
            $state,'','',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
        );
        self::writeAll($destination,pack('N',strlen($final)).$final);
        return $total;
    }

    /**
     * Decrypt into an output stream or, when omitted, authenticate and discard.
     * A final authenticated tag is mandatory.
     */
    public static function decrypt($source,$destination,string $key): int {
        if (strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Invalid backup decryption key.');
        }
        $header=self::readExactly($source,strlen(self::MAGIC));
        if (!hash_equals(self::MAGIC,$header)) throw new RuntimeException('Unsupported backup format.');
        $nonce=self::readExactly($source,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull($nonce,$key);
        $total=0;
        while (true) {
            $lengthData=self::readExactly($source,4);
            $length=unpack('Nsize',$lengthData)['size'];
            if ($length<SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES ||
                $length>self::CHUNK+SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                throw new RuntimeException('Invalid encrypted record size.');
            }
            $record=self::readExactly($source,$length);
            $result=sodium_crypto_secretstream_xchacha20poly1305_pull($state,$record);
            if ($result===false) throw new RuntimeException('Backup authentication failed.');
            [$plain,$tag]=$result;
            if ($destination!==null && $plain!=='') self::writeAll($destination,$plain);
            $total+=strlen($plain);
            if ($tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                if (fread($source,1)!=='') throw new RuntimeException('Unexpected trailing bytes after backup.');
                return $total;
            }
            if ($tag!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) {
                throw new RuntimeException('Unsupported authenticated stream tag.');
            }
        }
    }
}
