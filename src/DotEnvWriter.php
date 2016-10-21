<?php

namespace DotEnvWriter;

class DotEnvWriter
{
    /**
     * The full contents of the .env file during processing
     * @var string
     */
    protected $buffer;

    /**
     * Should a .env file be created if it doesn't exist?
     *
     * @var bool
     */
    protected $create;

    /**
     * Path to the .env file
     *
     * @var type
     */
    protected $filePath;

    /**
     * Create the instance
     *
     * @param string
     * @param bool
     */
    public function __construct($filePath, $create = false)
    {
        $this->filePath = $filePath;
        $this->create = $create;

        $this->ensureFileIsWritable();
        $this->buffer = trim(file_get_contents($this->filePath));
    }

    /**
     * Add or modify an environment variable. If the variable already exists and
     * $comment or $export are omitted, the existing value will be maintained.
     *
     * @param string $key
     * @param string $value
     * @param string $comment optional
     * @param boolean $export optional
     * @return \DotEnvWriter\DotEnvWriter
     */
    public function set($key, $value, $comment = null, $export = null)
    {
        $match = $this->get($key);

        // use values from $match for omitted parameters
        if (false !== $match) {
            if (is_null($comment)) {
                $comment = $match['comment'];
            }
            if (is_null($export)) {
                $export = $match['export'];
            }
        }

        $line = $this->buildLine($key, $value, $comment, $export);

        // replace or append, depending on if the key already exists
        if (false !== $match) {
            $this->buffer = str_replace($match['line'], $line, $this->buffer);
        } else {
            $this->buffer .= "\n{$line}";
        }

        $this->setEnvironment($key, $value);

        return $this;
    }

    /**
     * Write the changes to the .env file
     *
     * @return \DotEnvWriter\DotEnvWriter
     * @throws \Exception
     */
    public function save()
    {
        if (false === file_put_contents($this->filePath, trim($this->buffer)."\n")) {
            throw new \Exception(sprintf('Failed to write environment file at %s.', $this->filePath));
        }

        return $this;
    }

    /**
     * Find the existing value of the given environment variable. Returns false
     * if the variable doesn't exist, or an array containing the full line as
     * well as its components broken out.
     *
     * @param type $key
     * @return boolean|array
     */
    protected function get($key)
    {
        // first, find the quote style
        $pattern = '/^(export\h)?\h*'.preg_quote($key, '/').'\h*=\h*(?P<quote>[\'"])?/m';
        if (!preg_match($pattern, $this->buffer, $m)) {
            return false;
        }

        if (!empty($m['quote'])) {
            // if it has quotes then allow for escaped quotes, whitespace, etc.
            $quote = $m['quote'];
            $pattern = '/^(?P<export>export\h)?\h*(?P<key>'.preg_quote($key, '/').')\h*=\h*'.$quote.'(?P<value>[^'.$quote.'\\\\]*(?:\\\\.[^'.$quote.'\\\\]*)*)'.$quote.'\h*(?:#\h*(?P<comment>.*))?$/m';
            if (!preg_match($pattern, $this->buffer, $m)) {
                return false;
            }
        } else {
            // if it's not quoted then it should just be one string of basic word characters
            $pattern = '/^(?P<export>export\h)?\h*(?P<key>'.preg_quote($key, '/').')\h*=\h*(?P<value>.*?)\h*(?:#\h*(?P<comment>.*))?$/m';
            if (!preg_match($pattern, $this->buffer, $m)) {
                return false;
            }
        }

        return [
            'line' => $m[0],
            'export' => (strlen($m['export']) > 0),
            'key' => $m['key'],
            'value' => $m['value'],
            'comment' => isset($m['comment']) ? $m['comment'] : ''
        ];
    }

    /**
     * Update a variable in the current request's environment.
     *
     * @param string $name
     * @param string $value
     */
    protected function setEnvironment($name, $value)
    {
        // This is required to overwrite variables that come from apache
        // configs e.g. <VirtualHost>SetEnv FOO bar </VirtualHost>
        if (function_exists('apache_getenv') && function_exists('apache_setenv') && apache_getenv($name)) {
            apache_setenv($name, $value);
        }

        // these work in php scope but don't affect getenv() or other
        // apache / process level scopes
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;

        // this controls what comes out of getenv and also what passes it to
        // child processes e.g. exec() calls
        if (function_exists('putenv')) {
            putenv("$name=$value");
        }
    }

    /**
     * Build an environment file line from the individual components
     *
     * @param string $key
     * @param string $value
     * @param string $comment optional
     * @param bool $export optional
     * @return string
     */
    protected function buildLine($key, $value, $comment = '', $export = false)
    {
        $forceQuotes = (strlen($comment) > 0);
        $escapedValue = $this->escapeValue($value, $forceQuotes);
        $export = $export ? 'export ' : '';
        $comment = strlen($comment) ? " # {$comment}" : '';

        $line = "{$export}{$key}={$escapedValue}{$comment}";

        return $line;
    }

    /**
     * Prepare the value for writing to the file.
     *
     * Values need quoted/escaped if they contain any of
     * the following: whitespace, '\n', '#'
     *
     * @param string $value
     * @param bool $forceQuotes
     * @return string
     */
    protected function escapeValue($value, $forceQuotes = false)
    {
        $value = trim($value);

        if (!$forceQuotes && !preg_match('/[#\s"]|\\\\n/', $value)) {
            return $value;
        }
        $escapedValue = str_replace('"', '\"', $value);
        $escapedValue = "\"{$escapedValue}\"";

        return $escapedValue;
    }

    /**
     * Tests the .env file for writability. Creates the file if it doesn's
     * exist and the create flag is set.
     *
     * @throws \Exception
     */
    protected function ensureFileIsWritable()
    {
        if (!is_file($this->filePath) && $this->create) {
            touch($this->filePath);
        }

        if (!is_writable($this->filePath) || !is_file($this->filePath)) {
            throw new \Exception(sprintf('Unwritable environment file at %s.', $this->filePath));
        }
    }
}