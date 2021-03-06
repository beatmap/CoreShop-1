<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2017 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
*/

namespace CoreShop\Bundle\OrderBundle\Transformer;

use Carbon\Carbon;
use CoreShop\Component\Resource\Factory\FactoryInterface;
use CoreShop\Component\Resource\Pimcore\ObjectServiceInterface;
use CoreShop\Component\Order\Model\CartInterface;
use CoreShop\Component\Order\Model\CartItemInterface;
use CoreShop\Component\Order\Model\OrderDocumentInterface;
use CoreShop\Component\Order\Model\OrderInterface;
use CoreShop\Component\Order\Model\OrderInvoiceInterface;
use CoreShop\Component\Order\Model\OrderInvoiceItemInterface;
use CoreShop\Component\Order\Model\OrderItemInterface;
use CoreShop\Component\Order\NumberGenerator\NumberGeneratorInterface;
use CoreShop\Component\Order\Repository\OrderInvoiceRepositoryInterface;
use CoreShop\Component\Order\Transformer\OrderDocumentItemTransformerInterface;
use CoreShop\Component\Order\Transformer\OrderDocumentTransformerInterface;
use CoreShop\Component\Resource\Factory\PimcoreFactoryInterface;
use CoreShop\Component\Resource\Repository\PimcoreRepositoryInterface;
use CoreShop\Component\Resource\Transformer\ItemKeyTransformerInterface;
use CoreShop\Component\Taxation\Model\TaxItemInterface;
use Pimcore\Model\Object\Fieldcollection;
use Webmozart\Assert\Assert;

class OrderToInvoiceTransformer implements OrderDocumentTransformerInterface
{
    /**
     * @var OrderDocumentItemTransformerInterface
     */
    protected $orderItemToInvoiceItemTransformer;

    /**
     * @var ItemKeyTransformerInterface
     */
    protected $keyTransformer;

    /**
     * @var NumberGeneratorInterface
     */
    protected $numberGenerator;

    /**
     * @var string
     */
    protected $invoiceFolderPath;

    /**
     * @var ObjectServiceInterface
     */
    protected $objectService;

    /**
     * @var PimcoreRepositoryInterface
     */
    protected $orderItemRepository;

    /**
     * @var PimcoreFactoryInterface
     */
    protected $invoiceItemFactory;

    /**
     * @var OrderInvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var TransformerEventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var FactoryInterface
     */
    protected $taxItemFactory;

    /**
     * @param OrderDocumentItemTransformerInterface $orderDocumentItemTransformer
     * @param ItemKeyTransformerInterface           $keyTransformer
     * @param NumberGeneratorInterface              $numberGenerator
     * @param string                                $invoiceFolderPath
     * @param ObjectServiceInterface                $objectService
     * @param PimcoreRepositoryInterface            $orderItemRepository
     * @param PimcoreFactoryInterface               $invoiceItemFactory
     * @param OrderInvoiceRepositoryInterface       $invoiceRepository
     * @param TransformerEventDispatcherInterface   $eventDispatcher
     * @param FactoryInterface                      $taxItemFactory
     */
    public function __construct(
        OrderDocumentItemTransformerInterface $orderDocumentItemTransformer,
        ItemKeyTransformerInterface $keyTransformer,
        NumberGeneratorInterface $numberGenerator,
        $invoiceFolderPath,
        ObjectServiceInterface $objectService,
        PimcoreRepositoryInterface $orderItemRepository,
        PimcoreFactoryInterface $invoiceItemFactory,
        OrderInvoiceRepositoryInterface $invoiceRepository,
        TransformerEventDispatcherInterface $eventDispatcher,
        FactoryInterface $taxItemFactory
    ) {
        $this->orderItemToInvoiceItemTransformer = $orderDocumentItemTransformer;
        $this->keyTransformer = $keyTransformer;
        $this->numberGenerator = $numberGenerator;
        $this->invoiceFolderPath = $invoiceFolderPath;
        $this->objectService = $objectService;
        $this->orderItemRepository = $orderItemRepository;
        $this->invoiceItemFactory = $invoiceItemFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->taxItemFactory = $taxItemFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(OrderInterface $order, OrderDocumentInterface $invoice, $itemsToTransform)
    {
        /*
         * @var $cart CartInterface
         */
        Assert::isInstanceOf($order, OrderInterface::class);
        Assert::isInstanceOf($invoice, OrderInvoiceInterface::class);

        $this->eventDispatcher->dispatchPreEvent('invoice', $invoice, ['order' => $order, 'items' => $itemsToTransform]);

        $invoiceFolder = $this->objectService->createFolderByPath(sprintf('%s/%s', $order->getFullPath(), $this->invoiceFolderPath));

        $invoiceNumber = $this->numberGenerator->generate($invoice);
        /*
         * @var $invoice OrderInvoiceInterface
         * @var $order OrderInterface
         */
        $invoice->setKey($this->keyTransformer->transform($invoiceNumber));
        $invoice->setInvoiceNumber($invoiceNumber);
        $invoice->setParent($invoiceFolder);
        $invoice->setPublished(true);
        $invoice->setInvoiceDate(Carbon::now());
        $invoice->setOrder($order);

        /*
         * We need to save the order twice in order to create the object in the tree for pimcore
         */
        $invoice->save();
        $items = [];

        /*
         * @var $cartItem CartItemInterface
         */
        foreach ($itemsToTransform as $item) {
            $invoiceItem = $this->invoiceItemFactory->createNew();
            $orderItem = $this->orderItemRepository->find($item['orderItemId']);
            $quantity = $item['quantity'];

            if ($orderItem instanceof OrderItemInterface) {
                $items[] = $this->orderItemToInvoiceItemTransformer->transform($invoice, $orderItem, $invoiceItem, $quantity);
            }
        }

        $invoice->setItems($items);
        $invoice->save();

        $this->calculateInvoice($invoice);

        $this->eventDispatcher->dispatchPostEvent('invoice', $invoice, ['order' => $order, 'items' => $itemsToTransform]);

        return $invoice;
    }

    /**
     * @param OrderInvoiceInterface $invoice
     */
    private function calculateInvoice(OrderInvoiceInterface $invoice)
    {
        $this->calculateSubtotal($invoice);
        $this->calculateShipping($invoice);
        $this->calculatePaymentFees($invoice);
        $this->calculateDiscount($invoice);
        $this->calculateTotal($invoice);

        $invoice->save();
    }

    /**
     * @param OrderInvoiceInterface $invoice
     */
    private function calculateSubtotal(OrderInvoiceInterface $invoice)
    {
        $discountPercentage = $invoice->getOrder()->getDiscountPercentage();

        $subtotalWithTax = 0;
        $subtotalWithoutTax = 0;
        $subtotalTax = 0;

        /**
         * @var $item OrderInvoiceItemInterface
         */
        foreach ($invoice->getItems() as $item) {
            $subtotalWithTax += $item->getTotal();
            $subtotalWithoutTax += $item->getTotal(false);
            $subtotalTax += $item->getTotalTax();

            foreach ($item->getTaxes() as $tax) {
                if ($tax instanceof TaxItemInterface) {
                    $this->addTax($invoice, $tax->getName(), $tax->getRate(), $tax->getAmount() * $discountPercentage);
                }
            }
        }

        $invoice->setSubtotal($subtotalWithTax);
        $invoice->setSubtotal($subtotalWithoutTax, false);
        $invoice->setSubtotalTax($subtotalTax);
    }

    /**
     * Calculate Shipping Prices for invoices.
     *
     * @param OrderInvoiceInterface $invoice
     */
    private function calculateShipping(OrderInvoiceInterface $invoice)
    {
        $shippingWithTax = 0;
        $shippingWithoutTax = 0;
        $shippingTax = 0;

        $totalShipping = $invoice->getOrder()->getShipping();
        $totalShippingWT = $invoice->getOrder()->getShipping(false);
        $invoicedShipping = $this->getProcessedValue('shippingGross', $invoice->getOrder());
        $invoicedShippingWT = $this->getProcessedValue('shippingNet', $invoice->getOrder());

        if ($totalShipping - $invoicedShipping > 0) {
            $shippingTaxRate = $invoice->getOrder()->getShippingTaxRate();

            $shippingWithTax = $totalShipping - $invoicedShipping;
            $shippingWithoutTax = $totalShippingWT - $invoicedShippingWT;
            $shippingTax = $shippingWithTax - $shippingWithoutTax;

            $this->addTax($invoice, 'shipping', $shippingTaxRate, $shippingTax);
        }

        $invoice->setShipping($shippingWithTax);
        $invoice->setShipping($shippingWithoutTax, false);
        $invoice->setShippingTax($shippingTax);
        $invoice->setShippingTaxRate($invoice->getOrder()->getShippingTaxRate());
    }

    /**
     * Calculate Payment Fees for Invoice.
     *
     * @param OrderInvoiceInterface $invoice
     */
    private function calculatePaymentFees(OrderInvoiceInterface $invoice)
    {
        $paymentFeeWithTax = 0;
        $paymentFeeWithoutTax = 0;
        $paymentFeeTax = 0;

        $totalPaymentFee = $invoice->getOrder()->getPaymentFee();
        $invoicedPaymentFees = $this->getProcessedValue('paymentFeeGross', $invoice->getOrder());
        $invoicedPaymentFeesWT = $this->getProcessedValue('paymentFeeNet', $invoice->getOrder());

        if ($totalPaymentFee - $invoicedPaymentFees > 0) {
            $paymentFeeTaxRate = $invoice->getOrder()->getPaymentFeeTaxRate();

            $paymentFeeWithTax = $totalPaymentFee - $invoicedPaymentFees;
            $paymentFeeWithoutTax = $paymentFeeWithTax - $invoicedPaymentFeesWT;
            $paymentFeeTax = $paymentFeeWithTax - $paymentFeeWithoutTax;

            $this->addTax($invoice, 'payment', $paymentFeeTaxRate, $paymentFeeTax);
        }

        $invoice->setPaymentFee($paymentFeeWithTax);
        $invoice->setPaymentFee($paymentFeeWithoutTax, false);
        $invoice->setPaymentFeeTax($paymentFeeTax);
        $invoice->setPaymentFeeTaxRate($invoice->getShippingTaxRate());
    }

    /**
     * Calculate Discount for Invoice.
     *
     * @param OrderInvoiceInterface $invoice
     */
    private function calculateDiscount(OrderInvoiceInterface $invoice)
    {
        $discountWithTax = 0;
        $discountWithoutTax = 0;
        $discountTax = 0;

        $totalDiscount = $invoice->getOrder()->getDiscount();
        $invoicedDiscount = $this->getProcessedValue('discount', $invoice->getOrder());

        if ($totalDiscount - $invoicedDiscount > 0) {
            $discountWithTax = $totalDiscount - $invoicedDiscount;
            $discountWithoutTax = $invoice->getOrder()->getDiscount(false) - $this->getProcessedValue('discountNet', $invoice->getOrder());
            $discountTax = $discountWithTax - $discountWithoutTax;
        }

        $invoice->setDiscount($discountWithTax);
        $invoice->setDiscount($discountWithoutTax, false);
        $invoice->setDiscountTax($discountTax);
    }

    /**
     * Calculate Total for invoice.
     *
     * @param OrderInvoiceInterface $invoice
     */
    private function calculateTotal(OrderInvoiceInterface $invoice)
    {
        $subtotalTax = $invoice->getSubtotalTax();
        $shippingTax = $invoice->getShippingTax();
        $paymentFeeTax = $invoice->getPaymentFeeTax();
        $discountTax = $invoice->getDiscountTax();

        $subtotalWithTax = $invoice->getSubtotal();
        $shippingWithTax = $invoice->getShipping();
        $paymentFeeWithTax = $invoice->getPaymentFee();
        $discountWithTax = $invoice->getDiscount();

        $subtotalWithoutTax = $invoice->getSubtotal(false);
        $shippingWithoutTax = $invoice->getShipping(false);
        $paymentFeeWithoutTax = $invoice->getPaymentFee(false);
        $discountWithoutTax = $invoice->getDiscount(false);

        $totalTax = ($subtotalTax + $shippingTax + $paymentFeeTax) - $discountTax;
        $total = ($subtotalWithTax + $shippingWithTax + $paymentFeeWithTax) - $discountWithTax;
        $totalWithoutTax = ($subtotalWithoutTax + $shippingWithoutTax + $paymentFeeWithoutTax) - $discountWithoutTax;

        $invoice->setTotalTax($totalTax);
        $invoice->setTotal($total);
        $invoice->setTotal($totalWithoutTax, false);
    }

    /**
     * @param string         $field
     * @param OrderInterface $order
     *
     * @return float
     */
    private function getProcessedValue($field, OrderInterface $order)
    {
        $invoices = $this->invoiceRepository->getDocuments($order);
        $processedValue = 0;

        foreach ($invoices as $invoice) {
            $processedValue += $invoice->getValueForFieldName($field);
        }

        return $processedValue;
    }

    /**
     * @param OrderInvoiceInterface $invoice
     * @param $name
     * @param $rate
     * @param $amount
     */
    private function addTax(OrderInvoiceInterface $invoice, $name, $rate, $amount)
    {
        $taxes = $invoice->getTaxes();

        if (!$taxes instanceof Fieldcollection) {
            $taxes = new Fieldcollection();
        }

        $found = false;

        foreach ($taxes as $tax) {
            if ($tax instanceof TaxItemInterface) {
                if ($tax->getName() === $name) {
                    $tax->setAmount($tax->getAmount() + $amount);
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            /**
             * @var $taxItem TaxItemInterface
             */
            $taxItem = $this->taxItemFactory->createNew();
            $taxItem->setName($name);
            $taxItem->setRate($rate);
            $taxItem->setAmount($amount);

            $taxes->add($taxItem);
            $invoice->setTaxes($taxes);
        }
    }
}
