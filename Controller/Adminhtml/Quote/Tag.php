<?php

namespace MelhorEnvio\Quote\Controller\Adminhtml\Quote;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use MelhorEnvio\Quote\Api\ShippingManagementInterface;
use MelhorEnvio\Quote\Controller\Adminhtml\BaseController;
use MelhorEnvio\Quote\Model\Services\TagGenerateFactory;
use MelhorEnvio\Quote\Model\Services\TagPreviewFactory;

/**
 * Class Tag
 * @package MelhorEnvio\Quote\Controller\Adminhtml\Quote
 */
class Tag extends BaseController
{
    /**
     * @var TagPreviewFactory
     */
    private $tagPreviewFactory;
    /**
     * @var TagGenerateFactory
     */
    private $tagGenerateFactory;

    private $shippingManagement;

    /**
     * Constructor
     *
     * @param Context $context
     * @param TagPreviewFactory $tagPreviewFactory
     * @param TagGenerateFactory $tagGenerateFactory
     * @param ShippingManagementInterface $shippingManagement
     */
    public function __construct(
        Context $context,
        TagPreviewFactory $tagPreviewFactory,
        TagGenerateFactory $tagGenerateFactory,
        ShippingManagementInterface $shippingManagement
    ) {
        parent::__construct($context);
        $this->tagPreviewFactory = $tagPreviewFactory;
        $this->tagGenerateFactory = $tagGenerateFactory;
        $this->shippingManagement = $shippingManagement;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|mixed
     */
    public function execute()
    {
        $shippingId = $this->getRequest()->getParam('quote_id');
        $postcode = $this->getRequest()->getParam('origin');

        if (
            !$this->getRequest()->getParam('quote_id')
            || !$this->getRequest()->getParam('action')
        ) {
            return $this->redirectWithError(__('Não foi possível encontrar a etiqueta'));
        }

        try {
            $this->shippingManagement->generateTags($shippingId);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage(
                __('Nao foi possivel gerar a etiqueta do pedido %1 ', $shippingId)
            );
        }
        return $this->redirect();
    }
}
