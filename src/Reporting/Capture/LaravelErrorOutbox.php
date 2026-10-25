<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;
use Leolnid\Common\Reporting\StoragePolicy;
use Leolnid\Common\Reporting\Delivery\{FilesystemOutbox,NotificationTransport};
class LaravelErrorOutbox extends FilesystemOutbox
{
    protected const FILE_MODE=0600;
    protected const DIRECTORY_MODE=0700;
    public function __construct(NotificationTransport $transport)
    {
        parent::__construct($transport,storage_path('app/error-notifications'),storage_path('app/daily-reports'),static fn():bool=>(bool)config('app_errors.enabled'),static fn():bool=>is_file(storage_path('app/error-notifications/.delivery-paused')),(int)config('app_errors.project_index',0),new StoragePolicy(static::FILE_MODE,static::DIRECTORY_MODE));
    }
}
