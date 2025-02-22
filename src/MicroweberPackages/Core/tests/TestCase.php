<?php

namespace MicroweberPackages\Core\tests;

use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use MicroweberPackages\User\Models\User;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;
use Tests\CreatesApplication;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    // use WithConsoleEvents;

    use CreatesApplication;

    public $parserErrorStrings = ['mw_replace_back', 'tag-comment', 'mw-unprocessed-module-tag', 'parser_'];
    private $sqlite_file = 'phpunit.sqlite';
    public $template_name = '';

    protected function setUp(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');
        ini_set('memory_limit', '-1');
        \Illuminate\Support\Env::getRepository()->set('APP_ENV', 'testing');

        $config_folder = __DIR__ . '/../../../../config/testing/';
        $config_folder = realpath($config_folder);

        $mw_file = $config_folder . '/microweber.php';
        if (!is_file($mw_file)) {
            $this->install();
        }



        parent::setUp();


    }



    public function install()
    {

        ini_set('memory_limit', '4024M');
        ini_set('max_execution_time', '3000');

        if (!defined('MW_UNIT_TEST')) {
            define('MW_UNIT_TEST', true);
        }

        \Illuminate\Support\Env::getRepository()->set('APP_ENV', 'testing');

        $testing_env_name = 'testing';
        $testEnvironment = $testing_env_name = env('APP_ENV') ? env('APP_ENV') : 'testing';

        $config_folder = __DIR__ . '/../../../../config/';


        if ($testEnvironment == 'testing') {
            $config_folder = $config_folder . 'testing/';
        }

        if (!is_dir($config_folder)) {
            mkdir($config_folder);
        }


        $mw_file = $config_folder . 'microweber.php';
        $mw_file_database = $config_folder . 'database.php';
        $mw_file = $this->normalizePath($mw_file, false);

        $test_env_from_conf = env('APP_ENV_TEST_FROM_CONFIG');
        if ($test_env_from_conf) {
            $testing_env_name = $testEnvironment = $test_env_from_conf;
            putenv("APP_ENV=$testing_env_name");
            if (!defined('MW_UNIT_TEST_ENV_FROM_TEST')) {
                define('MW_UNIT_TEST_ENV_FROM_TEST', $testing_env_name);
                $config_folder = __DIR__ . '/../../../../config/';
                $config_folder = realpath($config_folder);
                $mw_file = $config_folder . '/microweber.php';
            }
        }

        if (!defined('MW_UNIT_TEST_CONF_FILE_CREATED')) {
            if (is_file($mw_file_database)) {
                @unlink($mw_file_database);
            }
            file_put_contents($mw_file, "<?php return array (
            'is_installed' => 0,
            'compile_assets' => 0,
            'install_default_template' => 'default',
            'install_default_template_content' => 1,
            );"
            );


            //   rmdir_recursive($config_folder, 1);
        }


        $app = require __DIR__ . '/../../../../bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        $app->detectEnvironment(function () use ($testing_env_name) {
            return $testing_env_name;
        });
        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file',
            [
                'driver' => 'file',
                'path' => storage_path('framework/cache'),
                'separator' => '~#~'
            ]
        );

        if (!defined('MW_UNIT_TEST_CONF_FILE_CREATED')) {

            define('MW_UNIT_TEST_CONF_FILE_CREATED', true);

            $this->assertEquals(true, is_dir($config_folder));


            $environment = $app->environment();
            $this->sqlite_file = $this->normalizePath(storage_path() . '/phpunit.' . $environment . '.sqlite', false);


            if (!defined('MW_UNIT_TEST_DB_CLEANED')) {
                if (is_file($this->sqlite_file)) {
                    @unlink($this->sqlite_file);
                }
                define('MW_UNIT_TEST_DB_CLEANED', true);

            }

            $db_driver = env('DB_DRIVER') ? env('DB_DRIVER') : 'sqlite';
            $db_host = env('DB_HOST', '127.0.0.1');
            $db_port = env('DB_PORT', '');

            $db_user = env('DB_USERNAME', 'forge');
            $db_pass = env('DB_PASSWORD', '');
            $db_prefix = env('DB_PREFIX', 'phpunit_test_');
            //  $db_name = env('DB_DATABASE', $this->sqlite_file);
            $db_name = env('DB_DATABASE') ? env('DB_DATABASE') : $this->sqlite_file;


            //  $db_name = $this->sqlite_file;
            if ($test_env_from_conf) {
                $dbEngines = \Config::get('database.connections');
                $defaultDbEngine = \Config::get('database.default');
                $default = \Config::get('database');

                if ($defaultDbEngine) {
                    $db_driver = $defaultDbEngine;
                    if (isset($dbEngines[$defaultDbEngine]) and is_array($dbEngines[$defaultDbEngine])) {
                        $config = $dbEngines[$defaultDbEngine];
                        if (isset($config['database'])) {
                            $db_name = $config['database'];
                        }
                        if (isset($config['host'])) {
                            $db_host = $config['host'];
                        }
                        if (isset($config['host'])) {
                            $db_host = $config['host'];
                        }
                        if (isset($config['username'])) {
                            $db_user = $config['username'];
                        }
                        if (isset($config['password'])) {
                            $db_pass = $config['password'];
                        }
                        if (isset($config['prefix'])) {
                            $db_prefix = $config['prefix'];
                        }
                    }
                }

            }

            // make fresh install
            $install_params = array(
                '--username' => 'test' . uniqid(),
                '--password' => 'test',
                '--email' => 'test' . uniqid() . '@example.com',
                '--db-driver' => $db_driver,
                '--db-host' => $db_host,
                '--db-username' => $db_user,
                '--db-password' => $db_pass,
                '--db-name' => $db_name,
                '--db-prefix' => $db_prefix,
                //  '--db-name' => ':memory:',
                '--env' => $environment,
            );

            //  $is_installed = mw_is_installed();

            //if (!$is_installed) {

            $install = \Artisan::call('microweber:install', $install_params);

            $this->assertEquals(0, $install);

            // Clear caches
            \Artisan::call('config:cache');
            \Artisan::call('config:clear');
            \Artisan::call('cache:clear');
            \Artisan::call('route:clear');

            $is_installed = mw_is_installed();
            $this->assertEquals(1, $is_installed);
            //  }
            // }
            //\Config::set('microweber.is_installed' , 1);
            \Config::set('mail.driver', 'array');
            \Config::set('queue.driver', 'sync');
            \Config::set('mail.transport', 'array');


        }


        //  $app['env'] = $testing_env_name;
        // $environment = $app->environment();
//        load_all_functions_files_for_modules();
//        load_all_service_providers_for_modules();
//         load_functions_files_for_template();
//        load_service_providers_for_template();
        $app->rebootApplication();
        $this->app = $app;
        return $app;
    }

    public static function setUpBeforeClass(): void
    {

        $test_env_from_conf = env('APP_ENV_TEST_FROM_CONFIG');
        if ($test_env_from_conf) {
            $testing_env_name = $testEnvironment = $test_env_from_conf;
            putenv("APP_ENV=$testing_env_name");
        }



        parent::setUpBeforeClass();

    }

    protected function xxxtearDown(): void
    {
        //echo 'pre reduce memory usage: '.sprintf('%.2fM', memory_get_usage(true)/1024/1024);
        // reduce memory usage

        // get all properties of self
        $refl = new \ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            // if not phpunit related or static
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                // make accessible and set value to to free memory
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
        //echo 'post reduce memory usage: '.sprintf('%.2fM', memory_get_usage(true)/1024/1024);

        parent::tearDown();
    }


    private function normalizePath($path, $slash_it = true)
    {
        $path_original = $path;
        $s = DIRECTORY_SEPARATOR;
        $path = preg_replace('/[\/\\\]/', $s, $path);
        $path = str_replace($s . $s, $s, $path);
        if (strval($path) == '') {
            $path = $path_original;
        }
        if ($slash_it == false) {
            $path = rtrim($path, DIRECTORY_SEPARATOR);
        } else {
            $path .= DIRECTORY_SEPARATOR;
            $path = rtrim($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
        }
        if (strval(trim($path)) == '' or strval(trim($path)) == '/') {
            $path = $path_original;
        }
        if ($slash_it == false) {
        } else {
            $path = $path . DIRECTORY_SEPARATOR;
            $path = $this->reduce_double_slashes($path);
        }

        return $path;

    }

    public function testTrue()
    {
        $this->assertTrue(true);
    }

    /**
     * Removes double slashes from sting.
     *
     * @param $str
     *
     * @return string
     */
    private function reduce_double_slashes($str)
    {
        return preg_replace('#([^:])//+#', '\\1/', $str);
    }


    protected function assertPreConditions(): void
    {
        $this->assertEquals('testing', \Illuminate\Support\Env::get('APP_ENV'));
        $this->assertEquals('testing', app()->environment());


        if ($this->template_name) {

            save_option('current_template', $this->template_name, 'template');
        }
    }


    public function getEmailDataAsArrayFromObject($email)
    {

        $emailOriginal = $email->getOriginalMessage();
        $body = $emailOriginal->getBody();

        $emailAsArray = [];

        $emailAsArray['body'] = '';
        if ($body instanceof TextPart) {
            $emailAsArray['body'] = $body->getBody();
        }

        if ($body instanceof MixedPart) {
            $emailAsArray['body'] = $body->bodyToString();

            if (isset($emailAsArray['body'])) {
                $emailAsArray['body'] = utf8_encode(quoted_printable_decode($emailAsArray['body']));
            }
        }


        $emailAsArray['subject'] = $emailOriginal->getSubject();
        $emailAsArray['to'] = $emailOriginal->getTo()[0]->getAddress();
        $emailAsArray['from'] = $emailOriginal->getFrom()[0]->getAddress();

        $emailAsArray['replyTo'] = false;

        if (isset($emailOriginal->getReplyTo()[0]) && !empty($emailOriginal->getReplyTo()[0]->getAddress())) {
            $emailAsArray['replyTo'] = $emailOriginal->getReplyTo()[0]->getAddress();
        }

        return $emailAsArray;
    }
    protected function loginAsAdmin()
    {
        if (app()->environment() != 'testing') {
            return;
        }

        $user = User::where('is_admin', 1)->first();

        if (!$user) {
            $user = new User();
            $user->username = 'test'.uniqid();
            $user->password = 'test'.uniqid();
            $user->email = 'bobi@microweber.com';
            $user->is_admin = 1;
            $user->save();
        }
        Auth::login($user);


    }
}

