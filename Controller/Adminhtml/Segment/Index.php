<?php
/**
 * Magendoo CustomerSegment Segment Index Controller
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Controller\Adminhtml\Segment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Segment grid controller
 */
class Index extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'Magendoo_CustomerSegment::segments';

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Magendoo_CustomerSegment::segments');
        $resultPage->addBreadcrumb(__('Customer Segments'), __('Customer Segments'));
        $resultPage->addBreadcrumb(__('Manage Segments'), __('Manage Segments'));
        $resultPage->getConfig()->getTitle()->prepend(__('Customer Segments'));

        return $resultPage;
    }
}
