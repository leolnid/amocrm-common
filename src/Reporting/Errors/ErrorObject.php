<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final readonly class ErrorObject
{
    private const LABELS = ['rule'=>'Правило','lead'=>'Сделка','contact'=>'Контакт','request'=>'Запрос','module'=>'Модуль','account'=>'Аккаунт'];
    private function __construct(public string $type, public string $label, public string $value, public ?string $url = null) {}
    public static function id(mixed $value, bool $uuid = false): string
    {
        if (is_int($value) && $value > 0) return (string)$value;
        if (is_string($value) && preg_match('/^[1-9][0-9]{0,18}$/D', $value) && (strlen($value) < strlen((string)PHP_INT_MAX) || (strlen($value) === strlen((string)PHP_INT_MAX) && strcmp($value, (string)PHP_INT_MAX) <= 0))) return $value;
        if ($uuid && is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $value)) return strtolower($value);
        throw new \InvalidArgumentException('Invalid loaded object id');
    }
    public static function reference(string $type, mixed $id, ?string $url = null): self
    {
        if (!isset(self::LABELS[$type])) throw new \InvalidArgumentException('Invalid object type');
        $id = self::id($id, $type === 'account');
        if ($url !== null) {
            ErrorMessage::url($url);$parts=parse_url($url);$host=$parts['host'];$path=$parts['path']??'/';$quoted=preg_quote($id,'~');
            $allowed = match ($type) {
                'lead','contact'=>self::crmHost($host) && $path === '/'.($type === 'lead'?'leads':'contacts').'/detail/'.$id,
                'rule'=>$host === 'recurent-leads.amorenat.ru' && $path === '/rules/'.$id,
                'request'=>$host === 'mc.amorenat.ru' && $path === '/admin/requests/'.$id,
                'module'=>self::moduleLink($host, $path, $id),
                'account'=>$host === 'bp.amorenat.ru' && $path === '/admin/accounts/'.$id.'/show',
            };
            if (!$allowed) throw new \InvalidArgumentException('Object link outside registered route');
        }
        $value=($type === 'account' && str_contains($id,'-')) ? $id : '#'.$id;
        return new self($type,$type === 'account' && str_contains($id,'-') ? 'Аккаунт' : self::LABELS[$type].' #'.$id,$value,$url);
    }
    private static function moduleLink(string $host, string $path, string $id): bool
    {
        $prefix = match ($host) {'mc.amorenat.ru'=>'/admin/modules/accounts/', 'bp1.amorenat.ru'=>'/admin/accounts/', default=>null};
        if ($prefix === null || !preg_match('~^'.preg_quote($prefix,'~').'([^/]+)/modules/'.preg_quote($id,'~').'/edit$~D', $path, $match)) return false;
        if ($host === 'bp1.amorenat.ru') return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $match[1]) === 1;
        try { self::id($match[1]); return true; } catch (\InvalidArgumentException) { return false; }
    }
    public static function accountReference(mixed $id, ?string $url = null, ?string $crmHost = null): self
    {
        $reference=self::reference('account',$id,$url);
        return $crmHost !== null && self::crmHost($crmHost) ? new self('account','Аккаунт '.$crmHost,$crmHost,$reference->url) : $reference;
    }
    public static function crmAccount(string $host): self
    {
        if (!self::crmHost($host)) throw new \InvalidArgumentException('Invalid loaded CRM host');
        return new self('account','Аккаунт '.$host,$host, 'https://'.$host.'/');
    }
    public static function crmHost(string $host): bool { return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.amocrm\.(?:ru|com)$/D',$host) === 1; }
}
