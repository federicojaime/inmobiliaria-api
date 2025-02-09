<?php
// services/LogService.php
namespace services;

class LogService
{
    private $log_path = 'logs/';
    private $error_log;
    private $access_log;

    public function __construct()
    {
        if (!file_exists($this->log_path)) {
            mkdir($this->log_path, 0755, true);
        }

        $this->error_log = $this->log_path . 'error.log';
        $this->access_log = $this->log_path . 'access.log';
    }

    public function logError($message, $context = [])
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = json_encode($context);
        $logMessage = "[$date] ERROR: $message - Context: $contextStr" . PHP_EOL;
        error_log($logMessage, 3, $this->error_log);
    }

    public function logAccess($method, $path, $status, $duration)
    {
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] $method $path - Status: $status - Duration: {$duration}ms" . PHP_EOL;
        error_log($logMessage, 3, $this->access_log);
    }
}
