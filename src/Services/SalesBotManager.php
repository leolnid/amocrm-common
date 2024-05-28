<?php

namespace Leolnid\Common\Services;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Leolnid\Common\Exceptions\SalesbotRunException;
use Throwable;

class SalesBotManager
{
    private AmoCRMApiClient $client;

    /**
     * @throws Throwable
     */
    public function __construct(?AmoCRMApiClient $client = null)
    {
        $this->client = $client ?? Credentials::getApiClient();
    }

    public function list(): Collection
    {

        try {
            $result = $this->client->getRequest()->get('private/ajax/v2/json/helpbot/',
                ['count' => 100],
                ['X-Requested-With' => 'XMLHttpRequest']);
        } catch (Throwable) {
            return collect();
        }

        return collect(Arr::get($result, '_embedded.salesbots'))
            ->map(fn ($arr) => [
                'id' => Arr::get($arr, 'id'),
                'name' => Arr::get($arr, 'name'),
            ])
            ->values();
    }

    /**
     * @throws SalesbotRunException
     */
    public function run(int $botId, int $leadId): void
    {
        try {
            $this->client->getRequest()->post('api/v2/salesbot/run', [
                [
                    'bot_id' => $botId,
                    'entity_id' => $leadId,
                    'entity_type' => 2,
                ],
            ]);
        } catch (AmoCRMApiException $e) {
            throw new SalesbotRunException($botId, $leadId, $e);
        }
    }
}
