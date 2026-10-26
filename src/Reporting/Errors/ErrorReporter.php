<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
/** Local metadata and storage ports are supplied explicitly by the application. */
final class ErrorReporter
{
    private static bool $reporting = false;
    private \WeakMap $observed;
    private \WeakMap $correlations;
    public ?array $lastReceipt = null;
    public function __construct(
        private Deduplicator $deduplicator,
        private \Closure $log,
        private \Closure $ready,
        private \Closure $send,
        private \Closure $outbox,
        private string $chatId,
        private int $threadId,
    ) { $this->observed = new \WeakMap; $this->correlations = new \WeakMap; }
    public function report(\Throwable $error, ?ErrorMessage $message, array $metadata, bool $allowRemote = true, ?HealthCheckIdentity $health = null): bool
    {
        if (self::$reporting) return false;
        self::$reporting = true;
        try {
            $correlation = $this->correlations[$error] ??= bin2hex(random_bytes(8));
            $test = $message?->test ?? false;
            $this->write('error', $test ? 'TEST application error reporting' : 'Application error', $metadata + ['event_kind'=>'application_error', 'is_test'=>$test, 'occurred_at_utc'=>gmdate('c'), 'correlation_id'=>$correlation]);
            if ($message === null || !$allowRemote || ($this->ready)() !== true || isset($this->observed[$error])) return false;
            if (!($health === null ? $this->deduplicator->first($metadata) : $this->deduplicator->firstHealth($health))) return false;
            $this->observed[$error] = true;
            $renderMessage=$message->withEventCode($correlation)->withRepeatCount($this->deduplicator->repeatCount());
            $payload = ['chat_id'=>$this->chatId, 'message_thread_id'=>$this->threadId, 'text'=>CompactErrorFormatter::render($renderMessage), 'parse_mode'=>'HTML', 'disable_web_page_preview'=>true, 'disable_notification'=>false];
            try { $this->lastReceipt = ($this->send)($payload); $this->deduplicator->acknowledgeRepeats(); return true; }
            catch (\Throwable $delivery) {
                $queued=false;
                try { ($this->outbox)($payload, $delivery); $queued=true; }
                catch (\Throwable $failure) { $this->write('critical', 'Application error outbox unavailable', ['failure_class'=>self::exceptionName($failure)]); }
                if ($queued) $this->deduplicator->acknowledgeRepeats();
                $this->write('warning', 'Application error notification delivery failed', ['failure_class'=>self::exceptionName($delivery), 'correlation_id'=>$correlation]);
                return false;
            }
        } catch (\Throwable $failure) {
            $this->write('warning', 'Application error observer failed', ['failure_class'=>self::exceptionName($failure)]);
            return false;
        } finally { self::$reporting = false; }
    }
    private static function exceptionName(\Throwable $failure): string
    {
        $name = $failure::class;
        return preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]{0,199}$/D', $name) === 1 ? $name : 'Throwable';
    }
    private function write(string $level, string $message, array $metadata): void
    {
        try { ($this->log)($level, $message, $metadata); }
        catch (\Throwable) { try { error_log($message); } catch (\Throwable) {} }
    }
}
