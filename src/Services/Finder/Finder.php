<?php

namespace Leolnid\Common\Services\Finder;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use Leolnid\Common\Services\Credentials;
use Throwable;

class Finder
{
    private AmoCRMApiClient $service;

    /**
     * @throws Throwable
     */
    public function __construct(AmoCRMApiClient $service = null)
    {
        $this->service = $service ?? Credentials::getApiClient();
    }


    /**
     * @throws Throwable
     */
    public static function make(AmoCRMApiClient $service = null): static
    {
        return new static($service);
    }

    /**
     * @throws AmoCRMApiException
     */
    public function companies(): EntityFinder
    {
        return new EntityFinder($this->service->companies());
    }

    /**
     * @throws AmoCRMApiException
     */
    public function contacts(): EntityFinder
    {
        return new EntityFinder($this->service->contacts());
    }
}
