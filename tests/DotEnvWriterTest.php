<?php

use DotEnvWriter\DotEnvWriter;

class DotEnvWriterTest extends PHPUnit_Framework_TestCase
{
    protected $writer;
    protected $fixtures;
    protected $inputFileHash = '892b1da67ef6ebd5f8e61430eaf06a4a';
    protected $testVars = [
        'DOUBLE_QUOTED_VAR' => '',
        'SINGLE_QUOTED_VAR' => '',
        'NO_QUOTES_VAR' => '',
        'EXPORT_VAR' => '',
        'HAS_COMMENT_VAR' => '',
        'HAS_COMMENT_REPLACEMENT_VAR2' => '',
        'IS_A_COMMENT_VAR' => '',
        'IS_A_BOOLEAN_VAR' => ''
    ];

    protected function setUp()
    {
        $this->fixtures = [
            'inputFile' => __DIR__.'/fixtures/.env.example',
            'outputFile' => __DIR__.'/fixtures/.env',
        ];

        $this->writer = (new DotEnvWriter($this->fixtures['inputFile']))
            ->setOutputPath($this->fixtures['outputFile']);

        $this->generateTestVars();
    }

    protected function tearDown()
    {
        $this->cleanup();
    }

    protected function replaceWithRandomValues()
    {
        foreach ($this->testVars as $key => $value) {
            $this->writer->set($key, $value);
        }
    }

    protected function cleanup()
    {
        if (is_file($this->fixtures['outputFile'])) {
            unlink($this->fixtures['outputFile']);
        }
    }

    protected function randomString($length = 1000, $startChar = 32, $endChar = 126)
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= chr(mt_rand($startChar, $endChar));
        }
        return $result;
    }

    protected function generateTestVars()
    {
        foreach ($this->testVars as $k => $v) {
            $this->testVars[$k] = $this->randomString();
        }
    }

    protected function checkTestVarsInOutputFile()
    {
        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        foreach ($this->testVars as $key => $value) {
            $parsedLine = $writer->get($key);

            $this->assertEquals($value, $parsedLine['value'], "assertEquals: {$key} == [{$value}]");
        }
    }

    protected function checkInputFileHash()
    {
        $inputFileContents = preg_replace('/\R/', "\n", file_get_contents($this->fixtures['inputFile']));
        $this->assertEquals($this->inputFileHash, md5($inputFileContents));
    }

    public function testSaveToPath()
    {
        $this->writer = (new DotEnvWriter($this->fixtures['inputFile']));
        $this->replaceWithRandomValues();

        $this->writer->save($this->fixtures['outputFile']);

        $this->checkTestVarsInOutputFile();
        $this->checkInputFileHash();
    }

    public function testLoadThenSaveToPath()
    {
        $this->writer = (new DotEnvWriter())->load($this->fixtures['inputFile']);
        $this->replaceWithRandomValues();
        $this->writer->save($this->fixtures['outputFile']);

        $this->checkTestVarsInOutputFile();
        $this->checkInputFileHash();
    }

    public function testLoadKeepsOriginalOutputPath()
    {
        $this->writer = (new DotEnvWriter($this->fixtures['outputFile']))->load($this->fixtures['inputFile']);
        $this->replaceWithRandomValues();
        $this->writer->save();

        $this->checkTestVarsInOutputFile();
        $this->checkInputFileHash();
    }

    public function testCanReplaceValues()
    {
        $this->replaceWithRandomValues();
        $this->writer->save();

        $this->checkTestVarsInOutputFile();
        $this->checkInputFileHash();
    }

    public function testCommentsCanBeOmitted()
    {
        $expectedComment = 'this is a comment test';
        $key = 'HAS_COMMENT_VAR';

        $this->writer->set($key, 'changed!')->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $value = $writer->get($key);
        $this->assertEquals($expectedComment, $value['comment']);
    }

    public function testCommentsCanBeReplaced()
    {
        $newComment = 'this is the new comment';
        $key = 'HAS_COMMENT_REPLACEMENT_VAR2';

        $this->writer->set($key, 'changed!', $newComment)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $value = $writer->get($key);
        $this->assertEquals($newComment, $value['comment']);
    }

    public function testExportCanBeOmitted()
    {
        $key = 'EXPORT_VAR';

        $this->writer->set($key, 'changed!')->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $value = $writer->get($key);
        $this->assertEquals(true, $value['export']);
    }

    public function testExportCanBeReplaced()
    {
        $key = 'EXPORT_VAR';

        $this->writer->set($key, 'changed!', null, false)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $value = $writer->get($key);
        $this->assertEquals(false, $value['export']);
    }

    public function testCanAppend()
    {
        $key = 'NONEXISTENT_KEY';
        $value = $this->randomString();

        $this->writer->set($key, $value, null, false)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $parsedLine = $writer->get($key);
        $this->assertEquals($value, $parsedLine['value']);
    }

    public function testCanStoreBooleans()
    {
        $key = 'IS_A_BOOLEAN_VAR';

        $this->writer->castBooleans();

        $this->writer->set($key, false)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $parsedLine = $writer->get($key);
        $this->assertEquals("false", $parsedLine['value']);

        $this->writer->set($key, true)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $parsedLine = $writer->get($key);
        $this->assertEquals("true", $parsedLine['value']);
    }

    public function testItCastsToBooleansOnlyIfEnabled()
    {
        $key = 'IS_A_BOOLEAN_VAR';

        // By default casting booleans is disabled
        $this->writer->set($key, true)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $parsedLine = $writer->get($key);
        $this->assertEquals("1", $parsedLine['value']);
        
        // If enabled, values will be casted
        $this->writer->castBooleans();

        $this->writer->set($key, true)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $parsedLine = $writer->get($key);
        $this->assertEquals("true", $parsedLine['value']);
        
        // And then we can disable it again
        $this->writer->castBooleans(false);

        $this->writer->set($key, false)->save();

        $writer = (new DotEnvWriter($this->fixtures['outputFile']));
        $parsedLine = $writer->get($key);
        $this->assertEquals("", $parsedLine['value']);
    }
}
