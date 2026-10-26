<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final readonly class ErrorMessage
{
    /** @param list<ErrorObject> $objects @param list<ErrorContextItem> $context */
    public function __construct(
        public string $project,
        public string $projectUrl,
        public ErrorAction $failure,
        public ErrorReason $reason,
        public array $objects = [],
        public bool $test = false,
        public array $context = [],
        public ?string $eventCode = null,
        public int $repeatCount = 0,
    ) {
        self::text($project, 100);
        self::url($projectUrl);
        if (!array_is_list($objects) || count($objects) > 3) throw new \InvalidArgumentException('Invalid notification objects');
        foreach ($objects as $object) if (!$object instanceof ErrorObject) throw new \InvalidArgumentException('Invalid notification object');
        if (!array_is_list($context)) throw new \InvalidArgumentException('Invalid notification context');
        foreach ($context as $item) if (!$item instanceof ErrorContextItem) throw new \InvalidArgumentException('Invalid notification context item');
        if ($eventCode !== null && !preg_match('/^[a-f0-9]{16}$/D',$eventCode)) throw new \InvalidArgumentException('Invalid stable event code');
        if ($repeatCount<0 || $repeatCount>999999) throw new \InvalidArgumentException('Invalid repeat count');
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
    public function withEventCode(string $eventCode): self
    {
        return new self($this->project,$this->projectUrl,$this->failure,$this->reason,$this->objects,$this->test,$this->context,$eventCode,$this->repeatCount);
    }
    public function withRepeatCount(int $repeatCount): self
    {
        return new self($this->project,$this->projectUrl,$this->failure,$this->reason,$this->objects,$this->test,$this->context,$this->eventCode,$repeatCount);
    }
}
