<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class SourcePins
{
    public static function verify(string $root, array $pin): bool
    {
        $relative=$pin['path']??null;$expected=$pin['sha256_lf']??null;
        if (!is_string($relative)||!preg_match('~^[A-Za-z0-9_./-]{1,300}$~D',$relative)||str_contains($relative,'..')||str_starts_with($relative,'/')||!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected)) return false;
        $base=realpath($root);$file=$base===false?false:realpath($base.'/'.$relative);
        if ($base===false||$file===false||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file)||filesize($file)>1048576) return false;
        $raw=@file_get_contents($file);
        return is_string($raw)&&hash_equals($expected,hash('sha256',str_replace("\r\n","\n",$raw)));
    }
}
