<?php

namespace Mariuzzo\LaravelJsLocalization;

use Config;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Filesystem\Filesystem as File;
use Mariuzzo\LaravelJsLocalization\Commands\LangJsCommand;
use Mariuzzo\LaravelJsLocalization\Generators\LangJsGenerator;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * The LangJsCommandTest class.
 *
 * @author Rubens Mariuzzo <rubens@mariuzzo.com>
 */
class LangJsCommandTest extends TestCase
{
    /**
     * The base path of tests.
     *
     * @var string
     */
    private $testPath;

    /**
     * The root path of the project.
     *
     * @var string
     */
    private $rootPath;

    /**
     * The file path of the expected output.
     *
     * @var string
     */
    private $outputFilePath;

    /**
     * The base path of language files.
     *
     * @var string
     */
    private $langPath;

    /**
     * LangJsCommandTest constructor.
     */
    public function __construct()
    {
        $this->testPath = __DIR__.'/..';
        $this->rootPath = __DIR__.'/../..';
        $this->outputFilePath = "$this->testPath/output/lang.js";
        $this->langPath = "$this->testPath/fixtures/lang";
    }

    /**
     * Assert the command can be run.
     */
    public function testShouldCommandRun()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command, ['target' => $this->outputFilePath]);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);

        $template = "$this->rootPath/src/Mariuzzo/LaravelJsLocalization/Generators/Templates/langjs_with_messages.js";
        $this->assertFileExists($template);
        $this->assertFileNotEquals($template, $this->outputFilePath);

        $this->cleanupOutputDirectory();
    }

    /**
     * Assert template exist with handlebars.
     */
    public function testShouldTemplateHasHandlebars()
    {
        $template = "$this->rootPath/src/Mariuzzo/LaravelJsLocalization/Generators/Templates/langjs_with_messages.js";
        $this->assertFileExists($template);

        $contents = file_get_contents($template);
        $this->assertNotEmpty($contents);
        $this->assertHasHandlebars('messages', $contents);
        $this->assertHasHandlebars('langjs', $contents);
    }

    /**
     * Assert the command upon execution replaces handlebars with generated contents.
     */
    public function testShouldOutputHasNotHandlebars()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command, ['target' => $this->outputFilePath]);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);

        $contents = file_get_contents($this->outputFilePath);
        $this->assertNotEmpty($contents);
        $this->assertHasNotHandlebars('messages', $contents);
        $this->assertHasNotHandlebars('langjs', $contents);

        $this->cleanupOutputDirectory();
    }

    /**
     * Assert messages key ares included in the generated file.
     */
    public function testAllFilesShouldBeConverted()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command, ['target' => $this->outputFilePath]);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);

        $contents = file_get_contents($this->outputFilePath);
        $this->assertContains('gm8ft2hrrlq1u6m54we9udi', $contents);

        $this->assertNotContains('vendor.nonameinc.en.messages', $contents);
        $this->assertNotContains('vendor.nonameinc.es.messages', $contents);
        $this->assertNotContains('vendor.nonameinc.ht.messages', $contents);

        $this->assertContains('en.nonameinc::messages', $contents);
        $this->assertContains('es.nonameinc::messages', $contents);
        $this->assertContains('ht.nonameinc::messages', $contents);


        $this->cleanupOutputDirectory();

    }

    /**
     * Assert specified message source should be included in the generated file.
     */
    public function testFilesSelectedInConfigShouldBeConverted()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath, ['messages']);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command, ['target' => $this->outputFilePath]);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);

        $contents = file_get_contents($this->outputFilePath);
        $this->assertContains('en.messages', $contents);
        $this->assertNotContains('en.validation', $contents);

        $this->cleanupOutputDirectory();
    }

    /**
     * Assert specified message shource can be in a nested directory.
     */
    public function testShouldIncludeNestedDirectoryFile()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath, ['forum/thread']);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command, ['target' => $this->outputFilePath]);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);

        $contents = file_get_contents($this->outputFilePath);
        $this->assertContains('en.forum.thread', $contents);

        $this->cleanupOutputDirectory();
    }

    /**
     * Assert that a custom target can be specified.
     */
    public function testShouldUseDefaultOutputPathFromConfig()
    {
        $customOutputFilePath = "{$this->testPath}/output/lang-with-custom-path.js";
        Config::set('localization-js.path', $customOutputFilePath);

        $generator = new LangJsGenerator(new File(), $this->langPath);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($customOutputFilePath);

        $template = "$this->rootPath/src/Mariuzzo/LaravelJsLocalization/Generators/Templates/langjs_with_messages.js";
        $this->assertFileExists($template);
        $this->assertFileNotEquals($template, $customOutputFilePath);

        $this->cleanupOutputDirectory();
    }

    /**
     * Assert that target specified in command line has more importance than configuration file.
     */
    public function testShouldIgnoreDefaultOutputPathFromConfigIfTargetArgumentExist()
    {
        $customOutputFilePath = "{$this->testPath}/output/lang-with-custom-path.js";
        Config::set('localization-js.path', $customOutputFilePath);

        $generator = new LangJsGenerator(new File(), $this->langPath);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command, ['target' => $this->outputFilePath]);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);
        $this->assertFileNotExists($customOutputFilePath);

        $template = "$this->rootPath/src/Mariuzzo/LaravelJsLocalization/Generators/Templates/langjs_with_messages.js";
        $this->assertFileExists($template);
        $this->assertFileNotEquals($template, $this->outputFilePath);

        $this->cleanupOutputDirectory();
    }

    /**
     * Assert exclusion of lang.js library.
     */
    public function testShouldOnlyMessageExported()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath);
        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command, ['target' => $this->outputFilePath,'--no-lib' => true]);
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);

        $contents = file_get_contents($this->outputFilePath);
        $this->assertNotEmpty($contents);
        $this->assertHasNotHandlebars('messages', $contents);
        $this->cleanupOutputDirectory();
    }

    /**
     *
     */
    public function testChangeDefaultLangSourceFolder()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command,[
                'target' => $this->outputFilePath,
                '-s' => "$this->testPath/fixtures/theme/lang",
            ]
        );
        $this->assertRunsWithSuccess($code);
        $this->assertFileExists($this->outputFilePath);

        $template = "$this->rootPath/src/Mariuzzo/LaravelJsLocalization/Generators/Templates/langjs_with_messages.js";
        $this->assertFileExists($template);
        $this->assertFileNotEquals($template, $this->outputFilePath);

        $contents = file_get_contents($this->outputFilePath);
        $this->assertContains('en.page', $contents);

        $this->cleanupOutputDirectory();
    }

    /**
     * @expectedException Exception
     */
    public function testChangeDefaultLangSourceFolderForOneThatDosentExist()
    {
        $generator = new LangJsGenerator(new File(), $this->langPath);

        $command = new LangJsCommand($generator);
        $command->setLaravel($this->app);

        $code = $this->runCommand($command,[
                'target' => $this->outputFilePath,
                '-s' => $this->langPath.'/non-exist',
            ]
        );
    }

    /**
     * Run a command with optional input.
     *
     * @param \Illuminate\Console\Command $command
     * @param array                       $input
     *
     * @return int
     */
    protected function runCommand($command, $input = [])
    {
        return $command->run(new ArrayInput($input), new NullOutput());
    }

    /**
     * Assert output code is successful.
     *
     * @param int  $code
     * @param null $message
     */
    protected function assertRunsWithSuccess($code, $message = null)
    {
        $this->assertEquals(0, $code, $message);
    }

    /**
     * Assert a handle exists in a text contents.
     *
     * @param string $handle   The handle.
     * @param string $contents The text contents.
     */
    protected function assertHasHandlebars($handle, $contents)
    {
        $this->assertEquals(1, preg_match('/\'\{(\s)'.preg_quote($handle).'(\s)\}\'/', $contents));
    }

    /**
     * Assert a handle doesn't exists in a text contents.
     *
     * @param string $handle   The handle.
     * @param string $contents The text contents.
     */
    protected function assertHasNotHandlebars($handle, $contents)
    {
        $this->assertEquals(0, preg_match('/\'\{(\s)'.preg_quote($handle).'(\s)\}\'/', $contents));
    }

    /**
     * Cleanup output directory.
     */
    protected function cleanupOutputDirectory()
    {
        $files = FileFacade::files("{$this->testPath}/output");
        foreach ($files as $file) {
            FileFacade::delete($file);
        }
    }
}
