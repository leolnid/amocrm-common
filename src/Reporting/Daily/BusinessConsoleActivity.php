<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Daily;
use Throwable;
/** Named command metadata only: input, output, arguments and business objects are never read. */
final class BusinessConsoleActivity
{
 private static array $stack=[];
 /** @param callable(string,string,string):bool $record */
 public static function starting(?string $name,array $catalog,bool $enabled,?string $coverageFrom,callable $correlate,callable $record,callable $gap): void
 {
  try {
   if(count(self::$stack)>=16){self::$stack=[];self::gap($gap);return;}
   self::$stack[]=['name'=>$name,'unit'=>null];
   $unit=is_string($name)?($catalog[$name]??null):null;
   $from=strtotime((string)$coverageFrom);
   if(!$enabled||$from===false||$from>time()||!in_array($unit,['batch','mail_poll'],true)||!preg_match('/^[a-zA-Z0-9:_-]{1,96}$/D',(string)$name))return;
   $id=$correlate('console:'.bin2hex(random_bytes(16)));
   self::$stack[array_key_last(self::$stack)]=['name'=>$name,'unit'=>$unit,'id'=>$id];
   if($record('accepted',$id,'cli:started:'.$unit.':'.$name)!==true)self::gap($gap);
  }catch(Throwable){self::gap($gap);error_log('Console activity observer unavailable');}
 }
 /** @param callable(string,string,string):bool $record */
 public static function finished(?string $name,?int $exitCode,callable $record,callable $gap): void
 {
  try {
   if(!self::$stack)return;
   $row=array_pop(self::$stack);
   if($row['name']!==$name){self::$stack=[];self::gap($gap);return;}
   if($row['unit']===null)return;
   if($exitCode===null){self::gap($gap);return;}
   if($record($exitCode===0?'processed':'failed',$row['id'],'cli:finished:'.$row['unit'].':'.$name)!==true)self::gap($gap);
  }catch(Throwable){self::gap($gap);error_log('Console activity observer unavailable');}
 }
 private static function gap(callable $gap):void {try {$gap();}catch(Throwable){error_log('Console activity gap unavailable');}}
 public static function clear(): void {self::$stack=[];}
}
