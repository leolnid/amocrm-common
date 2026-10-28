<?php
declare(strict_types=1);

namespace GuzzleHttp\Psr7 {
    final class UriFixture
    {
        public function __construct(private string $host) {}
        public function getHost(): string { return $this->host; }
    }
    final class RequestFixture
    {
        public function __construct(private string $host) {}
        public function getUri(): UriFixture { return new UriFixture($this->host); }
    }
    final class ResponseFixture
    {
        public function __construct(private ?int $status, private string $body = '{}') {}
        public function getStatusCode(): int { return $this->status; }
        public function getBody(): BodyFixture { return new BodyFixture($this->body); }
    }
    final class BodyFixture
    {
        public function __construct(private string $body) {}
        public function __toString(): string { return $this->body; }
    }
}

namespace GuzzleHttp\Exception {
    final class ClientException extends \RuntimeException
    {
        public function __construct(private object $response, private object $request, string $message)
        {
            parent::__construct($message);
        }
        public function getResponse(): object { return $this->response; }
        public function getRequest(): object { return $this->request; }
    }
}

namespace Leolnid\Common\Reporting\Delivery {
    function error_log(string $message): bool
    {
        if (($GLOBALS['force_delivery_log_failure'] ?? false) === true) throw new \RuntimeException('synthetic logger failure');
        return \error_log($message);
    }
}

namespace {
    spl_autoload_register(static function ($class): void {
        $prefix = 'Leolnid\\Common\\Reporting\\';
        if (str_starts_with($class, $prefix)) {
            require __DIR__ . '/../../src/Reporting/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });

    use Leolnid\Common\Reporting\Delivery\FilesystemOutbox;
    use Leolnid\Common\Reporting\Delivery\NotificationTransport;

    final class FailingOutboxTransport implements NotificationTransport
    {
        public function __construct(private Throwable $error) {}
        public function send(array $payload): array { throw $this->error; }
    }

    function diagnosticAssert(bool $condition, string $message): void
    {
        if (!$condition) throw new RuntimeException('Outbox diagnostic fixture: ' . $message);
    }

    $logPath = tempnam(sys_get_temp_dir(), 'common-outbox-diagnostic-');
    if (!is_string($logPath)) throw new RuntimeException('Could not create local diagnostic fixture log');
    $previousLogPath = ini_get('error_log');
    $retryRoot = null;
    ini_set('error_log', $logPath);

    try {
        $id = '0123456789abcdef0123456789abcdef';
        $secret = 'fixture-token-must-not-appear';
        $uriPath = '/bot' . $secret . '/sendMessage?private=must-not-appear';
        $response = new \GuzzleHttp\Psr7\ResponseFixture(403);
        $request = new \GuzzleHttp\Psr7\RequestFixture('api.telegram.org');
        $error = new \GuzzleHttp\Exception\ClientException($response, $request, 'request URL ' . $uriPath . ' body secret');

        $method = new \ReflectionMethod(FilesystemOutbox::class, 'logDeliveryFailure');
        $method->invoke(null, $error, $id . '.json', 3);
        $logged = file_get_contents($logPath);
        $jsonStart = is_string($logged) ? strpos($logged, '{') : false;
        $record = $jsonStart === false ? null : json_decode(substr($logged, $jsonStart), true);

        diagnosticAssert(is_array($record) && ($record['event'] ?? null) === 'reporting_outbox_delivery_failed', 'failure event is recorded');
        diagnosticAssert(($record['envelope_id'] ?? null) === $id && ($record['attempt'] ?? null) === 3, 'opaque envelope id and attempt are correlated');
        diagnosticAssert(($record['exception_class'] ?? null) === 'GuzzleHttp\\Exception\\ClientException', 'exception class is retained');
        diagnosticAssert(($record['http_status'] ?? null) === 403, 'HTTP status is retained');
        diagnosticAssert(($record['service_host'] ?? null) === 'api.telegram.org', 'allowlisted service hostname is retained');
        diagnosticAssert(($record['reason_bucket'] ?? null) === 'http_403_forbidden', 'reason is limited to a safe category');
        diagnosticAssert(!str_contains($logged, $secret) && !str_contains($logged, $uriPath) && !str_contains($logged, 'private response'), 'token, URL path/query and response body are absent');

        file_put_contents($logPath, '');
        $otherRequest = new \GuzzleHttp\Psr7\RequestFixture('secret.internal.example');
        $otherError = new \GuzzleHttp\Exception\ClientException(new \GuzzleHttp\Psr7\ResponseFixture(403), $otherRequest, 'private URL');
        $method->invoke(null, $otherError, $id . '.json', 4);
        $otherLogged = file_get_contents($logPath);
        $otherStart = is_string($otherLogged) ? strpos($otherLogged, '{') : false;
        $otherRecord = $otherStart === false ? null : json_decode(substr($otherLogged, $otherStart), true);
        diagnosticAssert(is_array($otherRecord) && ($otherRecord['service_host'] ?? null) === 'other' && !str_contains($otherLogged, 'secret.internal.example'), 'unknown hostname is coarse-grained');

        $GLOBALS['force_delivery_log_failure'] = true;
        $method->invoke(null, $error, $id . '.json', 5);
        unset($GLOBALS['force_delivery_log_failure']);

        $retryIntegration = 'skipped_non_posix';
        $assertions = 9;
        if (function_exists('posix_geteuid')) {
            $retryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'common-outbox-retry-' . bin2hex(random_bytes(8));
            $outboxDir = $retryRoot . DIRECTORY_SEPARATOR . 'outbox';
            $dailyDir = $retryRoot . DIRECTORY_SEPARATOR . 'daily';
            mkdir($retryRoot, 0700, true);
            mkdir($outboxDir, 0700);
            mkdir($dailyDir, 0700);
            $retryId = 'fedcba9876543210fedcba9876543210';
            $retryPath = $outboxDir . DIRECTORY_SEPARATOR . $retryId . '.json';
            $retryPayload = ['text' => 'unchanged local fixture'];
            file_put_contents($retryPath, json_encode(['payload' => $retryPayload, 'attempts' => 0, 'created_at' => time(), 'not_before' => 0], JSON_THROW_ON_ERROR));
            $GLOBALS['force_delivery_log_failure'] = true;
            $outbox = new FilesystemOutbox(
                new FailingOutboxTransport($error),
                $outboxDir,
                $dailyDir,
                static fn (): bool => true,
                static fn (): bool => false,
                (int) gmdate('i') % 5,
            );
            $result = $outbox->drain();
            unset($GLOBALS['force_delivery_log_failure']);
            $saved = json_decode((string) file_get_contents($retryPath), true, 512, JSON_THROW_ON_ERROR);
            diagnosticAssert(($result['pending'] ?? null) === 1 && ($result['sent'] ?? null) === 0, 'logger failure does not break transport retry');
            diagnosticAssert(($saved['attempts'] ?? null) === 1 && ($saved['payload'] ?? null) === $retryPayload && ($saved['not_before'] ?? 0) >= time() + 55, 'retry state is persisted after diagnostic failure');
            $retryIntegration = 'PASS';
            $assertions += 2;
            foreach (glob($outboxDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) if (is_file($path)) unlink($path);
            rmdir($outboxDir);
            rmdir($dailyDir);
            rmdir($retryRoot);
            $retryRoot = null;
        }

        echo json_encode(['status' => 'PASS', 'assertions' => $assertions, 'logger_failure_fails_open' => true, 'retry_integration' => $retryIntegration, 'real_http_calls' => 0, 'payload_mutations' => 0, 'raw_request_or_response_logged' => false]) . "\n";
    } finally {
        if (is_string($previousLogPath)) ini_set('error_log', $previousLogPath);
        if (is_file($logPath)) unlink($logPath);
        if (is_string($retryRoot) && is_dir($retryRoot)) {
            foreach (['outbox', 'daily'] as $name) {
                $dir = $retryRoot . DIRECTORY_SEPARATOR . $name;
                foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) if (is_file($path)) unlink($path);
                if (is_dir($dir)) rmdir($dir);
            }
            rmdir($retryRoot);
        }
    }
}
