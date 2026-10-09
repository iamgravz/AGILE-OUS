<?php
declare(strict_types=1);
namespace Agile;

final class StagingPreflight {
    /** Return actionable launch blockers without logging secrets. */
    public static function validate(array $config):array {
        $get=static fn(string $key):string=>(string)($config[$key]??'');
        $errors=[];
        if($get('APP_ENV')!=='staging')$errors[]='APP_ENV must be staging, not production/local.';
        if(!str_starts_with($get('APP_URL'),'https://'))
            $errors[]='APP_URL must use HTTPS on the approved staging hostname.';
        if($get('SESSION_SECURE')!=='true')
            $errors[]='SESSION_SECURE must be true behind verified TLS.';
        foreach(['MFA_KEY_B64','AGILE_BACKUP_KEY_B64','AGILE_FILE_BACKUP_KEY_B64'] as $key) {
            $plain=base64_decode($get($key),true);
            if(!is_string($plain)||strlen($plain)!==32)
                $errors[]=$key.' must be provisioned by the secret manager (32 bytes, base64).';
        }
        if($get('FILE_SCANNING_ENABLED')!=='true' || $get('FILE_SCAN_DRIVER')!=='clamav')
            $errors[]='ClamAV scanning must be enabled, not the CI-only synthetic scanner.';
        if($get('MAIL_TRANSPORT')!=='disabled')
            $errors[]='Staging email delivery must remain disabled until isolated authorized Gmail tests are approved.';
        if($get('AI_EXTERNAL_PROCESSING_APPROVED')!=='false')
            $errors[]='External AI processing must remain disabled for staging synthetic exercises.';
        if($get('ACADEMIC_ROLE_TRANSITIONS_ENABLED')!=='false')
            $errors[]='Academic role transitions must remain disabled under draft bylaws.';
        if($get('AUTOMATION_ENABLED')!=='false')
            $errors[]='Scheduled operational jobs must be enabled only by an approved staging rollout.';
        if($get('AGILE_ALLOW_ISOLATED_RESTORE')==='true')
            $errors[]='Isolated restore gate must not remain enabled in the web app environment.';
        return $errors;
    }
    public static function inspectEnvironment():array {
        $names=[
          'APP_ENV','APP_URL','SESSION_SECURE','MFA_KEY_B64','AGILE_BACKUP_KEY_B64',
          'AGILE_FILE_BACKUP_KEY_B64','FILE_SCANNING_ENABLED','FILE_SCAN_DRIVER',
          'MAIL_TRANSPORT','AI_EXTERNAL_PROCESSING_APPROVED','ACADEMIC_ROLE_TRANSITIONS_ENABLED',
          'AUTOMATION_ENABLED','AGILE_ALLOW_ISOLATED_RESTORE'
        ];
        $config=[];
        foreach($names as $name)$config[$name]=\envValue($name);
        $issues=self::validate($config);
        if(!extension_loaded('sodium'))$issues[]='PHP sodium extension is required.';
        if(!extension_loaded('fileinfo'))$issues[]='PHP fileinfo extension is required.';
        if(!extension_loaded('pdo_mysql'))$issues[]='PHP pdo_mysql extension is required.';
        $found=false;
        foreach(explode(PATH_SEPARATOR,getenv('PATH')?:'') as $folder){
            if($folder!=='' && is_file($folder.'/clamscan') && is_executable($folder.'/clamscan')){
                $found=true;break;
            }
        }
        if(!$found)$issues[]='ClamAV clamscan executable not found in the staging worker PATH.';
        $private=realpath(dirname(__DIR__).'/storage/private');
        $public=realpath(dirname(__DIR__).'/public');
        if($private===false||$public===false||
            $private===$public||str_starts_with($private,$public.DIRECTORY_SEPARATOR)){
            $issues[]='Private attachments must exist outside the public document root.';
        }
        return $issues;
    }
}
