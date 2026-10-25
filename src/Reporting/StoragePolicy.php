<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting;
/** Explicit modes preserve shared-UID ACLs; no recursive permission changes. */
final readonly class StoragePolicy
{
    public function __construct(public int $fileMode = 0600, public int $directoryMode = 0700)
    {
        if (!in_array([$fileMode,$directoryMode], [[0600,0700],[0640,0750],[0660,0770]], true)) throw new \InvalidArgumentException('Unsupported reporting storage policy');
    }
    public function ensureDirectory(string $path): void
    {
        if (is_link($path)) throw new \RuntimeException('Reporting directory symlink refused');
        if (!is_dir($path)) {
            $previous = umask(0777 ^ $this->directoryMode);
            try { if (!mkdir($path,$this->directoryMode,true) && !is_dir($path)) throw new \RuntimeException('Reporting directory unavailable'); }
            finally { umask($previous); }
        }
        $this->ensureMode($path,true);
    }
    public function ensureFileMode(string $path): void { $this->ensureMode($path,false); }
    private function ensureMode(string $path, bool $directory): void
    {
        clearstatcache(true,$path);$info=lstat($path);
        if (!is_array($info) || is_link($path) || (($info['mode'] & 0170000) !== ($directory ? 0040000 : 0100000))) throw new \RuntimeException('Invalid reporting storage entry');
        $expected=$directory ? $this->directoryMode : $this->fileMode;
        if (($info['mode'] & 0777) === $expected) return;
        if (!function_exists('posix_geteuid') || $info['uid'] !== posix_geteuid()) throw new \RuntimeException('Reporting storage mode requires owner review');
        if (!chmod($path,($info['mode'] & 07000) | $expected)) throw new \RuntimeException('Reporting storage mode unavailable');
        clearstatcache(true,$path);$after=lstat($path);
        if (!is_array($after) || $after['dev'] !== $info['dev'] || $after['ino'] !== $info['ino'] || ($after['mode'] & 0777) !== $expected) throw new \RuntimeException('Reporting storage changed during mode check');
    }
    /** @return resource */
    public function open(string $path, string $mode = 'c+b')
    {
        if (!in_array($mode,['c+b','x+b'],true) || is_link($path)) throw new \InvalidArgumentException('Invalid reporting storage open');
        $previous=umask(0666 & ~$this->fileMode);
        try { $stream=fopen($path,$mode); }
        finally { umask($previous); }
        if ($stream === false) throw new \RuntimeException('Reporting storage open failed');
        try {
            $this->ensureFileMode($path);$opened=fstat($stream);$named=lstat($path);
            if (!is_array($opened) || !is_array($named) || $opened['dev'] !== $named['dev'] || $opened['ino'] !== $named['ino']) throw new \RuntimeException('Reporting storage open changed');
            return $stream;
        } catch (\Throwable $error) { fclose($stream); throw $error; }
    }
    public function writeNew(string $path, string $content): void
    {
        $stream=$this->open($path,'x+b');
        try {
            if (!flock($stream,LOCK_EX) || fwrite($stream,$content) !== strlen($content) || !fflush($stream) || !fsync($stream)) throw new \RuntimeException('Reporting storage write failed');
        } finally { fclose($stream); }
    }
}
