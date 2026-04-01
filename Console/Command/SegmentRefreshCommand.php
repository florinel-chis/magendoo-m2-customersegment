<?php
/**
 * Magendoo CustomerSegment CLI Refresh Command
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Console\Command;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to refresh customer segments
 */
class SegmentRefreshCommand extends Command
{
    /**
     * Command name
     */
    public const COMMAND_NAME = 'magendoo:customer-segment:refresh';

    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param SegmentRepositoryInterface $segmentRepository
     * @param string|null $name
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        SegmentRepositoryInterface $segmentRepository,
        ?string $name = null
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->segmentRepository = $segmentRepository;
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Refresh customer segments')
            ->setHelp(
                <<<HELP
This command allows you to refresh customer segments:

- Refresh specific segment(s):
  <comment>%command.full_name% 1</comment>
  <comment>%command.full_name% 1 2 3</comment>

- Refresh all active segments:
  <comment>%command.full_name% --all</comment>

- Export segment customers:
  <comment>%command.full_name% 1 --export --format=csv</comment>
HELP
            )
            ->addArgument(
                'segment_id',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Segment ID(s) to refresh'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Refresh all active segments'
            )
            ->addOption(
                'export',
                'e',
                InputOption::VALUE_NONE,
                'Export segment customers (requires segment_id)'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Export format (csv or xml)',
                'csv'
            );

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($input->getOption('all')) {
                return $this->refreshAllSegments($output);
            }

            $segmentIds = $input->getArgument('segment_id');

            if (empty($segmentIds)) {
                $output->writeln('<error>Please provide segment ID(s) or use --all option</error>');
                return Cli::RETURN_FAILURE;
            }

            if ($input->getOption('export')) {
                return $this->exportSegment($segmentIds[0], $input->getOption('format'), $output);
            }

            return $this->refreshSegments($segmentIds, $output);

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Refresh all segments
     *
     * @param OutputInterface $output
     * @return int
     */
    protected function refreshAllSegments(OutputInterface $output): int
    {
        $output->writeln('<info>Refreshing all active segments...</info>');

        $this->segmentManagement->refreshAllSegments();

        $output->writeln('<info>All segments refreshed successfully</info>');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Refresh specific segments
     *
     * @param array $segmentIds
     * @param OutputInterface $output
     * @return int
     */
    protected function refreshSegments(array $segmentIds, OutputInterface $output): int
    {
        $segmentIds = array_map('intval', $segmentIds);

        foreach ($segmentIds as $segmentId) {
            try {
                $segment = $this->segmentRepository->getById($segmentId);
                $output->writeln(sprintf('Refreshing segment: <comment>%s</comment>', $segment->getName()));

                $customerCount = $this->segmentManagement->refreshSegment($segmentId);

                $output->writeln(sprintf(
                    '  <info>✓</info> Assigned <comment>%d</comment> customers',
                    $customerCount
                ));
            } catch (\Exception $e) {
                $output->writeln(sprintf('  <error>✗ Error: %s</error>', $e->getMessage()));
            }
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Export segment customers
     *
     * @param int $segmentId
     * @param string $format
     * @param OutputInterface $output
     * @return int
     */
    protected function exportSegment(int $segmentId, string $format, OutputInterface $output): int
    {
        try {
            $segment = $this->segmentRepository->getById($segmentId);
            $output->writeln(sprintf('Exporting segment: <comment>%s</comment>', $segment->getName()));

            $data = $this->segmentManagement->exportSegmentCustomers($segmentId, $format);

            if (empty($data)) {
                $output->writeln('<warning>No customers to export</warning>');
                return Cli::RETURN_SUCCESS;
            }

            // Save to file
            $filename = sprintf('segment_%d_customers_%s.%s', 
                $segmentId, 
                date('Y-m-d_H-i-s'), 
                $format
            );
            $filepath = 'var/export/' . $filename;
            $fullPath = BP . '/' . $filepath;

            // Ensure directory exists
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, $data);

            $output->writeln(sprintf('<info>✓</info> Exported to: <comment>%s</comment>', $filepath));

            return Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Export failed: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }
}
