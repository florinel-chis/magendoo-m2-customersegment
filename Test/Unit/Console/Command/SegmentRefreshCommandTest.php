<?php
/**
 * Magendoo CustomerSegment - SegmentRefreshCommand unit tests
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Console\Command;

use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Console\Command\SegmentRefreshCommand;
use Magendoo\CustomerSegment\Helper\Data as Helper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SegmentRefreshCommand::class)]
class SegmentRefreshCommandTest extends TestCase
{
    /** @var SegmentManagementInterface&MockObject */
    private $segmentManagement;

    /** @var SegmentRepositoryInterface&MockObject */
    private $segmentRepository;

    /** @var Helper&MockObject */
    private $helper;

    /** @var Filesystem&MockObject */
    private $filesystem;

    /** @var DateTime&MockObject */
    private $dateTime;

    /** @var SegmentRefreshCommand */
    private $command;

    /** @var CommandTester */
    private $tester;

    protected function setUp(): void
    {
        $this->segmentManagement = $this->createMock(SegmentManagementInterface::class);
        $this->segmentRepository = $this->createMock(SegmentRepositoryInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->dateTime = $this->createMock(DateTime::class);

        // Enabled by default; individual tests override when they exercise the gate.
        $this->helper->method('isEnabled')->willReturn(true);

        $this->command = new SegmentRefreshCommand(
            $this->segmentManagement,
            $this->segmentRepository,
            $this->helper,
            $this->filesystem,
            $this->dateTime
        );
        $this->tester = new CommandTester($this->command);
    }

    public function testDisabledModuleReturnsFailureAndDoesNothing(): void
    {
        // A fresh helper that reports the module disabled.
        $helper = $this->createMock(Helper::class);
        $helper->method('isEnabled')->willReturn(false);

        $command = new SegmentRefreshCommand(
            $this->segmentManagement,
            $this->segmentRepository,
            $helper,
            $this->filesystem,
            $this->dateTime
        );
        $tester = new CommandTester($command);

        $this->segmentManagement->expects($this->never())->method('refreshSegment');

        $exitCode = $tester->execute(['segment_id' => ['1']]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testRefreshSingleSegmentReportsAssignedCount(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getName')->willReturn('VIP Customers');

        $this->segmentRepository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($segment);

        $this->segmentManagement->expects($this->once())
            ->method('refreshSegment')
            ->with(1)
            ->willReturn(5);

        $exitCode = $this->tester->execute(['segment_id' => ['1']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('VIP Customers', $display);
        $this->assertStringContainsString('5', $display);
    }

    public function testRefreshMultipleSegmentsCastsIdsToInt(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getName')->willReturn('Segment');
        $this->segmentRepository->method('getById')->willReturn($segment);

        $refreshed = [];
        $this->segmentManagement->method('refreshSegment')
            ->willReturnCallback(function (int $id) use (&$refreshed) {
                $refreshed[] = $id;
                return 1;
            });

        $exitCode = $this->tester->execute(['segment_id' => ['1', '2', '3']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertSame([1, 2, 3], $refreshed);
    }

    public function testRefreshAllOptionRefreshesEverySegment(): void
    {
        $this->segmentManagement->expects($this->once())->method('refreshAllSegments');

        $exitCode = $this->tester->execute(['--all' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('All segments refreshed', $this->tester->getDisplay());
    }

    public function testNoSegmentIdAndNoAllReturnsFailure(): void
    {
        $this->segmentManagement->expects($this->never())->method('refreshSegment');
        $this->segmentManagement->expects($this->never())->method('refreshAllSegments');

        $exitCode = $this->tester->execute([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('provide segment ID', $this->tester->getDisplay());
    }

    public function testPerSegmentRefreshErrorIsReportedButLoopContinues(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getName')->willReturn('Segment');
        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentManagement->method('refreshSegment')
            ->willThrowException(new \Exception('boom'));

        $exitCode = $this->tester->execute(['segment_id' => ['1']]);

        // A per-segment failure is caught and reported; the command still exits successfully.
        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('boom', $this->tester->getDisplay());
    }

    public function testExportRejectsInvalidFormat(): void
    {
        $this->segmentManagement->expects($this->never())->method('exportSegmentCustomers');

        $exitCode = $this->tester->execute([
            'segment_id' => ['1'],
            '--export' => true,
            '--format' => 'json',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid --format', $this->tester->getDisplay());
    }

    public function testExportWritesFileForValidFormat(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getName')->willReturn('Segment');
        $this->segmentRepository->method('getById')->with(1)->willReturn($segment);

        $this->segmentManagement->expects($this->once())
            ->method('exportSegmentCustomers')
            ->with(1, 'csv')
            ->willReturn("Customer ID,Email\n1,test@example.com\n");

        $this->dateTime->method('gmtDate')->willReturn('2026-07-20_00-00-00');

        $directory = $this->createMock(WriteInterface::class);
        $directory->expects($this->once())->method('create')->with('export');
        $directory->expects($this->once())
            ->method('writeFile')
            ->with('export/segment_1_customers_2026-07-20_00-00-00.csv', $this->isString());
        $directory->method('getAbsolutePath')
            ->willReturn('/var/www/var/export/segment_1_customers_2026-07-20_00-00-00.csv');

        $this->filesystem->expects($this->once())
            ->method('getDirectoryWrite')
            ->with(DirectoryList::VAR_DIR)
            ->willReturn($directory);

        $exitCode = $this->tester->execute([
            'segment_id' => ['1'],
            '--export' => true,
            '--format' => 'csv',
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Exported to', $this->tester->getDisplay());
    }

    public function testExportReportsNoCustomersWhenEmpty(): void
    {
        $segment = $this->createMock(SegmentInterface::class);
        $segment->method('getName')->willReturn('Segment');
        $this->segmentRepository->method('getById')->willReturn($segment);

        $this->segmentManagement->method('exportSegmentCustomers')->willReturn('');

        $this->filesystem->expects($this->never())->method('getDirectoryWrite');

        $exitCode = $this->tester->execute([
            'segment_id' => ['1'],
            '--export' => true,
            '--format' => 'csv',
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('No customers to export', $this->tester->getDisplay());
    }

    public function testExportFailureIsCaughtAndReported(): void
    {
        $this->segmentRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Segment not found')));

        $exitCode = $this->tester->execute([
            'segment_id' => ['999'],
            '--export' => true,
            '--format' => 'csv',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('Export failed', $this->tester->getDisplay());
    }
}
