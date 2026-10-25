<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
/** Only the scheduled health parent/child adapter opts into this identity. */
final readonly class HealthCheckIdentity
{
    public function __construct(public string $project, public string $server, public string $action = 'health_checks')
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/D',$project) || !preg_match('/^[A-Za-z0-9_.:-]{1,200}$/D',$server)) throw new \InvalidArgumentException('Invalid health scope');
        if (!in_array($action,['health_checks','queue_heartbeat','scheduler_heartbeat'],true)) throw new \InvalidArgumentException('Invalid health action');
    }
    public function metadata(): array { return ['project'=>$this->project,'server'=>$this->server,'action'=>$this->action]; }
}
