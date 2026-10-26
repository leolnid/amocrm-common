<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;

/** A single application-registered business entity already loaded by its caller. */
final readonly class ErrorContextItem
{
    private function __construct(
        public string $key,
        public string $label,
        public string $name,
        public ?string $id,
        public ?string $url,
        public ?string $receivedAt,
    ) {}

    public static function loaded(string $key, string $label, mixed $id = null, ?string $name = null, ?string $url = null, ?string $receivedAt = null): self
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$key)) throw new \InvalidArgumentException('Unregistered context key');
        if (!preg_match('/^[\p{L}\p{N} _-]{1,50}$/uD',$label)) throw new \InvalidArgumentException('Invalid registered context label');
        $id=$id===null ? null : ($key==='account' ? ErrorObject::id($id,false) : self::identifier($id));
        if($key==='request'&&$id===null)throw new \InvalidArgumentException('Request identifier missing');
        if ($id===null && ($name===null || $name==='')) throw new \InvalidArgumentException('Context identifier and name missing');
        if ($name !== null) {
            $name=trim(preg_replace('/\s+/u',' ',$name)??'');
            if ($name==='') $name=null;
            if ($name!==null) {
                if (function_exists('mb_substr')) $name=mb_substr($name,0,120,'UTF-8');
                else { $chars=preg_split('//u',$name,-1,PREG_SPLIT_NO_EMPTY);$name=implode('',array_slice($chars?:[],0,120)); }
                ErrorMessage::text($name,120);
            }
        }
        if ($url !== null) ErrorMessage::url($url);
        if ($receivedAt !== null) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D',$receivedAt)) throw new \InvalidArgumentException('Context event time invalid');
            try { new \DateTimeImmutable($receivedAt); } catch (\Throwable) { throw new \InvalidArgumentException('Request receive time invalid'); }
        }
        return new self($key,$label,$name??'',$id,$url,$receivedAt);
    }

    public static function identifier(mixed $value): string
    {
        try { return ErrorObject::id($value,true); } catch (\InvalidArgumentException) {}
        if (is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/D',$value)) return $value;
        throw new \InvalidArgumentException('Invalid loaded context identifier');
    }

    public function display(): string
    {
        $identity=$this->id===null ? $this->name : ($this->name==='' ? $this->id : $this->name.' ('.$this->id.')');
        return $this->receivedAt===null ? $identity : $this->receivedAt.' · '.$identity;
    }
}
