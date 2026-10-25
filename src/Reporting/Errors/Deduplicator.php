<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final class Deduplicator
{
    private static array $local = [];
    public function __construct(private \Closure $atomicClaim, private int $seconds = 60) {}
    public function first(array $metadata): bool
    {
        // Preserve every account/object/operation/reason field; never project a financial identity away.
        $key = 'app-errors:'.hash('sha256', json_encode(self::canonical($metadata), JSON_THROW_ON_ERROR));
        return $this->claim($key,max(10,min(300,$this->seconds)));
    }
    public function firstHealth(HealthCheckIdentity $identity): bool
    {
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
