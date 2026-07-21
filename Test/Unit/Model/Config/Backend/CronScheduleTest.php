<?php
/**
 * Magendoo CustomerSegment - Cron schedule config backend unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Config\Backend;

use Magendoo\CustomerSegment\Model\Config\Backend\CronSchedule;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CronSchedule::class)]
class CronScheduleTest extends TestCase
{
    private const CRON_STRING_PATH =
        'crontab/customer_segment/jobs/magendoo_customersegment_refresh/schedule/cron_expr';

    /** @var ScheduleFactory&MockObject */
    private ScheduleFactory $scheduleFactory;

    /** @var ValueFactory&MockObject */
    private ValueFactory $configValueFactory;

    /** @var DateTime&MockObject */
    private DateTime $dateTime;

    /** @var CronSchedule */
    private CronSchedule $model;

    protected function setUp(): void
    {
        $this->scheduleFactory = $this->createMock(ScheduleFactory::class);
        $this->configValueFactory = $this->createMock(ValueFactory::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('gmtDate')->willReturn('2026-07-20 00:00:00');

        $objectManager = new ObjectManager($this);
        $this->model = $objectManager->getObject(CronSchedule::class, [
            'configValueFactory' => $this->configValueFactory,
            'scheduleFactory' => $this->scheduleFactory,
            'dateTime' => $this->dateTime,
        ]);
    }

    /**
     * @param bool|\Throwable $tryScheduleResult
     * @return Schedule&MockObject
     */
    private function schedule(bool|\Throwable $tryScheduleResult): Schedule
    {
        // setCronExpr/setScheduledAt are magic DataObject setters; only trySchedule is real.
        $schedule = $this->getMockBuilder(Schedule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trySchedule'])
            ->getMock();

        if ($tryScheduleResult instanceof \Throwable) {
            $schedule->method('trySchedule')->willThrowException($tryScheduleResult);
        } else {
            $schedule->method('trySchedule')->willReturn($tryScheduleResult);
        }

        return $schedule;
    }

    /**
     * A config Value whose magic setValue/setPath pass through the real __call,
     * stubbing only the real load()/save() persistence methods.
     *
     * @param \Throwable|null $saveThrows
     * @return Value&MockObject
     */
    private function configValue(?\Throwable $saveThrows = null): Value
    {
        $configValue = $this->getMockBuilder(Value::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load', 'save'])
            ->getMock();

        if ($saveThrows !== null) {
            $configValue->method('save')->willThrowException($saveThrows);
        }

        return $configValue;
    }

    private function stubValidSchedule(): void
    {
        $this->scheduleFactory->method('create')->willReturn($this->schedule(true));
    }

    #[Test]
    public function beforeSaveAcceptsValidExpression(): void
    {
        $this->stubValidSchedule();
        $this->model->setValue('*/5 * * * *');

        $this->assertSame($this->model, $this->model->beforeSave());
    }

    #[Test]
    public function beforeSaveAcceptsEmptyExpressionWithoutValidating(): void
    {
        $this->scheduleFactory->expects($this->never())->method('create');
        $this->model->setValue('');

        $this->model->beforeSave();
        $this->assertSame('', $this->model->getValue());
    }

    #[Test]
    #[DataProvider('wrongFieldCountProvider')]
    public function beforeSaveRejectsWrongFieldCount(string $expression): void
    {
        $this->scheduleFactory->expects($this->never())->method('create');
        $this->model->setValue($expression);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('expected 5 space-separated fields');
        $this->model->beforeSave();
    }

    public static function wrongFieldCountProvider(): array
    {
        return [
            'too few' => ['* * * *'],
            'too many' => ['* * * * * *'],
        ];
    }

    #[Test]
    public function beforeSaveRejectsExpressionThatFailsScheduleParsing(): void
    {
        $this->scheduleFactory->method('create')
            ->willReturn($this->schedule(new \Exception('bad')));
        $this->model->setValue('99 99 99 99 99');

        $this->expectException(LocalizedException::class);
        $this->model->beforeSave();
    }

    #[Test]
    public function afterSaveMirrorsExpressionIntoCrontabConfigPath(): void
    {
        $this->model->setValue('0 2 * * *');

        $configValue = $this->configValue();
        $configValue->expects($this->once())->method('load')
            ->with(self::CRON_STRING_PATH, 'path');
        $configValue->expects($this->once())->method('save');
        $this->configValueFactory->method('create')->willReturn($configValue);

        $this->assertSame($this->model, $this->model->afterSave());

        // setValue/setPath are magic setters; verify what they stored.
        $this->assertSame('0 2 * * *', $configValue->getValue());
        $this->assertSame(self::CRON_STRING_PATH, $configValue->getPath());
    }

    #[Test]
    public function afterSaveFallsBackToDefaultExpressionWhenEmpty(): void
    {
        $this->model->setValue('');

        $configValue = $this->configValue();
        $this->configValueFactory->method('create')->willReturn($configValue);

        $this->model->afterSave();

        $this->assertSame('*/5 * * * *', $configValue->getValue());
    }

    #[Test]
    public function afterSaveWrapsPersistenceFailureInLocalizedException(): void
    {
        $this->model->setValue('0 2 * * *');

        $this->configValueFactory->method('create')
            ->willReturn($this->configValue(new \Exception('db')));

        $this->expectException(LocalizedException::class);
        $this->model->afterSave();
    }
}
