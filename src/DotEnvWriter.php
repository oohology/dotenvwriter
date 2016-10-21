<?php

namespace DotEnvWriter;

class DotEnvWriter
{
    protected $filePath;
    protected $buffer;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;

        $this->ensureFileIsWritable();
        $this->buffer = trim(file_get_contents($this->filePath));
    }

    public function set($key, $value, $comment = '')
    {
        $line = $this->buildLine($key, $value, $comment);

        // replace or append, depending on if the key already exists
        if (false !== ($match = $this->get($key))) {
            $this->buffer = str_replace($match['line'], $line, $this->buffer);
        } else {
            $this->buffer .= "\n{$line}";
        }

        $this->setEnvironment($key, $value);

        return $this;
    }

    public function save()
    {
        if (false === file_put_contents($this->filePath, trim($this->buffer)."\n")) {
            throw new Exception(sprintf('Failed to write environment file at %s.', $this->filePath));
        }

        return $this;
    }

    protected function ensureFileIsWritable()
    {
        if (!is_writable($this->filePath) || !is_file($this->filePath)) {
            throw new Exception(sprintf('Unwritable environment file at %s.', $this->filePath));
        }
    }

    protected function buildLine($key, $value, $comment)
    {
        $escapedValue = str_replace('"', '\"', $value);
        $line = "{$key}=\"{$escapedValue}\"";
        if (strlen($comment)) {
            $line .= " # {$comment}";
        }
        return $line;
    }

    protected function get($key)
    {
        // first, find the quote style
        $pattern = '/^\h*'.preg_quote($key, '/').'\h*=\h*(?P<quote>[\'"])?/m';
        if (!preg_match($pattern, $this->buffer, $m)) {
            return false;
        }

        if (!empty($m['quote'])) {
            // if it has quotes then allow for escaped quotes, whitespace, etc.
            $quote = $m['quote'];
            $pattern = '/^\h*('.preg_quote($key, '/').')\h*=\h*'.$quote.'([^'.$quote.'\\\\]*(?:\\\\.[^'.$quote.'\\\\]*)*)'.$quote.'\h*(?:#\h*(.*))?$/m';
            if (!preg_match($pattern, $this->buffer, $m)) {
                return false;
            }
        } else {
            // if it's not quoted then it should just be one string of basic word characters
            $pattern = '/^\h*('.preg_quote($key, '/').')\h*=\h*(.*?)\h*(?:#\h*(.*))?$/m';
            if (!preg_match($pattern, $this->buffer, $m)) {
                return false;
            }
        }

        return [
            'line' => $m[0],
            'key' => $m[1],
            'value' => $m[2],
            'comment' => isset($m[3]) ? $m[3] : ''
        ];
    }

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
}