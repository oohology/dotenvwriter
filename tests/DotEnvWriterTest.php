<?php

use DotEnvWriter\DotEnvWriter;

class DotEnvWriterTest extends PHPUnit_Framework_TestCase
{
    protected $fixtures;
    protected $testVars = [
        'DOUBLE_QUOTED_VAR' => '',
        'SINGLE_QUOTED_VAR' => '',
        'NO_QUOTES_VAR' => '',
        'EXPORT_VAR' => '',
        'HAS_COMMENT_VAR' => '',
        'HAS_COMMENT_REPLACEMENT_VAR2' => '',
        'IS_A_COMMENT_VAR' => '',
    ];

    protected function setUp()
    {
        $this->fixtures = __DIR__.'/fixtures/';

        $writer = (new DotEnvWriter($this->fixtures.'.env.example'))
            ->setOutputPath($this->fixtures.'.env', true)
            ->save();

        $this->generateTestVars();
    }

    protected function generateTestVars()
    {
        foreach ($this->testVars as $k => $v) {
            $this->testVars[$k] = $this->randomString();
        }
    }

    protected function tearDown()
    {
        $this->cleanup();
    }

    public function testCanReplaceValues()
    {
        $writer = (new DotEnvWriter($this->fixtures.'.env'));
        foreach ($this->testVars as $key => $value) {
            $writer->set($key, $value);
        }
        $writer->save();

        $writer = (new DotEnvWriter($this->fixtures.'.env'));
        foreach ($this->testVars as $key => $value) {
            $parsedLine = $writer->get($key);

            $this->assertEquals($value, $parsedLine['value'], "assertEquals: {$key} == [{$value}]");
        }
    }

    public function testCommentsCanBeOmitted()
    {
        $expectedComment = 'this is a comment test';
        $key = 'HAS_COMMENT_VAR';

        $writer = (new DotEnvWriter($this->fixtures.'.env'))
            ->set($key, 'changed!')
            ->save();

        $writer = (new DotEnvWriter($this->fixtures.'.env'));
        $value = $writer->get($key);
        $this->assertEquals($expectedComment, $value['comment']);
    }

    public function testCommentsCanBeReplaced()
    {
        $newComment = 'this is the new comment';
        $key = 'HAS_COMMENT_REPLACEMENT_VAR2';

        $writer = (new DotEnvWriter($this->fixtures.'.env'))
            ->set($key, 'changed!', $newComment)
            ->save();

        $writer = (new DotEnvWriter($this->fixtures.'.env'));
        $value = $writer->get($key);
        $this->assertEquals($newComment, $value['comment']);
    }

    public function testExportCanBeOmitted()
    {
        $key = 'EXPORT_VAR';

        $writer = (new DotEnvWriter($this->fixtures.'.env'))
            ->set($key, 'changed!')
            ->save();

        $writer = (new DotEnvWriter($this->fixtures.'.env'));
        $value = $writer->get($key);
        $this->assertEquals(true, $value['export']);
    }

    public function testExportCanBeReplaced()
    {
        $key = 'EXPORT_VAR';

        $writer = (new DotEnvWriter($this->fixtures.'.env'))
            ->set($key, 'changed!', null, false)
            ->save();

        $writer = (new DotEnvWriter($this->fixtures.'.env'));
        $value = $writer->get($key);
        $this->assertEquals(false, $value['export']);
    }

    public function testCanAppend()
    {
        $key = 'NONEXISTENT_KEY';
        $value = $this->randomString();

        $writer = (new DotEnvWriter($this->fixtures.'.env'))
            ->set($key, $value, null, false)
            ->save();

        $writer = (new DotEnvWriter($this->fixtures.'.env'));
        $parsedLine = $writer->get($key);
        $this->assertEquals($value, $parsedLine['value']);
    }

    protected function cleanup()
    {
        if (is_file($this->fixtures.'.env')) {
            unlink($this->fixtures.'.env');
        }
    }

    protected function randomString($length = 1000, $startChar = 32, $endChar = 126)
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= chr(mt_rand($startChar, $endChar));
        }
        $result .= '\'';
        return $result;
    }
}