<?php


namespace Codeception\Module;


use Codeception\Exception\ModuleConfigException;
use tad\WPBrowser\Filesystem\Utils;

/**
 * Class WPFilesystem
 *
 * WordPress specific filesystem operations.
 *
 * @package Codeception\Module
 */
class WPFilesystem extends Filesystem {

    /**
     * @var array
     */
    protected $requiredFields = ['wpRootFolder'];

    /**
     * @var array
     */
    protected $config = [
        'themes'     => '/wp-content/themes',
        'plugins'    => '/wp-content/plugins',
        'mu-plugins' => '/wp-content/mu-plugins',
        'uploads'    => '/wp-content/uploads',
    ];

    public function _initialize() {
        $this->ensureWpRootFolder();
        $this->ensureAndSetOptionalPaths();
    }

    protected function ensureWpRootFolder() {
        $wpRoot = $this->config['wpRootFolder'];

        if ( ! is_dir($wpRoot)) {
            $wpRoot = codecept_root_dir(Utils::unleadslashit($wpRoot));
        }

        $message = "[{$wpRoot}] is not a valid WordPress root folder.\n\nThe WordPress root folder is the one that contains the 'wp-load.php' file.";

        if ( ! (is_dir($wpRoot) && is_readable($wpRoot) && is_writable($wpRoot))) {
            throw new ModuleConfigException(__CLASS__, $message);
        }

        if ( ! file_exists($wpRoot . '/wp-load.php')) {
            throw new ModuleConfigException(__CLASS__, $message);
        }

        $this->config['wpRootFolder'] = Utils::untrailslashit($wpRoot) . DIRECTORY_SEPARATOR;
    }

    protected function ensureAndSetOptionalPaths() {
        $optionalPaths = [
            'themes'     => ['mustExist' => true, 'default' => '/wp-content/themes'],
            'plugins'    => ['mustExist' => true, 'default' => '/wp-content/plugins'],
            'mu-plugins' => ['mustExist' => false, 'default' => '/wp-content/mu-plugins'],
            'uploads'    => ['mustExist' => true, 'default' => '/wp-content/uploads'],
        ];
        $wpRoot = $this->config['wpRootFolder'];
        foreach ($optionalPaths as $configKey => $info) {
            if (empty($this->config[$configKey])) {
                $path = $info['default'];
            } else {
                $path = $this->config[$configKey];
            }
            if ( ! is_dir($path)) {
                $path = Utils::unleadslashit($path);
                $absolutePath = $wpRoot . $path;
            } else {
                $absolutePath = $path;
            }
            $mustExistAndIsNotDir = $info['mustExist'] && ! is_dir($absolutePath);
            $canNotExistButIsDefined = ! empty($this->config[$configKey]) && ! is_dir($absolutePath);
            if ($mustExistAndIsNotDir || $canNotExistButIsDefined) {
                throw new ModuleConfigException(__CLASS__, "The {$configKey} config path [{$path}] does not exist.");
            }
            $this->config[$configKey] = Utils::untrailslashit($absolutePath) . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * Enters the uploads folder in the local filesystem.
     *
     * @param string $path
     */
    public function amInUploadsPath($path = null) {
        if (empty($path)) {
            $path = $this->config['uploads'];
        } else {
            $path = (string)$path;
            if (is_dir($this->config['uploads'] . DIRECTORY_SEPARATOR . Utils::unleadslashit($path))) {
                $path = $this->config['uploads'] . DIRECTORY_SEPARATOR . Utils::unleadslashit($path);
            } else {
                // time based?
                $timestamp = is_numeric($path) ? $path : strtotime($path);
                $path = implode(DIRECTORY_SEPARATOR, [$this->config['uploads'], date('Y', $timestamp), date('m', $timestamp)]);
            }
        }
        $this->amInPath($path);
    }

    /**
     * Checks if file exists in the uploads folder.
     *
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * Opens a file when it's exists
     *
     * ``` php
     * <?php
     * $I->seeUploadedFileFound('some-file.txt');
     * $I->seeUploadedFileFound('some-file.txt','today');
     * ?>
     * ```
     *
     * @param string $filename
     * @param string $date
     */
    public function seeUploadedFileFound($filename, $date = null) {
        $path = $this->getUploadsPath($filename, $date);
        \PHPUnit_Framework_Assert::assertFileExists($path);
    }

    protected function getUploadsPath($file, $date = null) {
        $dateFrag = '';
        if (null !== $date) {
            $timestamp = is_numeric($date) ? $date : strtotime($date);
            $Y = date('Y', $timestamp);
            $m = date('m', $timestamp);
            $dateFrag = $Y . DIRECTORY_SEPARATOR . $m;
        }
        $path = implode(DIRECTORY_SEPARATOR, [$this->config['uploads'], $dateFrag, Utils::unleadslashit($file)]);

        return $path;
    }

    /**
     * Checks thata a file does not exist in the uploads folder.
     *
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->dontSeeUploadedFileFound('some-file.txt');
     * $I->dontSeeUploadedFileFound('some-file.txt','today');
     * ?>
     * ```
     *
     * @param string $filename
     * @param string $date
     */
    public function dontSeeUploadedFileFound($file, $date = null) {
        $path = $this->getUploadsPath($file, $date);
        \PHPUnit_Framework_Assert::assertFileNotExists($path);
    }


    /**
     * Checks that a file in the uploads folder contains a string.
     *
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->seeInUploadedFile('some-file.txt', 'foo');
     * $I->seeInUploadedFile('some-file.txt','foo', 'today');
     * ?>
     * ```
     *
     * @param string $filename
     * @param string $contents
     * @param string $date
     */
    public function seeInUploadedFile($file, $contents, $date = null) {
        \PHPUnit_Framework_Assert::assertStringEqualsFile($this->getUploadsPath($file, $date), $contents);
    }

    /**
     * Checks that a file in the uploads folder does contain a string.
     *
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->dontSeeInUploadedFile('some-file.txt', 'foo');
     * $I->dontSeeInUploadedFile('some-file.txt','foo', 'today');
     * ?>
     * ```
     *
     * @param string $filename
     * @param string $contents
     * @param string $date
     */
    public function dontSeeInUploadedFile($file, $contents, $date = null) {
        \PHPUnit_Framework_Assert::assertStringNotEqualsFile($this->getUploadsPath($file, $date), $contents);
    }

    /**
     * Deletes a dir in the uploads folder.
     * 
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->deleteUploadedDir('folder');
     * $I->deleteUploadedDir('folder', 'today');
     * ?>
     * ```
     * 
     * @param  string $dir
     * @param  string $date
     */
    public function deleteUploadedDir($dir, $date = null) {
        $dir = $this->getUploadsPath($dir, $date);
        $this->deleteDir($dir);
    }

    /**
     * Deletes a file in the uploads folder.
     * 
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->deleteUploadedFile('some-file.txt');
     * $I->deleteUploadedFile('some-file.txt', 'today');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $date
     */
    public function deleteUploadedFile($file, $date = null) {
        $file = $this->getUploadsPath($file, $date);
        $this->deleteFile($file);
    }

    /**
     * Clears a directory in the uploads folder.
     * 
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->cleanUploadsDir('some/folder');
     * $I->cleanUploadsDir('some/folder', 'today');
     * ?>
     * ```
     * 
     * @param  string $dir
     * @param  string $date
     */
    public function cleanUploadsDir($dir = null, $date = null) {
        $dir = empty($dir) ? $this->config['uploads'] : $this->getUploadsPath($dir, $date);
        $this->cleanDir($dir);
    }

    /**
     * Copies a directory to the uploads folder.
     * 
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->copyDirToUploads(codecept_data_dir('foo'), 'uploadsFoo');
     * $I->copyDirToUploads(codecept_data_dir('foo'), 'uploadsFoo', 'today');
     * ?>
     * ```
     * 
     * @param  string $src
     * @param  string $dst
     * @param  string $date
     */
    public function copyDirToUploads($src, $dst, $date = null) {
        $this->copyDir($src, $this->getUploadsPath($dst, $date));
    }

    /**
     * Writes a string to a file in the the uploads folder.
     * 
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->writeToUploadedFile('some-file.txt', 'foo bar');
     * $I->writeToUploadedFile('some-file.txt', 'foo bar', 'today');
     * ?>
     * ```
     * 
     * @param  string $filename
     * @param  string $data
     * @param  string $date
     */
    public function writeToUploadedFile($filename, $data, $date = null) {
        $filename = $this->getUploadsPath($filename, $date);
        file_put_contents($filename, $data);
    }

    /**
     * Opens a file in the the uploads folder.
     * 
     * The date argument can be a string compatible with `strtotime` or a Unix timestamp that will be used
     * to build the `Y/m` uploads subfolder path.
     *
     * ``` php
     * <?php
     * $I->openUploadedFile('some-file.txt');
     * $I->openUploadedFile('some-file.txt', 'time');
     * ?>
     * ```
     * 
     * @param  string $filename
     * @param  string $date
     */
    public function openUploadedFile($filename, $date = null) {
        $this->openFile($this->getUploadsPath($filename, $date));
    }

    /**
     * Sets the current working directory to a directory in a plugin.
     *
     * ``` php
     * <?php
     * $I->amInPluginPath('my-plugin');
     * ?>
     * ```
     * 
     * @param  string $path
     */
    public function amInPluginPath($path) {
        $this->amInPath($this->config['plugins'] . DIRECTORY_SEPARATOR . Utils::unleadslashit($path));
    }

    /**
     * Copies a directory to a directory in a plugin.
     *
     * ``` php
     * <?php
     * $I->copyDirToPlugin(codecept_data_dir('foo'), 'plugin/foo');
     * ?>
     * ```
     * 
     * @param  string $src
     * @param  string $pluginDst
     */
    public function copyDirToPlugin($src, $pluginDst) {
        $this->copyDir($src, $this->config['plugins'] . Utils::unleadslashit($pluginDst));
    }

    /**
     * Deletes a file in a plugin directory.
     *
     * ``` php
     * <?php
     * $I->deletePluginFile('plugin1/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function deletePluginFile($file) {
        $this->deleteFile($this->config['plugins'] . Utils::unleadslashit($file));
    }

    /**
     * Writes a file in a plugin directory.
     *
     * ``` php
     * <?php
     * $I->writeToPluginFile('plugin1/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $data
     */
    public function writeToPluginFile($file, $data) {
        $this->writeToFile($this->config['plugins'] . Utils::unleadslashit($file), $data);
    }

    /**
     * Checks that a file is not found in a plugin directory.
     *
     * ``` php
     * <?php
     * $I->dontSeePluginFileFound('plugin1/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function dontSeePluginFileFound($file) {
        $this->dontSeeFileFound($this->config['plugins'] . Utils::unleadslashit($file));
    }

    /**
     * Checks that a file is found in a plugin directory.
     *
     * ``` php
     * <?php
     * $I->seePluginFileFound('plugin1/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function seePluginFileFound($file) {
        $this->seeFileFound($this->config['plugins'] . Utils::unleadslashit($file));
    }

    /**
     * Checks that a file in a plugin directory contains a string.
     *
     * ``` php
     * <?php
     * $I->seeInPluginFile('plugin1/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $contents
     */
    public function seeInPluginFile($file, $contents) {\PHPUnit_Framework_Assert::assertStringEqualsFile($this->config['plugins'] . Utils::unleadslashit($file), $contents);
    }

    /**
     * Checks that a file in a plugin directory does not contain a string.
     *
     * ``` php
     * <?php
     * $I->dontSeeInPluginFile('plugin1/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $contents
     */
    public function dontSeeInPluginFile($file, $contents) {
        \PHPUnit_Framework_Assert::assertStringNotEqualsFile($this->config['plugins'] . Utils::unleadslashit($file), $contents);
    }

    /**
     * Cleans a directory in a plugin directory.
     *
     * ``` php
     * <?php
     * $I->cleanPluginDir('plugin1/foo');
     * ?>
     * ```
     * 
     * @param  string $dir
     */
    public function cleanPluginDir($dir) {
        $this->cleanDir($this->config['plugins'] . Utils::unleadslashit($dir));
    }

    /**
     * Sets the current working directory to a directory in a theme.
     *
     * ``` php
     * <?php
     * $I->amInThemePath('my-theme');
     * ?>
     * ```
     * 
     * @param  string $path
     */
    public function amInThemePath($path) {
        $this->amInPath($this->config['themes'] . DIRECTORY_SEPARATOR . Utils::unleadslashit($path));
    }

    /**
     * Copies a directory in a theme directory.
     *
     * ``` php
     * <?php
     * $I->copyDirToTheme(codecept_data_dir('foo'), 'my-theme');
     * ?>
     * ```
     * 
     * @param  string $src
     * @param  string $themeDst
     */
    public function copyDirToTheme($src, $themeDst) {
        $this->copyDir($src, $this->config['themes'] . Utils::unleadslashit($themeDst));
    }

    /**
     * Deletes a file in a theme directory.
     *
     * ``` php
     * <?php
     * $I->deleteThemeFile('my-theme/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function deleteThemeFile($file) {
        $this->deleteFile($this->config['themes'] . Utils::unleadslashit($file));
    }

    /**
     * Writes a string to a file in a theme directory.
     *
     * ``` php
     * <?php
     * $I->writeToThemeFile('my-theme/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $data
     */
    public function writeToThemeFile($file, $data) {
        $this->writeToFile($this->config['themes'] . Utils::unleadslashit($file), $data);
    }

    /**
     * Checks that a file is not found in a theme directory.
     *
     * ``` php
     * <?php
     * $I->dontSeeThemeFileFound('my-theme/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function dontSeeThemeFileFound($file) {
        $this->dontSeeFileFound($this->config['themes'] . Utils::unleadslashit($file));
    }

    /**
     * Checks that a file is found in a theme directory.
     *
     * ``` php
     * <?php
     * $I->seeThemeFileFound('my-theme/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function seeThemeFileFound($file) {
        $this->seeFileFound($this->config['themes'] . Utils::unleadslashit($file));
    }

    /**
     * Checks that a file in a theme directory contains a string.
     *
     * ``` php
     * <?php
     * $I->seeInThemeFile('my-theme/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $contents
     */
    public function seeInThemeFile($file, $contents) {
        \PHPUnit_Framework_Assert::assertStringEqualsFile($this->config['themes'] . Utils::unleadslashit($file), $contents);
    }

    /**
     * Checks that a file in a theme directory does not contain a string.
     *
     * ``` php
     * <?php
     * $I->dontSeeInThemeFile('my-theme/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $contents
     */
    public function dontSeeInThemeFile($file, $contents) {
        \PHPUnit_Framework_Assert::assertStringNotEqualsFile($this->config['themes'] . Utils::unleadslashit($file), $contents);
    }

    /**
     * Clears a directory in a theme directory.
     *
     * ``` php
     * <?php
     * $I->cleanThemeDir('my-theme/foo');
     * ?>
     * ```
     * 
     * @param  string $dir
     */
    public function cleanThemeDir($dir) {
        $this->cleanDir($this->config['themes'] . Utils::unleadslashit($dir));
    }

    /**
     * Sets the current working directory to a directory in a mu-plugin.
     *
     * ``` php
     * <?php
     * $I->amInMuPluginPath('mu-plugin');
     * ?>
     * ```
     * 
     * @param  string $path
     */
    public function amInMuPluginPath($path) {
        $this->amInPath($this->config['mu-plugins'] . DIRECTORY_SEPARATOR . Utils::unleadslashit($path));
    }

    /**
     * Copies a directory to a directory in a mu-plugin.
     *
     * ``` php
     * <?php
     * $I->copyDirToMuPlugin(codecept_data_dir('foo'), 'mu-plugin/foo');
     * ?>
     * ```
     * 
     * @param  string $src
     * @param  string $pluginDst
     */   
    public function copyDirToMuPlugin($src, $pluginDst) {
        $this->copyDir($src, $this->config['mu-plugins'] . Utils::unleadslashit($pluginDst));
    }

    /**
     * Deletes a file in a mu-plugin directory.
     *
     * ``` php
     * <?php
     * $I->deleteMuPluginFile('mu-plugin1/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function deleteMuPluginFile($file) {
        $this->deleteFile($this->config['mu-plugins'] . Utils::unleadslashit($file));
    }

    /**
     * Writes a file in a mu-plugin directory.
     *
     * ``` php
     * <?php
     * $I->writeToMuPluginFile('mu-plugin1/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $data
     */
    public function writeToMuPluginFile($file, $data) {
        $this->writeToFile($this->config['mu-plugins'] . Utils::unleadslashit($file), $data);
    }

    /**
     * Checks that a file is not found in a mu-plugin directory.
     *
     * ``` php
     * <?php
     * $I->dontSeeMuPluginFileFound('mu-plugin1/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function dontSeeMuPluginFileFound($file) {
        $this->dontSeeFileFound($this->config['mu-plugins'] . Utils::unleadslashit($file));
    }

    /**
     * Checks that a file is found in a mu-plugin directory.
     *
     * ``` php
     * <?php
     * $I->seeMuPluginFileFound('mu-plugin1/some-file.txt');
     * ?>
     * ```
     * 
     * @param  string $file
     */
    public function seeMuPluginFileFound($file) {
        $this->seeFileFound($this->config['mu-plugins'] . Utils::unleadslashit($file));
    }

    /**
     * Checks that a file in a mu-plugin directory contains a string.
     *
     * ``` php
     * <?php
     * $I->seeInMuPluginFile('mu-plugin1/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $contents
     */
    public function seeInMuPluginFile($file, $contents) {
        \PHPUnit_Framework_Assert::assertStringEqualsFile($this->config['mu-plugins'] . Utils::unleadslashit($file), $contents);
    }

    /**
     * Checks that a file in a mu-plugin directory does not contain a string.
     *
     * ``` php
     * <?php
     * $I->dontSeeInMuPluginFile('mu-plugin1/some-file.txt', 'foo');
     * ?>
     * ```
     * 
     * @param  string $file
     * @param  string $contents
     */
    public function dontSeeInMuPluginFile($file, $contents) {
        \PHPUnit_Framework_Assert::assertStringNotEqualsFile($this->config['mu-plugins'] . Utils::unleadslashit($file), $contents);
    }

    /**
     * Cleans a directory in a mu-plugin directory.
     *
     * ``` php
     * <?php
     * $I->cleanMuPluginDir('mu-plugin1/foo');
     * ?>
     * ```
     * 
     * @param  string $dir
     */
    public function cleanMuPluginDir($dir) {
        $this->cleanDir($this->config['mu-plugins'] . Utils::unleadslashit($dir));
    }
}