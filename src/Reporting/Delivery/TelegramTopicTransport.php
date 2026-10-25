<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Delivery;
/** Explicit opt-in: the host supplies its existing SDK and protected destination. */
final readonly class TelegramTopicTransport implements NotificationTransport
{
    public function __construct(private string $chatId, private int $threadId, private \Closure $send, private \Closure $enabled, private \Closure $paused)
    {
        if (!preg_match('/^-[0-9]{1,20}$/D', $chatId) || $threadId < 1) throw new \InvalidArgumentException('Invalid topic destination');
    }
    public function send(array $payload): array
    {
        if (($this->enabled)() !== true || ($this->paused)() !== false) throw new \RuntimeException('Technical delivery paused');
        if ((string)($payload['chat_id'] ?? '') !== $this->chatId || ($payload['message_thread_id'] ?? null) !== $this->threadId || ($payload['parse_mode'] ?? '') !== 'HTML' || !is_string($payload['text'] ?? null) || $payload['text'] === '' || strlen($payload['text']) > 16000) throw new \InvalidArgumentException('Unapproved topic payload');
        $data = ($this->send)($payload);
        if (!is_array($data) || ($data['ok'] ?? false) !== true) throw new \UnexpectedValueException('Telegram receipt missing');
        $result = $data['result'] ?? [];
        if (!is_array($result) || !is_int($result['message_id'] ?? null) || $result['message_id'] < 1 || (string)($result['chat']['id'] ?? '') !== $this->chatId || ($result['message_thread_id'] ?? null) !== $this->threadId) throw new \UnexpectedValueException('Technical receipt destination mismatch');
        return ['message_id'=>$result['message_id'], 'chat_id'=>$this->chatId, 'message_thread_id'=>$this->threadId];
    }
}
