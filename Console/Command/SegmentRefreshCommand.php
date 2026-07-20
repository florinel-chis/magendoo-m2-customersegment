<?php
/**
 * Magendoo CustomerSegment - CLI segment refresh and export command
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Console\Command;

use Magendoo\CustomerSegment\Api\SegmentManagementInterface;
use Magendoo\CustomerSegment\Api\SegmentRepositoryInterface;
use Magendoo\CustomerSegment\Helper\Data as Helper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem;
use Magento\Framework\Stdlib\DateTime\DateTime;
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
     * Supported export formats
     */
    private const ALLOWED_EXPORT_FORMATS = ['csv', 'xml'];

    /**
     * @var SegmentManagementInterface
     */
    protected SegmentManagementInterface $segmentManagement;

    /**
     * @var SegmentRepositoryInterface
     */
    protected SegmentRepositoryInterface $segmentRepository;

    /**
     * @var Helper
     */
    protected Helper $helper;

    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @param SegmentManagementInterface $segmentManagement
     * @param SegmentRepositoryInterface $segmentRepository
     * @param Helper $helper
     * @param Filesystem $filesystem
     * @param DateTime $dateTime
     * @param string|null $name
     */
    public function __construct(
        SegmentManagementInterface $segmentManagement,
        SegmentRepositoryInterface $segmentRepository,
        Helper $helper,
        Filesystem $filesystem,
        DateTime $dateTime,
        ?string $name = null
    ) {
        $this->segmentManagement = $segmentManagement;
        $this->segmentRepository = $segmentRepository;
        $this->helper = $helper;
        $this->filesystem = $filesystem;
        $this->dateTime = $dateTime;
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
            if (!$this->helper->isEnabled()) {
                $output->writeln(
                    '<error>Customer Segment module is disabled '
                    . '(customersegment/general/enabled). Nothing to do.</error>'
                );
                return Cli::RETURN_FAILURE;
            }

            if ($input->getOption('all')) {
                return $this->refreshAllSegments($output);
            }

            $segmentIds = $input->getArgument('segment_id');

            if (empty($segmentIds)) {
                $output->writeln('<error>Please provide segment ID(s) or use --all option</error>');
                return Cli::RETURN_FAILURE;
            }

            if ($input->getOption('export')) {
                return $this->exportSegment(
                    (int) $segmentIds[0],
                    (string) $input->getOption('format'),
                    $output
                );
            }

            return $this->refreshSegments($segmentIds, $output);
        } catch (\Throwable $e) {
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
        $format = strtolower(trim($format));
        if (!in_array($format, self::ALLOWED_EXPORT_FORMATS, true)) {
            $output->writeln(sprintf(
                '<error>Invalid --format "%s". Supported formats: %s.</error>',
                $format,
                implode(', ', self::ALLOWED_EXPORT_FORMATS)
            ));
            return Cli::RETURN_FAILURE;
        }

        try {
            $segment = $this->segmentRepository->getById($segmentId);
            $output->writeln(sprintf('Exporting segment: <comment>%s</comment>', $segment->getName()));

            $data = $this->segmentManagement->exportSegmentCustomers($segmentId, $format);

            if (empty($data)) {
                $output->writeln('<warning>No customers to export</warning>');
                return Cli::RETURN_SUCCESS;
            }

            $filename = sprintf(
                'segment_%d_customers_%s.%s',
                $segmentId,
                $this->dateTime->gmtDate('Y-m-d_H-i-s'),
                $format
            );
            $relativePath = 'export/' . $filename;

            $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $varDir->create('export');
            $varDir->writeFile($relativePath, $data);

            $output->writeln(sprintf(
                '<info>Exported to:</info> <comment>%s</comment>',
                $varDir->getAbsolutePath($relativePath)
            ));

            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Export failed: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }
}
