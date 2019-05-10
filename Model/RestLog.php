<?php
/**
 * RestLog
 */
class RestLog extends RestAppModel
{
    public $restLogSettings = [];

    public $Encoder = null;

    public $logPaths = [];

    public $fileData = [];

    /**
     * Some log fields might be destined for disk instead of db
     *
     * @param <type> $options
     * @return <type>
     */
    public function beforeSave($options = [])
    {
        // Set defaults to settings.
        $this->restLogSettings += [
            'fields' => [],
            'pretty' => true,
        ];

        $fields = (array)$this->restLogSettings['fields'];

        $this->logPaths = [];
        $this->fileData = [];

        foreach ($fields as $field => $log) {
            if (false !== strpos($log, '.')
                || false !== strpos($log, '/')
                || false !== strpos($log, '{')
            ) {
                $this->logPaths[$field] = $log;
            }
        }

        foreach ($this->data[__CLASS__] as $field => $val) {
            if (!is_scalar($this->data[__CLASS__][$field])) {
                $this->data[__CLASS__][$field] = $this->Encoder->encode(
                    $this->data[__CLASS__][$field],
                    (bool)$this->restLogSettings['pretty']
                );
            }

            if (is_null($this->data[__CLASS__][$field])) {
                $this->data[__CLASS__][$field] = '';
            }

            if (isset($this->logPaths[$field])) {
                $this->fileData[$field] = $this->data[__CLASS__][$field];
                $this->data[__CLASS__][$field] = '# on disk at: ' . $this->logPaths[$field];
            }
        }

        return parent::beforeSave($options);
    }

    /**
     * Log fields to disk if necessary. Important to do after save so
     * we can also use the ->id in the filename.
     *
     * @param <type> $created
     * @return <type>
     */
    public function afterSave($created, $options = [])
    {
        if (!$created) {
            return parent::beforeSave($created);
        }

        // Set defaults to settings.
        $this->restLogSettings += [
            'vars' => [],
            'controller' => null,
        ];

        $vars = (array)$this->restLogSettings['vars'];

        foreach ($this->fileData as $field => $val) {
            $vars['{' . $field . '}'] = $val;
        }

        foreach ($this->data[$this->alias] as $field => $val) {
            $vars['{' . $field . '}'] = $val;
        }

        foreach (['Y', 'm', 'd', 'H', 'i', 's', 'U'] as $dp) {
            $vars['{date_' . $dp . '}'] = date($dp);
        }

        $vars['{LOGS}']       = LOGS;
        $vars['{id}']         = $this->id;
        $vars['{controller}'] = Inflector::tableize($this->restLogSettings['controller']);

        foreach ($this->fileData as $field => $val) {
            $vars['{field}'] = $field;
            $logFilePath    = $this->logPaths[$field];

            $logFilePath = str_replace(array_keys($vars), $vars, $logFilePath);
            $dir = dirname($logFilePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($logFilePath, $val, FILE_APPEND);
        }

        return parent::beforeSave($created, $options);
    }
}
