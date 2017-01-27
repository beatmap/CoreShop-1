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

namespace CoreShop\Model\Cart;

use CoreShop\Exception\ObjectUnsupportedException;
use CoreShop\Model\Base;
use CoreShop\Model\Cart;
use CoreShop\Model\Product;
use CoreShop\Model\Service;
use CoreShop\Model\Tax;
use CoreShop\Model\TaxCalculator;
use CoreShop\Model\User\Address;
use Pimcore\Model\Asset;
use Pimcore\Model\Object;

/**
 * Class Item
 * @package CoreShop\Model\Cart
 *
 * @method static Object\Listing\Concrete getByAmount ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByProduct ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByExtraInformation ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByIsGiftItem ($value, $limit = 0)
 */
class Item extends Base
{
    /**
     * Pimcore Object Class.
     *
     * @var string
     */
    public static $pimcoreClass = 'Pimcore\\Model\\Object\\CoreShopCartItem';

    /**
     * Calculates the total for the CartItem.
     *
     * @param $withTax boolean
     *
     * @return mixed
     */
    public function getBaseTotal($withTax = true) {
        return $this->getAmount() * $this->getBaseProductPrice($withTax);
    }

    /**
     * Calculates the total for the CartItem.
     *
     * @param $withTax boolean
     *
     * @return mixed
     */
    public function getTotal($withTax = true)
    {
        return $this->getAmount() * $this->getProductPrice($withTax);
    }

    /**
     * Get Cart Item Weight
     *
     * @return int
     */
    public function getWeight()
    {
        return $this->getAmount() * $this->getProduct()->getWeight();
    }

    /**
     * Get Cart Item is downloadable
     *
     * @return bool
     */
    public function getIsVirtualProduct()
    {
        return $this->getProduct()->getisVirtualProduct();
    }

    /**
     * Get CartItem Virtual Asset
     *
     * @return Asset
     */
    public function getVirtualAsset()
    {
        return $this->getProduct()->getVirtualAsset();
    }

    /**
     * Get Product Price without currency conversion
     *
     * @param bool $withTax
     * @return float|mixed
     */
    public function getBaseProductPrice($withTax = true) {
        return $this->getProduct()->getPrice($withTax);
    }

    /**
     * Get Product Price
     *
     * @param bool $withTax
     * @return float|mixed
     */
    public function getProductPrice($withTax = true)
    {
        return $this->convertToCurrency($this->getProduct()->getPrice($withTax));
    }

    /**
     * Get Product Sales Price
     *
     * @param $withTax
     * @return float
     */
    public function getBaseProductSalesPrice($withTax)
    {
        return $this->getProduct()->getSalesPrice($withTax);
    }

    /**
     * Get Product Sales Price
     *
     * @param $withTax
     * @return float
     */
    public function getProductSalesPrice($withTax)
    {
        return $this->convertToCurrency($this->getBaseProductSalesPrice($withTax));
    }

    /**
     * Get Products Wholesale Price
     *
     * @return float
     */
    public function getBaseProductWholesalePrice()
    {
        return $this->getProduct()->getWholesalePrice();
    }

    /**
     * Get Products Wholesale Price
     *
     * @return float
     */
    public function getProductWholesalePrice()
    {
        return $this->convertToCurrency($this->getBaseProductWholesalePrice());
    }

    /**
     * Get Products Retail Price
     *
     * @return float
     */
    public function getBaseProductRetailPrice()
    {
        return $this->getProduct()->getRetailPrice();
    }

    /**
     * Get Products Retail Price
     *
     * @return float
     */
    public function getProductRetailPrice()
    {
        return $this->convertToCurrency($this->getBaseProductRetailPrice());
    }

    /**
     * @return float
     */
    public function getBaseProductRetailPriceWithTax()
    {
        return $this->getProduct()->getRetailPriceWithTax();
    }

    /**
     * @return float
     */
    public function getProductRetailPriceWithTax()
    {
        return $this->convertToCurrency($this->getBaseProductRetailPriceWithTax());
    }

    /**
     * @return float
     */
    public function getBaseProductRetailPriceWithoutTax()
    {
        return $this->getProduct()->getRetailPriceWithoutTax();
    }

    /**
     * @return float
     */
    public function getProductRetailPriceWithoutTax()
    {
        return $this->convertToCurrency($this->getBaseProductRetailPriceWithoutTax());
    }

    /**
     * get total tax for product in item
     *
     * @return float
     */
    public function getBaseTotalProductTax()
    {
        return $this->getAmount() * $this->getBaseProductTaxAmount(false);
    }

    /**
     * get total tax for product in item
     *
     * @return float
     */
    public function getTotalProductTax()
    {
        return $this->convertToCurrency($this->getBaseTotalProductTax());
    }

    /**
     * Get Single Item Tax for Cart Item
     *
     * @return float
     */
    public function getBaseItemTax()
    {
        return $this->getBaseProductTaxAmount(false) * $this->getCart()->getDiscountPercentage();
    }

    /**
     * Get Single Item Tax for Cart Item
     *
     * @return float
     */
    public function getItemTax()
    {
        return $this->convertToCurrency($this->getBaseItemTax());
    }

    /**
     * Get Tax Amount for Cart Item
     *
     * @return float
     */
    public function getBaseTotalTax()
    {
        return ($this->getAmount() * $this->getBaseItemTax());
    }
    
    /**
     * Get Tax Amount for Cart Item
     *
     * @return float
     */
    public function getTotalTax()
    {
        return $this->convertToCurrency($this->getBaseTotalTax());
    }

    /**
     * Returns array with key=>value for tax and value.
     *
     * @param $applyDiscountToTaxValues
     *
     * @return array
     */
    public function getBaseTaxes($applyDiscountToTaxValues = true) {
        return $this->collectTaxes($applyDiscountToTaxValues, true);
    }

    /**
     * Returns array with key=>value for tax and value.
     *
     * @param $applyDiscountToTaxValues
     *
     * @return array
     */
    public function getTaxes($applyDiscountToTaxValues = true)
    {
        return $this->collectTaxes($applyDiscountToTaxValues, false);
    }

    /**
     * @param bool $applyDiscountToTaxValues
     * @param bool $basePrice
     * @return array
     */
    private function collectTaxes($applyDiscountToTaxValues = true, $basePrice = true) {
        $usedTaxes = [];

        $discountPercentage = $this->getCart()->getDiscountPercentage();

        $addTax = function (Tax $tax) use (&$usedTaxes) {
            if (!array_key_exists($tax->getId(), $usedTaxes)) {
                $usedTaxes[$tax->getId()] = [
                    'tax' => $tax,
                    'amount' => 0,
                ];
            }
        };

        $taxCalculator = $this->getProductTaxCalculator();

        if ($taxCalculator instanceof TaxCalculator) {
            $taxes = $taxCalculator->getTaxes();

            foreach ($taxes as $tax) {
                $addTax($tax);
            }

            $itemTotal = $basePrice ? $this->getBaseTotal(false) : $this->getTotal(false);
            $taxesAmount = $taxCalculator->getTaxesAmount($itemTotal, true);

            if (is_array($taxesAmount)) {
                foreach ($taxesAmount as $id => $amount) {
                    if ($applyDiscountToTaxValues) {
                        $amount *= $discountPercentage;
                    }

                    $usedTaxes[$id]['amount'] += $amount;
                }
            }
        }

        return $usedTaxes;
    }

    /**
     * @param bool $asArray
     * @return array|float
     */
    public function getBaseProductTaxAmount($asArray = false)
    {
        if ($asArray) {
            return $this->getProduct()->getBaseTaxAmount($asArray);
        }

        return $this->convertToCurrency($this->getProduct()->getBaseTaxAmount($asArray));
    }

    /**
     * @param bool $asArray
     * @return array|float
     */
    public function getProductTaxAmount($asArray = false)
    {
        if ($asArray) {
            return $this->getProduct()->getTaxAmount($asArray);
        }

        return $this->convertToCurrency($this->getProduct()->getTaxAmount($asArray));
    }

    /**
     * Get Products Tax Calculator
     *
     * @param Address|null $address
     * @return bool|\CoreShop\Model\TaxCalculator
     */
    public function getProductTaxCalculator(Address $address = null)
    {
        return $this->getProduct()->getTaxCalculator($address);
    }


    /**
     * Get the Cart for this CartItem.
     *
     * @return Cart
     */
    public function getCart()
    {
        $cart = Service::getParentOfType($this, Cart::class);

        return $cart instanceof Cart ? $cart : null;
    }

    /**
     * Convert Value to Carts - Currency
     *
     * @param $price
     * @return mixed
     */
    public function convertToCurrency($price)
    {
        if ($this->getCart() instanceof Cart) {
            return $this->getCart()->convertToCurrency($price);
        }

        return $price;
    }
    
    /**
     * @return int
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
}
