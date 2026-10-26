<?php
declare(strict_types=1);
spl_autoload_register(static function($class){$prefix='Leolnid\\Common\\Reporting\\';if(str_starts_with($class,$prefix))require __DIR__.'/../../src/Reporting/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
use Leolnid\Common\Reporting\Errors\{ErrorMessage,ErrorContextItem,ErrorAction,ErrorReason,CompactErrorFormatter,ContextAdapter,OperationResolver};
function verify(bool $ok,string $name):void {if(!$ok)throw new RuntimeException('Nullable project link fixture: '.$name);}
$entity=ErrorContextItem::loaded('order','Заказ',42,'Заказ 42','https://leads-automove.amorenat.ru/orders/42');
$message=new ErrorMessage('Вебхук проекта',null,ErrorAction::known('lead_process'),new ErrorReason('processing_error'),[],false,[$entity]);
$rendered=CompactErrorFormatter::render($message);
verify(str_starts_with($rendered,'⚠️ <b>Вебхук проекта</b>'),'null project URL renders plain bold title');
verify(!str_contains($rendered,'href=')||str_contains($rendered,'href="https://leads-automove.amorenat.ru/orders/42"'),'null project URL does not remove validated entity links');
verify(str_contains($rendered,'<b>Заказ</b>: <a href="https://leads-automove.amorenat.ru/orders/42">Заказ 42 (42)</a>'),'entity link remains rendered');
$definition=['operations'=>['job:Sync'=>'Синхронизация'],'default_service'=>'internal','kinds'=>['known'=>['action'=>['known'=>'lead_process'],'reason'=>['code'=>'processing_error'],'context'=>[['key'=>'order','id'=>'order_id','name'=>'order_name','url'=>'https://leads-automove.amorenat.ru/orders/{order_id}','params'=>['order_id'=>'id']]]]],'context_catalog'=>['order'=>'Заказ']];
$normalized=ContextAdapter::normalize('Вебхук проекта',null,(object)['kind'=>'known','order_id'=>42,'order_name'=>'Заказ 42'],$definition,false,new RuntimeException('failure'));
verify($normalized instanceof ErrorMessage && $normalized->projectUrl===null,'adapter accepts absent admin landing');
verify(str_contains(CompactErrorFormatter::render($normalized),'href="https://leads-automove.amorenat.ru/orders/42"'),'adapter keeps validated dynamic links');
$resolved=OperationResolver::resolve(new RuntimeException('failure'),'Вебхук проекта',null,['source'=>'queue','job_class'=>'Unmapped'],['operations'=>[]]);
verify($resolved===null,'unregistered operation stays unregistered');
try {new ErrorMessage('Bad URL','',ErrorAction::known('lead_process'),new ErrorReason('processing_error'));verify(false,'empty URL remains invalid');} catch(InvalidArgumentException) {verify(true,'empty URL remains invalid');}
echo "PASS 6 assertions\n";
