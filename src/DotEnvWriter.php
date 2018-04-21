<?php

namespace DotEnvWriter;

class DotEnvWriter
{
    /**
     * The full contents of the .env file during processing
     * @var string
     */
    protected $buffer = '';

    /**
     * Path where the .env file output will be written
     *
     * @var string
     */
    protected $outputPath;

    /**
     * The line endings used in the file, e.g \n or \r\n
     *
     * @var string
     */
    protected $lineEnding = PHP_EOL;

    /**
     * Controls if booleans should be casted when writing the file.
     *
     * @var string
     */
    protected $castBooleans = false;

    /**
     * Create the instance. If a $filePath is given it must be writable, although
     * output can be later redirected to a different path.
     *
     * @param string $filePath
     * @param bool
     */
    public function __construct($filePath = null)
    {
        if (!is_null($filePath)) {
            if (is_file($filePath)) {
                $this->load($filePath);
            }
            $this->setOutputPath($filePath);
        }
    }

    /**
     * Read the contents of a file into the buffer
     *
     * @param string $filePath
     * @return \DotEnvWriter\DotEnvWriter
     * @throws \Exception
     */
    public function load($filePath)
    {
        if (!is_file($filePath) || (!false === ($buffer = file_get_contents($filePath)))) {
            throw new \Exception(sprintf('Unable to read environment file at %s.', $filePath));
        }

        // detect the source line endings
        if (is_null($this->lineEnding)) {
            if (preg_match('/\R/', $buffer, $m)) {
                $this->lineEnding = $m[0];
            } else {
                $this->lineEnding = PHP_EOL;
            }
        }

        // The regex patterns require unix-style line endings
        $this->buffer = trim(preg_replace('/\R/', "\n", $buffer));

        return $this;
    }

    /**
     * Set the line endings that will be used in the output.
     *
     * @param string $lineEnding
     * @return \DotEnvWriter\DotEnvWriter
     */
    public function setLineEnding($lineEnding)
    {
        $this->lineEnding = $lineEnding;

        return $this;
    }

    /**
     * Set the path to write the output (if different from the source file)
     *
     * @param string $filePath
     * @throws \Exception
     * @return \DotEnvWriter\DotEnvWriter
     */
    public function setOutputPath($filePath)
    {
        if (false === $this->ensureFileIsWritable($filePath)) {
            throw new \Exception(sprintf('Unwritable environment file at %s.', $filePath));
        }

        $this->outputPath = $filePath;

        return $this;
    }

    /**
     * Write a single line of text.
     *
     * @param string $text
     * @return \DotEnvWriter\DotEnvWriter
     */
    public function line($text = '')
    {
        $this->buffer .= "{$text}\n";

        return $this;
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
    public function save($filePath = null)
    {
        if (!is_null($filePath)) {
            $this->setOutputPath($filePath);
        }

        if (is_null($this->outputPath)) {
            throw new \Exception('Output file path is not set');
        }

        $output = preg_replace('/\R/', $this->lineEnding, trim($this->buffer));

        if (false === file_put_contents($this->outputPath, $output.$this->lineEnding)) {
            throw new \Exception(sprintf('Failed to write environment file at %s.', $this->outputPath));
        }

        return $this;
    }

    /**
     * Find the existing value of the given environment variable. Returns false
     * if the variable doesn't exist, or an array containing the full line as
     * well as its components broken out.
     *
     * @param string $key
     * @return boolean|array
     */
    public function get($key)
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
            $m['value'] = str_replace('\\\\', '\\', $m['value']);
            $m['value'] = str_replace("\\$quote", $quote, $m['value']);
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
     * Enable / Disable cast booleans.
     *
     * @param bool $shouldCast
     *
     * @return \DotEnvWriter\DotEnvWriter
     */
    public function castBooleans($shouldCast = true)
    {
        $this->castBooleans = $shouldCast;
        
        return $this;
    }

    /**
     * Cast special values to the proper format for storage in the env file
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function castValue($value)
    {
        if ($this->castBooleans) {
            if ($value === true) return "true";
            if ($value === false) return "false";
        }
        
        return $value;
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
        $value = $this->castValue($value);
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
        if (!$forceQuotes && !preg_match('/[#\s"\'\\\\]|\\\\n/', $value)) {
            return $value;
        }
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\"', $value);
        $value = "\"{$value}\"";

        return $value;
    }

    /**
     * Tests the .env file for writability. If the file doesn't exist, check
     * the parent directory for writability so the file can be created.
     *
     * @return bool
     */
    protected function ensureFileIsWritable($filePath)
    {
        if ((is_file($filePath) && !is_writable($filePath)) || (!is_file($filePath) && !is_writable(dirname($filePath)))) {
            return false;
        }
        return true;
    }
}
