<?php

class Logger {
    private $logFile;
    private $isLoggingEnabled;
    private $logLevel;

    const LOG_LEVEL_DEBUG = 'DEBUG'; // Используется для подробной информации о процессе выполнения программы, полезной для отладки и анализа. 
    const LOG_LEVEL_INFO = 'INFO';   // Используется для записи общей информации о нормальной работе приложения, например, успешных операций и ключевых этапов выполнения.
    const LOG_LEVEL_ERROR = 'ERROR'; // Используется для записи ошибок и проблем, которые требуют внимания и могут нарушать работу приложения.
    const LOG_LEVEL_NONE = 'NONE';   // Логирование отключено; никакие сообщения не записываются в лог-файлы.


    /**
     * Summary of __construct
     * @param mixed $logFile
     * @param mixed $isLoggingEnabled
     * @param mixed $logLevel
     */
    public function __construct($logFile = 'log.txt', $isLoggingEnabled = true, $logLevel = self::LOG_LEVEL_INFO) {
        $this->logFile = $logFile;
        $this->isLoggingEnabled = $isLoggingEnabled;
        $this->logLevel = $logLevel;
    }

    /**
     * Summary of setLoggingEnabled
     * @param mixed $enabled
     * @return void
     */
    public function setLoggingEnabled($enabled) {
        $this->isLoggingEnabled = $enabled;
    }

    /**
     * Summary of setLogLevel
     * @param mixed $level
     * @return void
     */
    public function setLogLevel($level) {
        $this->logLevel = $level;
    }

    /**
     * Summary of log
     * @param mixed $message
     * @param mixed $level
     * @return void
     */
    private function log($message, $level) {
        if (!$this->isLoggingEnabled) {
            return;
        }
        
        if ($this->shouldLogLevel($level)) {
            file_put_contents($this->logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Summary of shouldLogLevel
     * @param mixed $level
     * @return bool
     */
    private function shouldLogLevel($level) {
        $levels = [
            self::LOG_LEVEL_DEBUG => 1,
            self::LOG_LEVEL_INFO => 2,
            self::LOG_LEVEL_ERROR => 3,
            self::LOG_LEVEL_NONE => 4
        ];
        return $levels[$level] >= $levels[$this->logLevel];
    }

    /**
     * Summary of logDebug
     * @param mixed $message
     * @return void
     */
    public function logDebug($message) {
        $this->log("[DEBUG] " . $message, self::LOG_LEVEL_DEBUG);
    }

    /**
     * Summary of logInfo
     * @param mixed $message
     * @return void
     */
    public function logInfo($message) {
        $this->log("[INFO] " . $message, self::LOG_LEVEL_INFO);
    }

    /**
     * Summary of logError
     * @param mixed $message
     * @return void
     */
    public function logError($message) {
        $this->log("[ERROR] " . $message, self::LOG_LEVEL_ERROR);
    }
}
