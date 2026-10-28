<?php
declare(strict_types=1);

$GLOBALS['telegram_fixture_config'] = [];
$GLOBALS['telegram_fixture_checks'] = 0;

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['telegram_fixture_config'][$key] ?? $default;
}

function storage_path(string $path = ''): string
{
    return __DIR__ . '/not-created/' . $path;
}

function checkTelegramFixture(bool $condition, string $name): void
{
    $GLOBALS['telegram_fixture_checks']++;
    if (!$condition) {
        throw new RuntimeException('Telegram proxy fixture failed: ' . $name);
    }
}

function rejectTelegramFixture(callable $callback, string $name): void
{
    try {
        $callback();
    } catch (Throwable) {
        checkTelegramFixture(true, $name);
        return;
    }
    checkTelegramFixture(false, $name);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Leolnid\\Common\\Reporting\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/../../src/Reporting/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

final class TelegramProxyFixtureHttpClient
{
    public static array $instances = [];

    public function __construct(public array $options)
    {
        self::$instances[] = $this;
    }
}

final class TelegramProxyFixtureResponse
{
    public function __construct(private string $body)
    {
    }

    public function getBody(): string
    {
        return $this->body;
    }
}

final class TelegramProxyFixtureSdk
{
    public static array $instances = [];
    public static ?array $lastPayload = null;

    public function __construct(public string $botToken, public object $client, public ?string $baseUri)
    {
        self::$instances[] = $this;
    }

    public function sendMessage(array $payload): TelegramProxyFixtureResponse
    {
        self::$lastPayload = $payload;
        return new TelegramProxyFixtureResponse(json_encode([
            'ok' => true,
            'result' => [
                'message_id' => 7,
                'chat' => ['id' => $payload['chat_id']],
                'message_thread_id' => $payload['message_thread_id'],
            ],
        ], JSON_THROW_ON_ERROR));
    }
}

final class TelegramProxyFixtureTransport extends \Leolnid\Common\Reporting\Capture\LaravelTelegramTransport
{
    protected const SDK_CLASS = TelegramProxyFixtureSdk::class;
    protected const HTTP_CLIENT_CLASS = TelegramProxyFixtureHttpClient::class;
    protected const APPROVED_CHAT_ID = '-1000000000000';
}

function fixtureConfig(?string $baseUri, ?string $relayToken): void
{
    $GLOBALS['telegram_fixture_config'] = [
        'services.telegram-bot-api.base_uri' => $baseUri,
        'services.telegram-bot-api.relay_token' => $relayToken,
        'app_errors.telegram.base_uri' => null,
        'app_errors.telegram.relay_token' => null,
        'app_errors.telegram.bot_token' => 'fixture-bot-token',
        'app_errors.telegram.chat_id' => '-1000000000000',
        'app_errors.telegram.message_thread_id' => 17,
        'app_errors.enabled' => true,
    ];
}

function fixturePayload(): array
{
    return [
        'chat_id' => '-1000000000000',
        'message_thread_id' => 17,
        'parse_mode' => 'HTML',
        'text' => '<b>Fixture</b>',
    ];
}

$relay = 'https://tg-proxy.makeroi.dev/relay/https://api.telegram.org';
fixtureConfig($relay, 'fixture-relay-token');
$result = (new TelegramProxyFixtureTransport())->send(fixturePayload());
checkTelegramFixture($result === ['message_id' => 7, 'chat_id' => '-1000000000000', 'message_thread_id' => 17], 'relay receipt preserves approved destination');
$client = TelegramProxyFixtureHttpClient::$instances[array_key_last(TelegramProxyFixtureHttpClient::$instances)];
$sdk = TelegramProxyFixtureSdk::$instances[array_key_last(TelegramProxyFixtureSdk::$instances)];
checkTelegramFixture($client->options['headers'] === ['X-Service-Token' => 'fixture-relay-token'], 'relay credential is request scoped');
checkTelegramFixture($client->options['timeout'] === 5 && $client->options['connect_timeout'] === 2 && $client->options['allow_redirects'] === false, 'relay request retains timeout and redirect safeguards');
checkTelegramFixture($sdk->baseUri === $relay && $sdk->botToken === 'fixture-bot-token', 'configured HTTPS relay reaches existing SDK adapter');
checkTelegramFixture(TelegramProxyFixtureSdk::$lastPayload === fixturePayload(), 'HTML topic payload reaches the SDK unchanged');

$before = count(TelegramProxyFixtureHttpClient::$instances);
fixtureConfig('http://example.test/relay', null);
rejectTelegramFixture(static fn () => (new TelegramProxyFixtureTransport())->send(fixturePayload()), 'explicit non-HTTPS base URI is rejected without relay credentials');
checkTelegramFixture(count(TelegramProxyFixtureHttpClient::$instances) === $before, 'invalid URI is rejected before HTTP client creation');

fixtureConfig('https://api.telegram.org', null);
$defaultOrigin = (new TelegramProxyFixtureTransport())->send(fixturePayload());
$client = TelegramProxyFixtureHttpClient::$instances[array_key_last(TelegramProxyFixtureHttpClient::$instances)];
$sdk = TelegramProxyFixtureSdk::$instances[array_key_last(TelegramProxyFixtureSdk::$instances)];
checkTelegramFixture($defaultOrigin['message_id'] === 7 && $sdk->baseUri === 'https://api.telegram.org', 'explicit official HTTPS API origin works without relay credential');
checkTelegramFixture($client->options['headers'] === [], 'official API request has no relay header');

$before = count(TelegramProxyFixtureHttpClient::$instances);
fixtureConfig('https://api.telegram.org./bot', 'fixture-relay-token');
rejectTelegramFixture(static fn () => (new TelegramProxyFixtureTransport())->send(fixturePayload()), 'trailing-dot official host cannot receive relay credential');
checkTelegramFixture(count(TelegramProxyFixtureHttpClient::$instances) === $before, 'official-origin guard runs before HTTP client creation');

fixtureConfig(null, null);
$legacy = (new TelegramProxyFixtureTransport())->send(fixturePayload());
$client = TelegramProxyFixtureHttpClient::$instances[array_key_last(TelegramProxyFixtureHttpClient::$instances)];
$sdk = TelegramProxyFixtureSdk::$instances[array_key_last(TelegramProxyFixtureSdk::$instances)];
checkTelegramFixture($legacy['message_id'] === 7 && $sdk->baseUri === null, 'null configuration remains backward compatible');
checkTelegramFixture($client->options['headers'] === [], 'legacy default request has no relay header');

fixtureConfig('https://native-telegram.example/api', 'fixture-native-service-credential');
$GLOBALS['telegram_fixture_config']['app_errors.telegram.base_uri'] = $relay;
$GLOBALS['telegram_fixture_config']['app_errors.telegram.relay_token'] = 'fixture-reporting-relay-token';
$beforeConfig = $GLOBALS['telegram_fixture_config'];
$isolated = (new TelegramProxyFixtureTransport())->send(fixturePayload());
$client = TelegramProxyFixtureHttpClient::$instances[array_key_last(TelegramProxyFixtureHttpClient::$instances)];
$sdk = TelegramProxyFixtureSdk::$instances[array_key_last(TelegramProxyFixtureSdk::$instances)];
checkTelegramFixture($isolated['message_id'] === 7 && $sdk->baseUri === $relay, 'reporting-only endpoint takes precedence over native SDK configuration');
checkTelegramFixture($client->options['headers'] === ['X-Service-Token' => 'fixture-reporting-relay-token'], 'only reporting relay credential reaches reporting transport');
checkTelegramFixture($GLOBALS['telegram_fixture_config'] === $beforeConfig, 'transport leaves application-wide native SDK config unchanged');

fixtureConfig(null, null);
$GLOBALS['telegram_fixture_config']['app_errors.telegram.base_uri'] = $relay;
$GLOBALS['telegram_fixture_config']['app_errors.telegram.relay_token'] = 'fixture-isolated-relay-token';
$isolatedOnly = (new TelegramProxyFixtureTransport())->send(fixturePayload());
$client = TelegramProxyFixtureHttpClient::$instances[array_key_last(TelegramProxyFixtureHttpClient::$instances)];
$sdk = TelegramProxyFixtureSdk::$instances[array_key_last(TelegramProxyFixtureSdk::$instances)];
checkTelegramFixture($isolatedOnly['message_id'] === 7 && $sdk->baseUri === $relay, 'isolated config works without legacy service keys');
checkTelegramFixture($client->options['headers'] === ['X-Service-Token' => 'fixture-isolated-relay-token'], 'isolated relay credential is scoped to its request');

fixtureConfig(null, null);
$GLOBALS['telegram_fixture_config']['app_errors.telegram.base_uri'] = 'https://api.telegram.org';
$GLOBALS['telegram_fixture_config']['app_errors.telegram.relay_token'] = 'fixture-reporting-relay-token';
$before = count(TelegramProxyFixtureHttpClient::$instances);
rejectTelegramFixture(static fn () => (new TelegramProxyFixtureTransport())->send(fixturePayload()), 'reporting relay credential is rejected for the official API origin');
checkTelegramFixture(count(TelegramProxyFixtureHttpClient::$instances) === $before, 'official API credential guard runs before HTTP client creation');

$before = count(TelegramProxyFixtureHttpClient::$instances);
fixtureConfig(null, 'fixture-relay-token');
rejectTelegramFixture(static fn () => (new TelegramProxyFixtureTransport())->send(fixturePayload()), 'relay credential requires explicit HTTPS base URI');
checkTelegramFixture(count(TelegramProxyFixtureHttpClient::$instances) === $before, 'missing relay URI is rejected before HTTP client creation');

fixtureConfig('https://user:pass@example.test/relay', null);
rejectTelegramFixture(static fn () => (new TelegramProxyFixtureTransport())->send(fixturePayload()), 'userinfo in HTTPS URI is rejected');
checkTelegramFixture(count(TelegramProxyFixtureHttpClient::$instances) === $before, 'userinfo URI is rejected before HTTP client creation');

echo 'PASS ' . $GLOBALS['telegram_fixture_checks'] . " Telegram proxy assertions\n";
