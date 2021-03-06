<?php // @codingStandardsIgnoreFile

namespace TestFrameworkBundle\Test;

use Doctrine\Common\DataFixtures\ReferenceRepository;

use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;

use Oro\Bundle\NavigationBundle\Event\ResponseHashnavListener;
use TestFrameworkBundle\Test\DataFixtures\AliceFixtureFactory;
use TestFrameworkBundle\Test\DataFixtures\AliceFixtureIdentifierResolver;
use TestFrameworkBundle\Test\DataFixtures\DataFixturesExecutor;
use TestFrameworkBundle\Test\DataFixtures\DataFixturesLoader;
use Oro\Component\Testing\DbIsolationExtension;

/**
 * Abstract class for functional and integration tests
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
abstract class WebTestCase extends BaseWebTestCase
{
    use DbIsolationExtension;

    /** Annotation names */
    const DB_ISOLATION_ANNOTATION = 'dbIsolation';
    const DB_ISOLATION_PER_TEST_ANNOTATION = 'dbIsolationPerTest';
    const DB_REINDEX_ANNOTATION   = 'dbReindex';

    /** Default WSSE credentials */
    const USER_NAME     = 'admin';
    const USER_PASSWORD = 'admin_api_key';

    /**  Default user name and password */
    const AUTH_USER         = 'admin@example.com';
    const AUTH_PW           = 'admin';
    const AUTH_ORGANIZATION = 1;

    /**
     * @var bool[]
     */
    private static $dbIsolation;

    /**
     * @var bool[]
     */
    private static $dbIsolationPerTest;

    /**
     * @var bool[]
     */
    private static $dbReindex;

    /**
     * @var Client
     */
    protected static $clientInstance;

    /**
     * @var Client
     */
    private static $soapClientInstance;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var SoapClient
     */
    protected $soapClient;

    /**
     * @var array
     */
    protected static $loadedFixtures = [];

    /**
     * @var ReferenceRepository
     */
    private static $referenceRepository;

    protected function tearDown()
    {
        if (self::getDbIsolationPerTestSetting()) {
            $this->rollbackTransaction();
            self::$loadedFixtures = [];
        }

        $refClass = new \ReflectionClass($this);
        foreach ($refClass->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
    }

    public static function setUpBeforeClass()
    {
        /**
         * In case we have isolated test we should have clean env before run it,
         * so we will not have next problem:
         * - Data provider in phpunit called before tests (even before this method) and can start a client
         *   for not isolated tests (ex. GetRestJsonApiTest),
         *   so we will have client without transaction started in our test
         */
        self::$clientInstance = null;
    }

    public static function tearDownAfterClass()
    {
        if (self::getDbIsolationSetting() || self::getDbIsolationPerTestSetting()) {
            self::rollbackTransaction();
        }

        self::$clientInstance = null;
        self::$soapClientInstance = null;
        self::$loadedFixtures = [];
    }

    /**
     * Creates a Client.
     *
     * @param array $options An array of options to pass to the createKernel class
     * @param array $server  An array of server parameters
     * @param bool  $force If this option - true, will reset client on each initClient call
     *
     * @return Client A Client instance
     */
    protected function initClient(array $options = [], array $server = [], $force = false)
    {
        if ($force) {
            $this->resetClient();
        }

        if (self::getDbIsolationPerTestSetting()) {
            $this->resetClient();
        }

        if (!self::$clientInstance) {
            // Fix for: The "native_profiler" extension is not enabled in "*.html.twig".
            // If you still getting this exception please run "php app/console cache:clear --env=test --no-debug".
            // The cache will be cleared and warmed up without the twig profiler.
            if (!isset($options['debug'])) {
                $options['debug'] = false;
            }

            $this->client = self::$clientInstance = static::createClient($options, $server);

            if (self::getDbIsolationSetting() || self::getDbIsolationPerTestSetting()) {
                //This is a workaround for MyISAM search tables that are not transactional
                if (self::getDbReindexSetting()) {
                    self::getContainer()->get('oro_search.search.engine.indexer')->reindex();
                }

                $this->startTransaction();
            }
        } else {
            self::$clientInstance->setServerParameters($server);
        }

        $this->client = self::$clientInstance;
    }

    /**
     * @param string $login
     */
    protected function loginUser($login)
    {
        if ('' !== $login) {
            self::$clientInstance->setServerParameters(static::generateBasicAuthHeader($login, $login));
        } else {
            self::$clientInstance->setServerParameters([]);
            $this->client->getCookieJar()->clear();
        }
    }

    /**
     * Reset client and rollback transaction
     */
    protected function resetClient()
    {
        if (self::$clientInstance) {
            if (self::getDbIsolationSetting() || self::getDbIsolationPerTestSetting()) {
                self::$loadedFixtures = [];
                $this->rollbackTransaction();
            }

            $this->client = null;
            self::$clientInstance = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected static function getKernelClass()
    {
        $dir = isset($_SERVER['KERNEL_DIR']) ? $_SERVER['KERNEL_DIR'] : static::getPhpUnitXmlDir();

        $finder = new Finder();
        $finder->name('AppKernel.php')->depth(0)->in($dir);
        $results = iterator_to_array($finder);

        if (count($results)) {
            $file  = current($results);
            $class = $file->getBasename('.php');

            require_once $file;
        } else {
            $class = parent::getKernelClass();
        }

        return $class;
    }

    /**
     * Get value of dbIsolation option from annotation of called class
     *
     * @return bool
     */
    private static function getDbIsolationSetting()
    {
        $calledClass = get_called_class();
        if (!isset(self::$dbIsolation[$calledClass])) {
            self::$dbIsolation[$calledClass] = self::isClassHasAnnotation($calledClass, self::DB_ISOLATION_ANNOTATION);
        }

        return self::$dbIsolation[$calledClass];
    }

    /**
     * Get value of dbIsolationPerTest option from annotation of called class
     *
     * @return bool
     */
    private static function getDbIsolationPerTestSetting()
    {
        $calledClass = get_called_class();
        if (!isset(self::$dbIsolationPerTest[$calledClass])) {
            self::$dbIsolationPerTest[$calledClass] = self::isClassHasAnnotation(
                $calledClass,
                self::DB_ISOLATION_PER_TEST_ANNOTATION
            );
        }

        return self::$dbIsolationPerTest[$calledClass];
    }

    /**
     * Get value of dbIsolation option from annotation of called class
     *
     * @return bool
     */
    private static function getDbReindexSetting()
    {
        $calledClass = get_called_class();
        if (!isset(self::$dbReindex[$calledClass])) {
            self::$dbReindex[$calledClass] = self::isClassHasAnnotation($calledClass, self::DB_REINDEX_ANNOTATION);
        }

        return self::$dbReindex[$calledClass];
    }

    /**
     * @param string $className
     * @param string $annotationName
     *
     * @return bool
     */
    private static function isClassHasAnnotation($className, $annotationName)
    {
        $annotations = \PHPUnit_Util_Test::parseTestMethodAnnotations($className);
        return isset($annotations['class'][$annotationName]);
    }

    /**
     * @param string $wsdl
     * @param array  $options
     * @param bool   $force
     *
     * @return SoapClient
     * @throws \Exception
     */
    protected function initSoapClient($wsdl = null, array $options = [], $force = false)
    {
        if (!self::$soapClientInstance || $force) {
            if ($wsdl === null) {
                $wsdl = "http://localhost/api/soap";
            }

            $options = array_merge(
                [
                    'location' => $wsdl,
                    'soap_version' => SOAP_1_2
                ],
                $options
            );

            $client = $this->getClientInstance();
            if ($options['soap_version'] == SOAP_1_2) {
                $contentType = 'application/soap+xml';
            } else {
                $contentType = 'text/xml';
            }
            $client->request('GET', $wsdl, [], [], ['CONTENT_TYPE' => $contentType]);
            $status = $client->getResponse()->getStatusCode();
            $wsdl = $client->getResponse()->getContent();
            if ($status >= 400) {
                throw new \Exception($wsdl, $status);
            }
            //save to file
            $file = tempnam(sys_get_temp_dir(), date("Ymd") . '_') . '.xml';
            $fl = fopen($file, "w");
            fwrite($fl, $wsdl);
            fclose($fl);

            self::$soapClientInstance = new SoapClient($file, $options, $client);

            unlink($file);
        }

        $this->soapClient = self::$soapClientInstance;
    }

    /**
     * Builds up the environment to run the given command.
     *
     * @param string $name
     * @param array  $params
     *
     * @return string
     */
    protected static function runCommand($name, array $params = [])
    {
        /** @var KernelInterface $kernel */
        $kernel = self::getContainer()->get('kernel');

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $argv = ['application', $name];
        foreach ($params as $k => $v) {
            if (is_bool($v)) {
                if ($v) {
                    $argv[] = $k;
                }
            } else {
                if (!is_int($k)) {
                    $argv[] = $k;
                }
                $argv[] = $v;
            }
        }
        $input = new ArgvInput($argv);
        $input->setInteractive(false);

        $fp = fopen('php://temp/maxmemory:' . (1024 * 1024 * 1), 'r+');
        $output = new StreamOutput($fp);

        $application->run($input, $output);

        rewind($fp);
        return stream_get_contents($fp);
    }

    /**
     * @param string[] $fixtures Each fixture can be a class name or a path to nelmio/alice file
     * @param bool     $force
     *
     * @link https://github.com/nelmio/alice
     */
    protected function loadFixtures(array $fixtures, $force = false)
    {
        $container = $this->getContainer();
        $fixtureIdentifierResolver = new AliceFixtureIdentifierResolver($container->get('kernel'));

        $fixtures = array_map(
            function ($value) use ($fixtureIdentifierResolver) {
                return $fixtureIdentifierResolver->resolveId($value);
            },
            $fixtures
        );
        if (!$force) {
            $fixtures = array_values(array_filter(
                $fixtures,
                function ($value) {
                    return !in_array($value, self::$loadedFixtures, true);
                }
            ));
            if (!$fixtures) {
                return;
            }
        }
        self::$loadedFixtures = array_merge(self::$loadedFixtures, $fixtures);

        $loader = new DataFixturesLoader(new AliceFixtureFactory(), $fixtureIdentifierResolver, $container);
        foreach ($fixtures as $fixture) {
            $loader->addFixture($fixture);
        }

        $executor = new DataFixturesExecutor($container->get('doctrine')->getManager());
        $executor->execute($loader->getFixtures(), true);
        self::$referenceRepository = $executor->getReferenceRepository();
        $this->postFixtureLoad();
    }

    /**
     * @param string $referenceUID
     *
     * @return object|mixed
     */
    protected function getReference($referenceUID)
    {
        return $this->getReferenceRepository()->getReference($referenceUID);
    }

    /**
     * @param string $referenceUID
     * @return bool
     */
    protected function hasReference($referenceUID)
    {
        return $this->getReferenceRepository()->hasReference($referenceUID);
    }

    /**
     * @return ReferenceRepository|null
     */
    protected function getReferenceRepository()
    {
        if (false == self::$referenceRepository) {
            throw new \LogicException('The reference repository is not set. Have you loaded fixtures?');
        }

        return self::$referenceRepository;
    }

    /**
     * Callback function to be executed after fixture load.
     */
    protected function postFixtureLoad()
    {
    }

    /**
     * Creates a mock object of a service identified by its id.
     *
     * @param string $id
     *
     * @return \PHPUnit_Framework_MockObject_MockBuilder
     */
    protected function getServiceMockBuilder($id)
    {
        $service = $this->getContainer()->get($id);
        $class = get_class($service);
        return $this->getMockBuilder($class)->disableOriginalConstructor();
    }

    /**
     * Generates a URL or path for a specific route based on the given parameters.
     *
     * @param string $name
     * @param array  $parameters
     * @param bool   $absolute
     *
     * @return string
     */
    protected function getUrl($name, $parameters = [], $absolute = false)
    {
        return self::getContainer()->get('router')->generate($name, $parameters, $absolute);
    }

    /**
     * Get an instance of the dependency injection container.
     *
     * @return ContainerInterface
     */
    protected static function getContainer()
    {
        return static::getClientInstance()->getContainer();
    }

    /**
     * @return Client
     * @throws \BadMethodCallException
     */
    public static function getClientInstance()
    {
        if (!self::$clientInstance) {
            throw new \BadMethodCallException('Client instance is not initialized.');
        }

        return self::$clientInstance;
    }

    /**
     * Add value from 'oro_default' route to the url
     *
     * @param array $data
     * @param string $urlParameterKey
     */
    public function addOroDefaultPrefixToUrlInParameterArray(&$data, $urlParameterKey)
    {
        $oroDefaultPrefix = $this->getUrl('oro_default');

        $replaceOroDefaultPrefixCallback = function (&$value) use ($oroDefaultPrefix, $urlParameterKey) {
            if (!is_null($value[$urlParameterKey])) {
                $value[$urlParameterKey] = str_replace(
                    '%oro_default_prefix%',
                    $oroDefaultPrefix,
                    $value[$urlParameterKey]
                );
            }
        };

        array_walk(
            $data,
            $replaceOroDefaultPrefixCallback
        );
    }

    /**
     * Data provider for REST/SOAP API tests
     *
     * @param string $folder
     *
     * @return array
     */
    public static function getApiRequestsData($folder)
    {
        static $randomString;

        // generate unique value
        if (!$randomString) {
            $randomString = self::generateRandomString(5);
        }

        $parameters = [];
        $testFiles = new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS);
        foreach ($testFiles as $fileName => $object) {
            $parameters[$fileName] = Yaml::parse(file_get_contents($fileName)) ?: [];
            if (is_null($parameters[$fileName]['response'])) {
                unset($parameters[$fileName]['response']);
            }
        }

        $replaceCallback = function (&$value) use ($randomString) {
            if (!is_null($value)) {
                $value = str_replace('%str%', $randomString, $value);
            }
        };

        foreach ($parameters as $key => $value) {
            array_walk(
                $parameters[$key]['request'],
                $replaceCallback,
                $randomString
            );
            array_walk(
                $parameters[$key]['response'],
                $replaceCallback,
                $randomString
            );
        }

        return $parameters;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public static function generateRandomString($length = 10)
    {
        $random = "";
        srand((double) microtime() * 1000000);
        $char_list = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $char_list .= "abcdefghijklmnopqrstuvwxyz";
        $char_list .= "1234567890_";

        for ($i = 0; $i < $length; $i++) {
            $random .= substr($char_list, (rand() % (strlen($char_list))), 1);
        }

        return $random;
    }

    /**
     * Generate WSSE authorization header
     *
     * @param string      $userName
     * @param string      $userPassword
     * @param string|null $nonce
     *
     * @return array
     */
    public static function generateWsseAuthHeader(
        $userName = self::USER_NAME,
        $userPassword = self::USER_PASSWORD,
        $nonce = null
    ) {
        if (null === $nonce) {
            $nonce = uniqid();
        }

        $created  = date('c');
        $digest   = base64_encode(sha1(base64_decode($nonce) . $created . $userPassword, true));
        $wsseHeader = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'WSSE profile="UsernameToken"',
            'HTTP_X-WSSE' => sprintf(
                'UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
                $userName,
                $digest,
                $nonce,
                $created
            )
        ];

        return $wsseHeader;
    }

    /**
     * Generate Basic  authorization header
     *
     * @param string $userName
     * @param string $userPassword
     * @param int    $userOrganization
     *
     * @return array
     */
    public static function generateBasicAuthHeader(
        $userName = self::AUTH_USER,
        $userPassword = self::AUTH_PW,
        $userOrganization = self::AUTH_ORGANIZATION
    ) {
        return [
            'PHP_AUTH_USER'         => $userName,
            'PHP_AUTH_PW'           => $userPassword,
            'PHP_AUTH_ORGANIZATION' => $userOrganization
        ];
    }

    /**
     * @return array
     */
    public static function generateNoHashNavigationHeader()
    {
        return ['HTTP_' . strtoupper(ResponseHashnavListener::HASH_NAVIGATION_HEADER) => 0];
    }

    /**
     * Convert value to array
     *
     * @param mixed $value
     *
     * @return array
     */
    public static function valueToArray($value)
    {
        return self::jsonToArray(json_encode($value));
    }

    /**
     * Convert json to array
     *
     * @param string $json
     *
     * @return array
     */
    public static function jsonToArray($json)
    {
        return (array)json_decode($json, true);
    }

    /**
     * Checks json response status code and return content as array
     *
     * @param Response $response
     * @param int      $statusCode
     *
     * @return array
     */
    public static function getJsonResponseContent(Response $response, $statusCode)
    {
        self::assertJsonResponseStatusCodeEquals($response, $statusCode);
        return self::jsonToArray($response->getContent());
    }

    /**
     * Assert response is json and has status code
     *
     * @param Response $response
     * @param int      $statusCode
     */
    public static function assertEmptyResponseStatusCodeEquals(Response $response, $statusCode)
    {
        self::assertResponseStatusCodeEquals($response, $statusCode);
        self::assertEmpty(
            $response->getContent(),
            sprintf('HTTP response with code %d must have empty body', $statusCode)
        );
    }

    /**
     * Assert response is json and has status code
     *
     * @param Response $response
     * @param int      $statusCode
     */
    public static function assertJsonResponseStatusCodeEquals(Response $response, $statusCode)
    {
        self::assertResponseStatusCodeEquals($response, $statusCode);
        self::assertResponseContentTypeEquals($response, 'application/json');
    }

    /**
     * Assert response is html and has status code
     *
     * @param Response $response
     * @param int      $statusCode
     */
    public static function assertHtmlResponseStatusCodeEquals(Response $response, $statusCode)
    {
        self::assertResponseStatusCodeEquals($response, $statusCode);
        self::assertResponseContentTypeEquals($response, 'text/html; charset=UTF-8');
    }

    /**
     * Assert response status code equals
     *
     * @param Response $response
     * @param int      $statusCode
     */
    public static function assertResponseStatusCodeEquals(Response $response, $statusCode)
    {
        try {
            \PHPUnit_Framework_TestCase::assertEquals($statusCode, $response->getStatusCode());
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
            if ($statusCode < 400
                && $response->getStatusCode() >= 400
                && $response->headers->contains('Content-Type', 'application/json')
            ) {
                $content = self::jsonToArray($response->getContent());
                if (!empty($content['message'])) {
                    $errors = null;
                    if (!empty($content['errors'])) {
                        $errors = is_array($content['errors'])
                            ? json_encode($content['errors'])
                            : $content['errors'];
                    }
                    $e = new \PHPUnit_Framework_ExpectationFailedException(
                        $e->getMessage()
                        . ' Error message: ' . $content['message']
                        . ($errors ? '. Errors: ' . $errors : ''),
                        $e->getComparisonFailure()
                    );
                } else {
                    $e = new \PHPUnit_Framework_ExpectationFailedException(
                        $e->getMessage() . ' Response content: ' . $response->getContent(),
                        $e->getComparisonFailure()
                    );
                }
            }
            throw $e;
        }
    }

    /**
     * Assert response content type equals
     *
     * @param Response $response
     * @param string   $contentType
     */
    public static function assertResponseContentTypeEquals(Response $response, $contentType)
    {
        \PHPUnit_Framework_TestCase::assertTrue(
            $response->headers->contains('Content-Type', $contentType),
            $response->headers
        );
    }

    /**
     * Assert that intersect of $actual with $expected equals $expected
     *
     * @param array  $expected
     * @param array  $actual
     * @param string $message
     */
    public static function assertArrayIntersectEquals(array $expected, array $actual, $message = null)
    {
        $actualIntersect = [];
        foreach (array_keys($expected) as $expectedKey) {
            if (array_key_exists($expectedKey, $actual)) {
                $actualIntersect[$expectedKey] = $actual[$expectedKey];
            }
        }
        \PHPUnit_Framework_TestCase::assertEquals(
            $expected,
            $actualIntersect,
            $message
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getClient()
    {
        return $this->client;
    }
}
