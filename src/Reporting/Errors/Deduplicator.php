<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class Deduplicator
{
    private static array $local = [];
    private int $repeatCount=0;
    private ?string $activeKey=null;
    public function __construct(private \Closure $atomicClaim, private int $seconds = 60, private ?\Closure $atomicState = null) {}
    public function first(array $metadata): bool
    {
        // Preserve every account/object/operation/reason field; never project a financial identity away.
        $key = 'app-errors:'.hash('sha256', json_encode(self::canonical($metadata), JSON_THROW_ON_ERROR));
        $ttl=max(10,min(300,$this->seconds));$this->activeKey=$key;$this->repeatCount=0;
        if ($this->atomicState!==null) {
            try {
                $result=($this->atomicState)($key,$ttl,'claim',0);
                if (is_array($result) && is_bool($result['first']??null)) {
                    $this->repeatCount=max(0,min(999999,(int)($result['repeat_count']??0)));
                    return $result['first'];
                }
            } catch (\Throwable) { /* Keep the original atomic claim fallback. */ }
        }
        return $this->claim($key,$ttl);
    }
    public function repeatCount(): int { return $this->repeatCount; }
    public function acknowledgeRepeats(): void
    {
        if ($this->atomicState===null || $this->activeKey===null || $this->repeatCount<1) return;
        try { ($this->atomicState)($this->activeKey,max(10,min(300,$this->seconds)),'ack',$this->repeatCount); } catch (\Throwable) {}
        $this->repeatCount=0;
    }
    public function firstHealth(HealthCheckIdentity $identity): bool
    {
        $this->activeKey=null;$this->repeatCount=0;
        $key='app-errors:health:'.hash('sha256',json_encode(self::canonical($identity->metadata()),JSON_THROW_ON_ERROR));
        return $this->claim($key,60);
    }
    private static function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (!array_is_list($value)) ksort($value,SORT_STRING);
            foreach ($value as $key=>$child) $value[$key]=self::canonical($child);
            return $value;
        }
        if ($value === null || is_scalar($value)) return $value;
        throw new \InvalidArgumentException('Non-scalar notification identity');
    }
    private function claim(string $key, int $ttl): bool
    {
        try { return ($this->atomicClaim)($key,$ttl) === true; }
        catch (\Throwable) {
            $now=time();
            self::$local=array_filter(self::$local,static fn(int $expiry):bool=>$expiry>$now);
            if (isset(self::$local[$key])) return false;
            if (count(self::$local)>=128) array_shift(self::$local);
            self::$local[$key]=$now+$ttl;
            return true;
        }
    }
}
