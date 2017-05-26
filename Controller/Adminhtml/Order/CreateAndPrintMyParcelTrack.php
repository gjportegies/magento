<?php

namespace MyParcelNL\Magento\Controller\Adminhtml\Order;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use MyParcelNL\Magento\Model\Sales\MagentoOrderCollection;

/**
 * Action to create and print MyParcel Track
 *
 * If you want to add improvements, please create a fork in our GitHub:
 * https://github.com/myparcelnl
 *
 * @author      Reindert Vetter <reindert@myparcel.nl>
 * @copyright   2010-2017 MyParcel
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US  CC BY-NC-ND 3.0 NL
 * @link        https://github.com/myparcelnl/magento
 * @since       File available since Release v0.1.0
 */
class CreateAndPrintMyParcelTrack extends \Magento\Framework\App\Action\Action
{
    const PATH_MODEL_ORDER = 'Magento\Sales\Model\Order';
    const PATH_URI_ORDER_INDEX = 'sales/order/index';

    /**
     * @var MagentoOrderCollection
     */
    private $orderCollection;

    /**
     * CreateAndPrintMyParcelTrack constructor.
     *
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);

        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->orderCollection = new MagentoOrderCollection(
            $context->getObjectManager(),
            $this->getRequest(),
            null
        );
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $this->massAction();

        return $this->resultRedirectFactory->create()->setPath(self::PATH_URI_ORDER_INDEX);
    }

    /**
     * Get selected items and process them
     */
    private function massAction()
    {
        if ($this->getRequest()->getParam('selected_ids')) {
            $orderIds = explode(',', $this->getRequest()->getParam('selected_ids'));
        } else {
            $orderIds = $this->getRequest()->getParam('selected');
        }

        if (empty($orderIds)) {
            throw new LocalizedException(__('No items selected'));
        }

        $this->getRequest()->setParams(['myparcel_track_email' => true]);

        $this->addOrdersToCollection($orderIds);
        $this->orderCollection
            ->setOptionsFromParameters()
            ->setMagentoShipment();

        if (!$this->orderCollection->hasShipment()) {
            $this->messageManager->addErrorMessage(__(MagentoOrderCollection::ERROR_ORDER_HAS_NO_SHIPMENT));
            return;
        }

        $this->orderCollection->setMagentoTrack()
            ->setMyParcelTrack()
            ->createMyParcelConcepts()
            ->updateOrderGrid();

        if ($this->orderCollection->getOption('request_type') == 'only_shipment') {
            return;
        }

        if ($this->orderCollection->getOption('request_type') == 'download') {
            $this->orderCollection
                ->setPdfOfLabels()
                ->updateMagentoTrack()
                ->sendTrackEmails()
                ->downloadPdfOfLabels();
        }
    }

    /**
     * @param $orderIds int[]
     */
    private function addOrdersToCollection($orderIds)
    {
        /**
         * @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection
         */
        $collection = $this->_objectManager->get(MagentoOrderCollection::PATH_MODEL_ORDER);
        $collection->addAttributeToFilter('entity_id', ['in' => $orderIds]);
        $this->orderCollection->setOrderCollection($collection);
    }
}