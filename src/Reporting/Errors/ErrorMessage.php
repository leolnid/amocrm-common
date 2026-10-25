<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final readonly class ErrorMessage
{
    /** @param list<ErrorObject> $objects */
    public function __construct(
        public string $project,
        public string $projectUrl,
        public ErrorAction $failure,
        public ErrorReason $reason,
        public array $objects = [],
        public bool $test = false,
    ) {
        self::text($project, 100);
        self::url($projectUrl);
        if ((parse_url($projectUrl,PHP_URL_PATH) ?? '/') !== '/') throw new \InvalidArgumentException('Project link must be its registered root');
        if (!array_is_list($objects) || count($objects) > 3) throw new \InvalidArgumentException('Invalid notification objects');
        foreach ($objects as $object) if (!$object instanceof ErrorObject) throw new \InvalidArgumentException('Invalid notification object');
        if ($reason->code === 'processing_error' && !array_any($objects,static fn(ErrorObject $object):bool=>$object->url !== null)) throw new \InvalidArgumentException('Unknown reason requires actionable object link');
    }
    public static function text(string $value, int $limit): void
    {
        if ($value === '' || strlen($value) > $limit * 4 || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)) throw new \InvalidArgumentException('Invalid notification text');
    }
    public static function url(string $url): void
    {
        $parts = parse_url($url);
        if (strlen($url) > 512 || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment']) || !preg_match('/^[a-z0-9-]+\.(?:amorenat\.ru|amocrm\.(?:ru|com))$/D', $parts['host'] ?? '') || (!preg_match('~^/[A-Za-z0-9_./-]*$~D', $parts['path'] ?? '/') || preg_match('~(?:^|/)\.\.?(?:/|$)~', $parts['path'] ?? '/'))) throw new \InvalidArgumentException('Invalid notification link');
    }
}
