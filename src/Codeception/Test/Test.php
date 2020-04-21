<?php
namespace Codeception\Test;

use Codeception\TestInterface;
use Codeception\Util\ReflectionHelper;
use SebastianBergmann\Timer\Timer;
use Codeception\PHPUnit\Test as TestAbstract;
use PHPUnit\Framework\TestResult;

/**
 * The most simple testcase (with only one test in it) which can be executed by PHPUnit/Codeception.
 * It can be extended with included traits. Turning on/off a trait should not break class functionality.
 *
 * Class has exactly one method to be executed for testing, wrapped with before/after callbacks delivered from included traits.
 * A trait providing before/after callback should contain corresponding protected methods: `{traitName}Start` and `{traitName}End`,
 * then this trait should be enabled in `hooks` property.
 *
 * Inherited class must implement `test` method.
 */
abstract class Test extends TestAbstract implements TestInterface
{
    use Feature\AssertionCounter;
    use Feature\CodeCoverage;
    use Feature\ErrorLogger;
    use Feature\MetadataCollector;
    use Feature\IgnoreIfMetadataBlocked;

    private $testResult;
    private $ignored = false;

    /**
     * Enabled traits with methods to be called before and after the test.
     *
     * @var array
     */
    protected $hooks = [
      'ignoreIfMetadataBlocked',
      'codeCoverage',
      'assertionCounter',
      'errorLogger'
    ];

    const STATUS_FAIL = 'fail';
    const STATUS_ERROR = 'error';
    const STATUS_OK = 'ok';
    const STATUS_PENDING = 'pending';

    /**
     * Everything inside this method is treated as a test.
     *
     * @return mixed
     */
    abstract public function test();

    /**
     * Runs a test and collects its result in a TestResult instance.
     * Executes before/after hooks coming from traits.
     *
     * @param  \PHPUnit\Framework\TestResult $result
     * @return \PHPUnit\Framework\TestResult
     */
    final public function run(\PHPUnit\Framework\TestResult $result = null): TestResult
    {
        $this->testResult = $result;

        $status = self::STATUS_PENDING;
        $time = 0;
        $e = null;
        
        $result->startTest($this);

        foreach ($this->hooks as $hook) {
            if (method_exists($this, $hook.'Start')) {
                $this->{$hook.'Start'}();
            }
        }

        $failedToStart = ReflectionHelper::readPrivateProperty($result, 'lastTestFailed');

        if (!$this->ignored && !$failedToStart) {
            Timer::start();
            try {
                $this->test();
                $status = self::STATUS_OK;
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $status = self::STATUS_FAIL;
            } catch (\PHPUnit\Framework\Exception $e) {
                $status = self::STATUS_ERROR;
            } catch (\Throwable $e) {
                $e     = new \PHPUnit\Framework\ExceptionWrapper($e);
                $status = self::STATUS_ERROR;
            } catch (\Exception $e) {
                $e     = new \PHPUnit\Framework\ExceptionWrapper($e);
                $status = self::STATUS_ERROR;
            }
            $time = Timer::stop();
        }

        foreach (array_reverse($this->hooks) as $hook) {
            if (method_exists($this, $hook.'End')) {
                $this->{$hook.'End'}($status, $time, $e);
            }
        }

        $result->endTest($this, $time);
        return $result;
    }

    public function getTestResultObject()
    {
        return $this->testResult;
    }

    /**
     * This class represents exactly one test
     * @return int
     */
    public function count()
    {
        return 1;
    }

    /**
     * Should a test be skipped (can be set from hooks)
     *
     * @param boolean $ignored
     */
    protected function ignore($ignored)
    {
        $this->ignored = $ignored;
    }
}
