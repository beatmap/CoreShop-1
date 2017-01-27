<?php
/**
 * CoreShop.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2017 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Model\Order;

use CoreShop\Exception\ObjectUnsupportedException;
use CoreShop\Model\Base;
use CoreShop\Model\Order;
use CoreShop\Model\Product;
use CoreShop\Model\Service;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset;
use Pimcore\Model\Object;

/**
 * Class Item
 * @package CoreShop\Model\Order
 *
 * @method static Object\Listing\Concrete getByProduct ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByWholesalePrice ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByRetailPrice ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByPrice ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByPriceWithoutTax ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByAmount ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByTotalTax ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByTotal ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByExtraInformation ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByIsGiftItem ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByTaxes ($value, $limit = 0)
 */
class Item extends Base
{
    /**
     * Pimcore Object Class.
     *
     * @var string
     */
    public static $pimcoreClass = 'Pimcore\\Model\\Object\\CoreShopOrderItem';

    /**
     * Calculate Total of OrderItem without tax.
     *
     * @return float
     */
    public function getTotalWithoutTax()
    {
        return $this->getAmount() * $this->getPriceWithoutTax();
    }

    /**
     * Calculate Total of OrderItem without tax.
     *
     * @return float
     */
    public function getBaseTotalWithoutTax()
    {
        return $this->getAmount() * $this->getBasePriceWithoutTax();
    }

    /**
     * Get Order for OrderItem.
     *
     * @return null|\Pimcore\Model\Object\AbstractObject
     */
    public function getOrder()
    {
        $order = Service::getParentOfType($this, Order::class);

        return $order instanceof Order ? $order : null;
    }

    /**
     * @param string $type
     *
     * @return int
     */
    public function getProcessedAmountForType($type)
    {
        $order = $this->getOrder();

        $amount = 0;

        if ($order instanceof Order) {
            $documents = $order->getDocumentsForType($type);

            foreach ($documents as $document) {
                foreach ($document->getItems() as $item) {
                    if ($item instanceof Document\Item) {
                        if ($item->getOrderItem()->getId() === $this->getId()) {
                            $amount += $item->getAmount();
                        }
                    }
                }
            }
        }

        return $amount;
    }

    /**
     * calculates the invoiced amount
     *
     * @return int
     */
    public function getInvoicedAmount()
    {
        return $this->getProcessedAmountForType(Invoice::getDocumentType());
    }

    /**
     * calculates the shipped amount
     *
     * @return int
     */
    public function getShippedAmount()
    {
        return $this->getProcessedAmountForType(Shipment::getDocumentType());
    }

    /**
     * @return string|null Product Name
     */
    public function getProductName()
    {
        if ($this->getProduct() instanceof Product) {
            return $this->getProduct()->getName();
        }

        return null;
    }

    /**
     * @return \Pimcore\Model\Asset|null
     */
    public function getProductImage()
    {
        if ($this->getProduct() instanceof Product) {
            if ($this->getProduct()->getImage() instanceof Image) {
                return $this->getProduct()->getImage();
            };
        }

        return null;
    }

    /**
     * @return Product
     *
     * @throws ObjectUnsupportedException
     */
    public function getProduct()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Product $product
     *
     * @throws ObjectUnsupportedException
     */
    public function setProduct($product)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return int
     *
     * @throws ObjectUnsupportedException
     */
    public function getAmount()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param int $amount
     *
     * @throws ObjectUnsupportedException
     */
    public function setAmount($amount)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getWholesalePrice()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $wholesalePrice
     *
     * @throws ObjectUnsupportedException
     */
    public function setWholesalePrice($wholesalePrice)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getRetailPrice()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $retailPrice
     *
     * @throws ObjectUnsupportedException
     */
    public function setRetailPrice($retailPrice)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $baseRetailPrice
     *
     * @throws ObjectUnsupportedException
     */
    public function setBaseRetailPrice($baseRetailPrice)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getBaseRetailPrice()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getPrice()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $price
     *
     * @throws ObjectUnsupportedException
     */
    public function setPrice($price)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getBasePrice()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $basePrice
     *
     * @throws ObjectUnsupportedException
     */
    public function setBasePrice($basePrice)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getPriceWithoutTax()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $priceWithoutTax
     *
     * @throws ObjectUnsupportedException
     */
    public function setPriceWithoutTax($priceWithoutTax)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getBasePriceWithoutTax()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $basePriceWithoutTax
     *
     * @throws ObjectUnsupportedException
     */
    public function setBasePriceWithoutTax($basePriceWithoutTax)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getTotalTax()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $totalTax
     *
     * @throws ObjectUnsupportedException
     */
    public function setTotalTax($totalTax)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getBaseTotalTax()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $baseTotalTax
     *
     * @throws ObjectUnsupportedException
     */
    public function setBaseTotalTax($baseTotalTax)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getTotal()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $total
     *
     * @throws ObjectUnsupportedException
     */
    public function setTotal($total)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return double
     *
     * @throws ObjectUnsupportedException
     */
    public function getBaseTotal()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param double $baseTotal
     *
     * @throws ObjectUnsupportedException
     */
    public function setBaseTotal($baseTotal)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getExtraInformation()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $extraInformation
     *
     * @throws ObjectUnsupportedException
     */
    public function setExtraInformation($extraInformation)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return boolean
     *
     * @throws ObjectUnsupportedException
     */
    public function getIsGiftItem()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param boolean $isGiftItem
     *
     * @throws ObjectUnsupportedException
     */
    public function setIsGiftItem($isGiftItem)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getTaxes()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $taxes
     *
     * @throws ObjectUnsupportedException
     */
    public function setTaxes($taxes)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getBaseTaxes()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $baseTaxes
     *
     * @throws ObjectUnsupportedException
     */
    public function setBaseTaxes($baseTaxes)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return boolean
     *
     * @throws ObjectUnsupportedException
     */
    public function getIsVirtualProduct()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param boolean $isVirtualProduct
     *
     * @throws ObjectUnsupportedException
     */
    public function setIsVirtualProduct($isVirtualProduct)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Asset
     *
     * @throws ObjectUnsupportedException
     */
    public function getVirtualAsset()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Asset|null $virtualAsset
     *
     * @throws ObjectUnsupportedException
     */
    public function setVirtualAsset($virtualAsset)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }
}
