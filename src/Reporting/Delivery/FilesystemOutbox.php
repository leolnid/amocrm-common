<?php
namespace Leolnid\Common\Reporting\Delivery;

use Throwable;
use Leolnid\Common\Reporting\StoragePolicy;
class FilesystemOutbox
{
    public function __construct(private NotificationTransport $transport, private string $path, private string $dailyReportsPath, private \Closure $enabled, private \Closure $paused, private int $projectIndex = 0, private StoragePolicy $policy = new StoragePolicy()) {}
    private function directory(): string
    {
        $path = $this->path;
        $this->policy->ensureDirectory($path);
        return $path;
    }
    private function write(string $path, array $item): void
    {
        $tmp=$path.'.tmp-'.bin2hex(random_bytes(8));
        try {
            $this->policy->writeNew($tmp,json_encode($item,JSON_THROW_ON_ERROR));
            if(!rename($tmp,$path)) throw new \RuntimeException('Outbox atomic rename failed');
        } finally {if(is_file($tmp))@unlink($tmp);}
    }
    private function recordReceipt(array $receipt, string $outboxPath): void
    {
        $chat=(string)($receipt['chat_id']??'');$message=(int)($receipt['message_id']??0);$thread=(int)($receipt['message_thread_id']??0);
        if(!preg_match('/^-?[0-9]{1,20}$/D',$chat)||$message<=0||$thread<=0)throw new \UnexpectedValueException('Sanitized receipt missing');
        $dir=$this->directory().'/receipts';$this->policy->ensureDirectory($dir);
        $id=basename($outboxPath,'.json');if(!preg_match('/^[a-f0-9]{32}$/D',$id))$id=substr(hash('sha256',$id),0,32);
        $row=['outbox_id'=>$id,'accepted_at_utc'=>gmdate('c'),'message_id'=>$message,'chat_id'=>$chat,'message_thread_id'=>$thread];
        $file=$dir.'/'.gmdate('Y-m-d').'.jsonl';$f=$this->policy->open($file);if(!flock($f,LOCK_EX))throw new \RuntimeException('Receipt journal lock failed');
        try {
            $size=fstat($f)['size'];if($size>1048576)throw new \OverflowException('Receipt journal capacity');
            if($size>0){fseek($f,-1,SEEK_END);if(fread($f,1)!=="\n"){fseek($f,0,SEEK_END);fwrite($f,"\n");error_log('Receipt journal interrupted record requires review');}}
            fseek($f,0,SEEK_END);$line=json_encode($row,JSON_THROW_ON_ERROR)."\n";if(fwrite($f,$line)!==strlen($line))throw new \RuntimeException('Receipt journal write failed');fflush($f);fsync($f);
        } finally {flock($f,LOCK_UN);fclose($f);}
        $cutoff=gmdate('Y-m-d',time()-7*86400);foreach(glob($dir.'/*.jsonl') as $old)if(preg_match('/^(\d{4}-\d{2}-\d{2})\.jsonl$/D',basename($old),$m)&&$m[1]<$cutoff)unlink($old);
    }
    public static function retryDelay(Throwable $error): int
    {
        try {
            for ($depth=0; $depth<5; $depth++) {
            if (method_exists($error, 'getResponse') && $error->getResponse()) {
                $data=json_decode((string)$error->getResponse()->getBody(),true);
                $seconds=$data['parameters']['retry_after']??null;
                if (is_numeric($seconds)) return max(1,min(86400,(int)$seconds));
            }
            $previous=$error->getPrevious();
            if (!$previous) break;
            $error=$previous;
            }
        } catch (Throwable) {}
        return 60;
    }
    public function put(array $payload, Throwable $error, ?string $receiptPath = null): void
    {
        $dir=$this->directory();
        $lock=$this->policy->open($dir.'/.lock');
        if (!$lock || !flock($lock,LOCK_EX)) throw new \RuntimeException('Outbox lock unavailable');
        try {
            if(count(glob($dir.'/*.json'))>=100) throw new \OverflowException('Outbox capacity reached');
            $path=$dir.'/'.bin2hex(random_bytes(16)).'.json';
            $this->write($path,['payload'=>$payload,'attempts'=>0,'created_at'=>time(),'not_before'=>time()+self::retryDelay($error),'receipt_path'=>$receiptPath]);
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    public function drain(): array
    {
        if (($this->paused)() !== false || ($this->enabled)() !== true) return ['delivery_paused'=>true];
        if (((int)gmdate('i') % 5) !== ($this->projectIndex % 5)) return ['slot_deferred'=>true];
        $dir=$this->directory();$lock=$this->policy->open($dir.'/.lock');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return ['busy'=>true];
        $sent=0;$pending=0;$expired=0;$receiptReview=0;$corrupt=count(glob($dir.'/*.invalid'));$interrupted=count(glob($dir.'/*.tmp-*'));
        try {
            foreach(glob($dir.'/*.json') as $path) {
                try {
                    $item=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
                    if(!is_array($item)||!isset($item['payload'],$item['created_at'],$item['not_before'],$item['attempts'])||!is_array($item['payload']))throw new \UnexpectedValueException();
                } catch(Throwable) {rename($path,$path.'.invalid');$corrupt++;continue;}
                if($item['created_at']<time()-86400||$item['attempts']>=20){$expired++;continue;}
                if($item['not_before']>time()||$sent>=1){$pending++;continue;}
                try {$receipt=$this->transport->send($item['payload']);
                    try {$this->recordReceipt($receipt,$path);}catch(Throwable){$receiptReview++;error_log('Delivered notification receipt requires review');}
                    unlink($path);$sent++;
                    if(is_string($item['receipt_path']??null)){
                        $target=$item['receipt_path'];
                        if(preg_match('/^\d{4}-\d{2}-\d{2}\.json$/D',basename($target))&&realpath(dirname($target))===realpath($this->dailyReportsPath)){
                            try {$this->write($target,['state'=>'accepted','date'=>substr(basename($target),0,10),'receipt'=>array_intersect_key($receipt,array_fill_keys(['message_id','chat_id','message_thread_id'],true))]);}
                            catch(Throwable){error_log('Delivered daily report receipt requires review');}
                        }
                    }}
                catch(Throwable $error){$item['attempts']++;$item['not_before']=time()+self::retryDelay($error);$this->write($path,$item);$pending++;break;}
            }
        } finally {flock($lock,LOCK_UN);fclose($lock);}
        return ['sent'=>$sent,'pending'=>$pending,'expired_requires_review'=>$expired,'corrupt_requires_review'=>$corrupt,'interrupted_requires_review'=>$interrupted,'receipt_journal_requires_review'=>$receiptReview];
    }
}
