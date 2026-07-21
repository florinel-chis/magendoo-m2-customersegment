<?php
/**
 * Magendoo CustomerSegment - Helper\Data unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Helper;

use Magendoo\CustomerSegment\Helper\Data;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Data::class)]
class DataTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    /** @var ScheduleFactory&MockObject */
    private ScheduleFactory $scheduleFactory;

    /** @var DateTime&MockObject */
    private DateTime $dateTime;

    /** @var Data */
    private Data $helper;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scheduleFactory = $this->createMock(ScheduleFactory::class);
        $this->dateTime = $this->createMock(DateTime::class);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->helper = new Data($context, $this->scheduleFactory, $this->dateTime);
    }

    /**
     * Build a Schedule whose magic setCronExpr/setScheduledAt pass through the real
     * DataObject __call, stubbing only trySchedule (a real method).
     *
     * @param bool|\Throwable $tryScheduleResult true/false to return, or a throwable to raise
     * @return Schedule&MockObject
     */
    private function schedule(bool|\Throwable $tryScheduleResult): Schedule
    {
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

    #[Test]
    public function isEnabledReadsTheEnableFlagFromStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(Data::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseWhenFlagIsUnset(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse($this->helper->isEnabled(5));
    }

    #[Test]
    public function getDefaultRefreshModeFallsBackToManual(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Data::XML_PATH_DEFAULT_REFRESH_MODE, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame('manual', $this->helper->getDefaultRefreshMode());
    }

    #[Test]
    public function getDefaultRefreshModeReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('realtime');

        $this->assertSame('realtime', $this->helper->getDefaultRefreshMode());
    }

    #[Test]
    public function getCronScheduleFallsBackToDefault(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(Data::DEFAULT_CRON_SCHEDULE, $this->helper->getCronSchedule());
    }

    #[Test]
    public function getCronScheduleReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('0 3 * * *');

        $this->assertSame('0 3 * * *', $this->helper->getCronSchedule());
    }

    #[Test]
    public function formatConditionsReturnsPlaceholderWhenEmpty(): void
    {
        $this->assertSame('No conditions defined', $this->helper->formatConditions(null));
        $this->assertSame('No conditions defined', $this->helper->formatConditions([]));
    }

    #[Test]
    public function formatConditionsDescribesAllAggregator(): void
    {
        $this->assertSame(
            'Match ALL of the following:',
            $this->helper->formatConditions(['aggregator' => 'all'])
        );
    }

    #[Test]
    public function formatConditionsDescribesAnyAggregator(): void
    {
        $this->assertSame(
            'Match ANY of the following:',
            $this->helper->formatConditions(['aggregator' => 'any'])
        );
    }

    #[Test]
    #[DataProvider('validCronExpressionProvider')]
    public function validateCronExpressionAcceptsWellFormedExpressions(string $expression): void
    {
        $this->dateTime->method('gmtDate')->willReturn('2026-07-20 00:00:00');
        $this->scheduleFactory->method('create')->willReturn($this->schedule(true));

        $this->assertTrue($this->helper->validateCronExpression($expression));
    }

    public static function validCronExpressionProvider(): array
    {
        return [
            'every five minutes' => ['*/5 * * * *'],
            'daily at three' => ['0 3 * * *'],
            'named day' => ['0 0 * * MON'],
        ];
    }

    #[Test]
    #[DataProvider('wrongFieldCountProvider')]
    public function validateCronExpressionRejectsWrongFieldCount(string $expression): void
    {
        // Field-count check short-circuits before the Schedule is ever created.
        $this->scheduleFactory->expects($this->never())->method('create');

        $this->assertFalse($this->helper->validateCronExpression($expression));
    }

    public static function wrongFieldCountProvider(): array
    {
        return [
            'too few fields' => ['* * * *'],
            'too many fields' => ['* * * * * *'],
            'empty' => ['   '],
        ];
    }

    #[Test]
    public function validateCronExpressionRejectsExpressionScheduleThrowsOn(): void
    {
        $this->dateTime->method('gmtDate')->willReturn('2026-07-20 00:00:00');
        $this->scheduleFactory->method('create')
            ->willReturn($this->schedule(new \Exception('bad expr')));

        $this->assertFalse($this->helper->validateCronExpression('bogus bogus bogus bogus bogus'));
    }

    #[Test]
    #[DataProvider('statusLabelProvider')]
    public function getStatusLabelReturnsHumanLabel(bool $active, string $expected): void
    {
        $this->assertSame($expected, $this->helper->getStatusLabel($active));
    }

    public static function statusLabelProvider(): array
    {
        return [
            'active' => [true, 'Active'],
            'inactive' => [false, 'Inactive'],
        ];
    }

    #[Test]
    #[DataProvider('refreshModeLabelProvider')]
    public function getRefreshModeLabelMapsModes(string $mode, string $expected): void
    {
        $this->assertSame($expected, $this->helper->getRefreshModeLabel($mode));
    }

    public static function refreshModeLabelProvider(): array
    {
        return [
            'manual' => ['manual', 'Manual'],
            'cron' => ['cron', 'Cron Schedule'],
            'realtime' => ['realtime', 'Real-time'],
            'unknown passes through' => ['weird', 'weird'],
        ];
    }
}
