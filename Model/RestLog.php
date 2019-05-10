<?php
class RestLog extends RestAppModel
{

    public $restLogSettings = [];
    public $Encoder = null;
    public $logpaths = [];
    public $filedata = [];

    /**
     * Some log fields might be destined for disk instead of db
     *
     * @param <type> $options
     * @return <type>
     */
    public function beforeSave($options = [])
    {
        $fields = (array)@$this->restLogSettings['fields'];
        $this->logpaths = [];
        $this->filedata = [];
        foreach ($fields as $field => $log) {
            if (false !== strpos($log, '.') || false !== strpos($log, '/') || false !== strpos($log, '{')) {
                $this->logpaths[$field] = $log;
            }
        }

        foreach ($this->data[__CLASS__] as $field => $val) {
            if (!is_scalar($this->data[__CLASS__][$field])) {
                $this->data[__CLASS__][$field] = $this->Encoder->encode($this->data[__CLASS__][$field], !!@$this->restLogSettings['pretty']);
            }
            if (is_null($this->data[__CLASS__][$field])) {
                $this->data[__CLASS__][$field] = '';
            }

            if (isset($this->logpaths[$field])) {
                $this->filedata[$field] = $this->data[__CLASS__][$field];
                $this->data[__CLASS__][$field] = '# on disk at: ' . $this->logpaths[$field];
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

        $vars = @$this->restLogSettings['vars'] ? @$this->restLogSettings['vars'] : [];

        foreach ($this->filedata as $field => $val) {
            $vars['{' . $field . '}'] = $val;
        }
        foreach ($this->data[__CLASS__] as $field => $val) {
            $vars['{' . $field . '}'] = $val;
        }
        foreach (['Y', 'm', 'd', 'H', 'i', 's', 'U'] as $dp) {
            $vars['{date_' . $dp . '}'] = date($dp);
        }

        $vars['{LOGS}'] = LOGS;
        $vars['{id}'] = $this->id;
        $vars['{controller}'] = Inflector::tableize(@$this->restLogSettings['controller']);

        foreach ($this->filedata as $field => $val) {
            $vars['{field}'] = $field;
            $logfilepath     = $this->logpaths[$field];

            $logfilepath = str_replace(array_keys($vars), $vars, $logfilepath);
            $dir = dirname($logfilepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($logfilepath, $val, FILE_APPEND);
        }

        return parent::beforeSave($created, $options);
    }
}
