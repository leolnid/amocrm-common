<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Delivery;
interface NotificationTransport { public function send(array $payload): array; }
