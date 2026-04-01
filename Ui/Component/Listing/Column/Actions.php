<?php
/**
 * Magendoo CustomerSegment Actions Column
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

/**
 * Actions column for segment grid
 */
class Actions extends Column
{
    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $name = $this->getData('name');
                if (isset($item['segment_id'])) {
                    $item[$name]['edit'] = [
                        'href' => $this->urlBuilder->getUrl(
                            'customersegment/segment/edit',
                            ['segment_id' => $item['segment_id']]
                        ),
                        'label' => __('Edit'),
                        'hidden' => false,
                    ];
                    $item[$name]['refresh'] = [
                        'href' => $this->urlBuilder->getUrl(
                            'customersegment/segment/refresh',
                            ['segment_id' => $item['segment_id']]
                        ),
                        'label' => __('Refresh'),
                        'hidden' => false,
                        'confirm' => [
                            'title' => __('Refresh Segment'),
                            'message' => __('Are you sure you want to refresh this segment?')
                        ]
                    ];
                    $item[$name]['delete'] = [
                        'href' => $this->urlBuilder->getUrl(
                            'customersegment/segment/delete',
                            ['segment_id' => $item['segment_id']]
                        ),
                        'label' => __('Delete'),
                        'hidden' => false,
                        'confirm' => [
                            'title' => __('Delete Segment'),
                            'message' => __('Are you sure you want to delete this segment?')
                        ]
                    ];
                }
            }
        }

        return $dataSource;
    }
}
