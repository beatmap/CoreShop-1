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

namespace CoreShop\Model;

use Carbon\Carbon;
use CoreShop\Exception;
use CoreShop\Exception\ObjectUnsupportedException;
use CoreShop\Model\Cart\Item;
use CoreShop\Model\Plugin\Payment as PaymentPlugin;
use CoreShop\Model\Plugin\Payment;
use CoreShop\Model\PriceRule\Action\FreeShipping;
use CoreShop\Model\User\Address;
use CoreShop\Model\Cart\PriceRule;
use Pimcore\Date;
use Pimcore\Logger;
use Pimcore\Model\Object\Fieldcollection;
use Pimcore\Model\Object\Service as ObjectService;
use CoreShop\Maintenance\CleanUpCart;
use Pimcore\Model\Object\Listing;

/**
 * Class Cart
 * @package CoreShop\Model
 *
 * @method static Listing\Concrete getByItems ($value, $limit = 0)
 * @method static Listing\Concrete getByCarrier ($value, $limit = 0)
 * @method static Listing\Concrete getByPriceRule ($value, $limit = 0)
 * @method static Listing\Concrete getByCustomIdentifier ($value, $limit = 0)
 * @method static Listing\Concrete getByOrder ($value, $limit = 0)
 * @method static Listing\Concrete getByPaymentModule ($value, $limit = 0)
 * @method static Listing\Concrete getByShop ($value, $limit = 0)
 * @method static Listing\Concrete getByUser ($value, $limit = 0)
 * @method static Listing\Concrete getByShippingAddress ($value, $limit = 0)
 * @method static Listing\Concrete getByBillingAddress ($value, $limit = 0)
 */
class Cart extends Base
{
    /**
     * Pimcore Object Class.
     *
     * @var string
     */
    public static $pimcoreClass = 'Pimcore\\Model\\Object\\CoreShopCart';

    /**
     * @var float shipping costs
     */
    protected $shipping;

    /**
     * @var float shipping without tax
     */
    protected $shippingWithoutTax;

    /**
     * Get all existing Carts.
     *
     * @return array Cart
     */
    public static function getAll()
    {
        $list = self::getList();

        return $list->load();
    }

    /**
     * Prepare a Cart.
     *
     * @param bool $persist
     * @param string $name
     *
     * @return Cart
     *
     * @throws Exception
     */
    public static function prepare($persist = false, $name = 'default')
    {
        $cart = \CoreShop::getTools()->getCartManager()->getSessionCart();

        if (!$cart instanceof Cart) {
            $cart = \CoreShop::getTools()->getCartManager()->createCart($name, \CoreShop::getTools()->getUser(), Shop::getShop());
        }

        if ($persist) {
            \CoreShop::getTools()->getCartManager()->persistCart($cart);
        }

        return $cart;
    }

    /**
     * Check if Cart has any physical items.
     *
     * @return bool
     */
    public function hasPhysicalItems()
    {
        foreach ($this->getItems() as $item) {
            if (!$item->getIsVirtualProduct()) {
                return true;
            }
        }

        return false;
    }

    /**
     * calculates discount for the cart.
     *
     * @param boolean $withTax
     *
     * @return int
     */
    public function getDiscount($withTax = true)
    {
        return $this->convertToCurrency($this->getBaseDiscount($withTax));
    }

    /**
     * calculates discount for the cart, without currency conversion.
     *
     * @param boolean $withTax
     *
     * @return int
     */
    public function getBaseDiscount($withTax = true) {
        $priceRule = $this->getPriceRules();
        $discount = 0;

        foreach ($priceRule as $ruleItem) {
            if ($ruleItem instanceof \CoreShop\Model\PriceRule\Item) {
                $rule = $ruleItem->getPriceRule();

                if ($rule instanceof PriceRule) {
                    $discount += $rule->getDiscount($this, $withTax);
                }
            }
        }

        return $discount;
    }

    /**
     * calculates discount percentage for cart
     *
     * @return float
     */
    public function getDiscountPercentage()
    {
        $totalWithoutDiscount = $this->getSubtotal(false);
        $totalWithDiscount = $this->getSubtotal(false) - $this->getDiscount(false);

        return ((100 / $totalWithoutDiscount) * $totalWithDiscount) / 100;
    }

    /**
     * calculates the discount tax
     *
     * @return number
     */
    public function getDiscountTax()
    {
        return abs($this->getDiscount(true) - $this->getDiscount(false));
    }

    /**
     * calculates the discount tax, without currency conversion
     *
     * @return number
     */
    public function getBaseDiscountTax()
    {
        return abs($this->getBaseDiscount(true) - $this->getBaseDiscount(false));
    }

    /**
     * calculates the subtotal for the cart.
     *
     * @param bool $withTax
     *
     * @return float
     */
    public function getSubtotal($withTax = true)
    {
        return $this->convertToCurrency($this->getBaseSubtotal($withTax));
    }

    /**
     * calculates the subtotal for the cart, without currency conversion
     *
     * @param bool $withTax
     *
     * @return float
     */
    public function getBaseSubtotal($withTax = true)
    {
        $subtotal = 0;

        foreach ($this->getItems() as $item) {
            $subtotal += $item->getBaseTotal($withTax);
        }

        return $subtotal;
    }

    /**
     * calculates the subtotal tax for the cart.
     *
     * @return float
     */
    public function getSubtotalTax()
    {
        $subtotalTi = $this->getSubtotal(true);
        $subtotalTe = $this->getSubtotal(false);

        return abs($subtotalTi - $subtotalTe);
    }

    /**
     * calculates the subtotal tax for the cart, without currency conversion.
     *
     * @return float
     */
    public function getBaseSubtotalTax()
    {
        $subtotalTi = $this->getBaseSubtotal(true);
        $subtotalTe = $this->getBaseSubtotal(false);

        return abs($subtotalTi - $subtotalTe);
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
     * Returns array with key=>value for tax and value, without currency conversion
     *
     * @param $applyDiscountToTaxValues
     *
     * @return array
     */
    public function getBaseTaxes($applyDiscountToTaxValues = true)
    {
        return $this->collectTaxes($applyDiscountToTaxValues, true);
    }

    /**
     * Returns array with key=>value for tax and value.
     *
     * @param $applyDiscountToTaxValues
     * @param $baseCurrency
     *
     * @return array
     */
    public function collectTaxes($applyDiscountToTaxValues = true, $baseCurrency = false)
    {
        $usedTaxes = [];

        $addTax = function (Tax $tax, $amount) use (&$usedTaxes) {
            if ($amount > 0) {
                if (!array_key_exists($tax->getId(), $usedTaxes)) {
                    $usedTaxes[$tax->getId()] = [
                        'tax' => $tax,
                        'amount' => $amount,
                    ];
                } else {
                    $usedTaxes[$tax->getId()]['amount'] += $amount;
                }
            }
        };

        foreach ($this->getItems() as $item) {
            $itemTaxes = $baseCurrency ? $item->getBaseTaxes($applyDiscountToTaxValues) : $item->getTaxes($applyDiscountToTaxValues);

            foreach ($itemTaxes as $itemTax) {
                $addTax($itemTax['tax'], $itemTax['amount']);
            }
        }

        if (!$this->getFreeShipping()) {
            $shippingProvider = $this->getShippingProvider();

            if ($shippingProvider instanceof Carrier) {
                $shippingTax = $this->getShippingProvider()->getTaxCalculator();

                if ($shippingTax instanceof TaxCalculator) {
                    $taxesAmount = $shippingTax->getTaxesAmount($baseCurrency ? $this->getBaseShipping(false) : $this->getShipping(false), true);

                    if (is_array($taxesAmount)) {
                        foreach ($taxesAmount as $id => $amount) {
                            $addTax(Tax::getById($id), $amount);
                        }
                    }
                }
            }
        }

        $paymentProvider = $this->getPaymentProvider();

        if ($paymentProvider instanceof PaymentPlugin) {
            if ($paymentProvider->getPaymentTaxCalculator($this) instanceof TaxCalculator) {
                $taxesAmount = $paymentProvider->getPaymentTaxCalculator($this)->getTaxesAmount($baseCurrency ? $this->getBasePaymentFee(false) : $this->getPaymentFee(false), true);

                if(is_array($taxesAmount)) {
                    foreach ($taxesAmount as $id => $amount) {
                        $addTax(Tax::getById($id), $amount);
                    }
                }
            }
        }

        return $usedTaxes;
    }

    /**
     * get shipping carrier for cart (if non selected, get cheapest).
     *
     * @return null|Carrier
     *
     * @throws ObjectUnsupportedException
     */
    public function getShippingProvider()
    {
        if (count($this->getItems()) === 0) {
            return null;
        }

        //check for existing shipping
        if ($this->getCarrier() instanceof Carrier) {
            return $this->getCarrier();
        }

        if ($this->hasPhysicalItems()) {
            $carrier = Carrier::getCheapestCarrierForCart($this);

            if ($carrier instanceof Carrier) {
                return $carrier;
            }
        }

        return null;
    }

    /**
     * get Shipping costs for specific carrier.
     *
     * @param Carrier $carrier
     * @param bool $withTax
     *
     * @return float
     */
    public function getShippingCostsForCarrier(Carrier $carrier, $withTax = true)
    {
        if (!$this->getFreeShipping()) {
            $freeShippingCurrency = floatval(Configuration::get('SYSTEM.SHIPPING.FREESHIPPING_PRICE'));
            $freeShippingWeight = floatval(Configuration::get('SYSTEM.SHIPPING.FREESHIPPING_WEIGHT'));

            if (isset($freeShippingCurrency) && $freeShippingCurrency > 0) {
                $freeShippingCurrency = $this->convertToCurrency($freeShippingCurrency);

                if ($this->getSubtotal() >= $freeShippingCurrency) {
                    return 0;
                }
            }

            if (isset($freeShippingWeight) && $freeShippingWeight > 0) {
                if ($this->getTotalWeight() >= $freeShippingWeight) {
                    return 0;
                }
            }

            return $carrier->getDeliveryPrice($this, $withTax);
        }

        return 0;
    }

    /**
     * Check if this cart is free shipping
     *
     * @return bool
     */
    public function isFreeShipping()
    {
        $priceRuleCollection = $this->getPriceRules();

        foreach ($priceRuleCollection as $ruleItem) {
            $rule = $ruleItem->getPriceRule();

            if ($rule instanceof PriceRule) {
                foreach ($rule->getActions() as $action) {
                    if ($action instanceof FreeShipping) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * calculates shipping costs for the cart, without currency conversion.
     *
     * @param $withTax
     *
     * @return float
     */
    public function getBaseShipping($withTax = true) {
        if (!$this->getFreeShipping()) {
            $cacheKey = $withTax ? 'shipping' : 'shippingWithoutTax';

            if (is_null($this->$cacheKey)) {
                $this->$cacheKey = 0;

                if ($this->getShippingProvider() instanceof Carrier) {
                    $this->$cacheKey = $this->getShippingCostsForCarrier($this->getShippingProvider(), $withTax);
                }
            }

            return $this->$cacheKey;
        }

        return 0;
    }

    /**
     * calculates shipping costs for the cart.
     *
     * @param $useTax boolean include taxes
     *
     * @return float
     */
    public function getShipping($useTax = true)
    {
        return $this->convertToCurrency($this->getBaseShipping($useTax));
    }

    /**
     * get shipping tax rate.
     *
     * @return int
     */
    public function getShippingTaxRate()
    {
        return $this->convertToCurrency($this->getBaseShippingTax());
    }

    /**
     * calculates shipping tax for the cart, without currency conversion.
     *
     * @return float
     */
    public function getBaseShippingTax()
    {
        if (!$this->getFreeShipping()) {
            if ($this->getShippingProvider() instanceof Carrier) {
                return $this->getShippingProvider()->getTaxAmount($this);
            }
        }

        return 0;
    }

    /**
     * calculates shipping tax for the cart.
     *
     * @return float
     */
    public function getShippingTax()
    {
        if (!$this->getFreeShipping()) {
            if ($this->getShippingProvider() instanceof Carrier) {
                return $this->getShippingProvider()->getTaxAmount($this);
            }
        }

        return 0;
    }

    /**
     * Get Payment Provider.
     *
     * @return PaymentPlugin
     */
    public function getPaymentProvider()
    {
        $paymentProvider = \CoreShop::getPaymentProvider($this->getPaymentModule());

        return $paymentProvider;
    }

    /**
     * Calculate the payment fee.
     *
     * @param $withTax boolean use taxes
     *
     * @return float
     */
    public function getPaymentFee($withTax = true)
    {
        return $this->convertToCurrency($this->getBasePaymentFee($withTax));
    }

    /**
     * Calculate the payment fee, without currency conversion
     *
     * @param $withTax boolean use taxes
     *
     * @return float
     */
    public function getBasePaymentFee($withTax = true)
    {
        $paymentProvider = $this->getPaymentProvider();

        if ($paymentProvider instanceof PaymentPlugin) {
            return $paymentProvider->getPaymentFee($this, $withTax);
        }

        return 0;
    }

    /**
     * get payment fee tax rate.
     *
     * @return float
     */
    public function getPaymentFeeTaxRate()
    {
        $paymentProvider = $this->getPaymentProvider();

        if ($paymentProvider instanceof PaymentPlugin) {
            return $paymentProvider->getPaymentFeeTaxRate($this);
        }

        return 0;
    }

    /**
     * Calculate the payment fee tax.
     *
     * @return float
     */
    public function getPaymentFeeTax()
    {
        return $this->convertToCurrency($this->getBasePaymentFeeTax());
    }

    /**
     * Calculate the payment fee tax, without currency conversion.
     *
     * @return float
     */
    public function getBasePaymentFeeTax()
    {
        $paymentProvider = $this->getPaymentProvider();

        if ($paymentProvider instanceof PaymentPlugin) {
            return $paymentProvider->getPaymentFeeTax($this);
        }

        return 0;
    }

    /**
     * get all taxes.
     *
     * @return float
     */
    public function getTotalTax()
    {
        $totalWithTax = $this->getTotal();
        $totalWithoutTax = $this->getTotal(false);

        return abs($totalWithTax - $totalWithoutTax);
    }

    /**
     * get all taxes, without currency conversion
     *
     * @return float
     */
    public function getBaseTotalTax()
    {
        $totalWithTax = $this->getBaseTotal();
        $totalWithoutTax = $this->getBaseTotal(false);

        return abs($totalWithTax - $totalWithoutTax);
    }

    /**
     * calculates the total of the cart.
     *
     * @param boolean $withTax get price with tax or without
     *
     * @return float
     */
    public function getTotal($withTax = true)
    {
        return $this->convertToCurrency($this->getBaseTotal($withTax));
    }

    /**
     * calculates the total of the cart, without currency conversion
     *
     * @param boolean $withTax get price with tax or without
     *
     * @return float
     */
    public function getBaseTotal($withTax = true)
    {
        $totalWd = $this->getBaseTotalWithoutDiscount($withTax);
        $discount = $this->getBaseDiscount($withTax);

        return ($totalWd) - $discount;
    }

    /**
     * calculates the total without discount
     *
     * @param bool $withTax
     * @return float
     */
    public function getTotalWithoutDiscount($withTax = true)
    {
        return $this->convertToCurrency($this->getBaseTotalWithoutDiscount($withTax));
    }

    /**
     * calculates the total without discount
     *
     * @param bool $withTax
     * @return float
     */
    public function getBaseTotalWithoutDiscount($withTax = true)
    {
        $subtotal = $this->getBaseSubtotal($withTax);
        $shipping = $this->getBaseShipping($withTax);
        $payment = $this->getBasePaymentFee($withTax);

        return $subtotal + $shipping + $payment;
    }

    /**
     * calculates the total weight of the cart.
     *
     * @return int
     */
    public function getTotalWeight()
    {
        $weight = 0;

        foreach ($this->getItems() as $item) {
            $weight += $item->getWeight();
        }

        return $weight;
    }

    /**
     * finds the CartItem for a Product.
     *
     * @param Product $product
     *
     * @return bool|Item
     *
     * @throws Exception
     */
    public function findItemForProduct(Product $product)
    {
        if (!$product instanceof Product) {
            throw new Exception('$product must be instance of Product');
        }

        foreach ($this->getItems() as $item) {
            if ($item->getProduct()->getId() == $product->getId()) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Changes the quantity of a Product in the Cart.
     *
     * @param Product    $product
     * @param int        $amount
     * @param bool|false $increaseAmount
     * @param bool|true  $autoAddPriceRule
     *
     * @return bool|Item
     *
     * @throws Exception
     */
    public function updateQuantity(Product $product, $amount = 0, $increaseAmount = false, $autoAddPriceRule = true)
    {
        if (!$product instanceof Product) {
            throw new Exception('$product must be instance of Product');
        }

        $item = $this->findItemForProduct($product);

        if ($item instanceof Item) {
            if ($amount <= 0) {
                $this->removeItem($item);

                return false;
            } else {
                $newAmount = $amount;

                if ($increaseAmount === true) {
                    $currentAmount = $item->getAmount();

                    if (is_integer($currentAmount)) {
                        $newAmount = $currentAmount + $amount;
                    }
                }

                $item->setAmount($newAmount);
                $item->save();
            }
        } else {
            $items = $this->getItems();

            if (!is_array($items)) {
                $items = [];
            }

            $item = Item::create();
            $item->setKey(uniqid());
            $item->setParent($this);
            $item->setAmount($amount);
            $item->setProduct($product);
            $item->setPublished(true);
            $item->save();

            $items[] = $item;

            $this->setItems($items);
            $this->save();
        }

        if ($autoAddPriceRule) {
            PriceRule::autoAddToCart($this);
        }

        //Clear Cache of Product Price, cause a PriceRule could change the price
        $product->clearPriceCache();
        $this->checkCarrierValid();

        return $item;
    }

    /**
     * Adds a new item to the cart.
     *
     * @param Product $product
     * @param int     $amount
     *
     * @return bool|Item
     *
     * @throws Exception
     */
    public function addItem(Product $product, $amount = 1)
    {
        $this->prepare(true);

        return $this->updateQuantity($product, $amount, true);
    }

    /**
     * Removes a item from the cart.
     *
     * @param Item $item
     */
    public function removeItem(Item $item)
    {
        $item->getProduct()->clearPriceCache();
        
        $item->delete();

        $this->checkCarrierValid();
    }

    /**
     * Modifies the quantity of a CartItem.
     *
     * @param Item $item
     * @param $amount
     *
     * @return bool|Item
     *
     * @throws Exception
     */
    public function modifyItem(Item $item, $amount)
    {
        return $this->updateQuantity($item->getProduct(), $amount, false);
    }

    /**
     * Check if carrier is still valid
     */
    public function checkCarrierValid()
    {
        if ($this->getCarrier() instanceof Carrier) {
            $carrierValid = true;

            if (!$this->getShippingAddress() instanceof Address) {
                $carrierValid = false;
            } elseif (!$this->getCarrier()->checkCarrierForCart($this, $this->getShippingAddress())) {
                $carrierValid  = false;
            }

            if (!$carrierValid) {
                $this->setCarrier(null);
                $this->save();
            }
        }
    }

    /**
     * Removes an existing PriceRule from the cart.
     *
     * @param PriceRule $priceRule
     *
     * @return bool
     *
     * @throws Exception
     */
    public function removePriceRule($priceRule)
    {
        if ($priceRule instanceof PriceRule) {
            $priceRule->unApplyRules($this);

            $priceRules = $this->getPriceRuleFieldCollection();

            foreach ($priceRules->getItems() as $index => $rule) {
                if ($rule->getPriceRule()->getId() === $priceRule->getId()) {
                    $priceRules->remove($index);
                    break;
                }
            }
            $this->setPriceRules($priceRules->getItems());
            $this->save();
        }

        return true;
    }

    /**
     * Adds a new PriceRule to the Cart.
     *
     * @param \CoreShop\Model\Cart\PriceRule $priceRule
     * @param string $voucherCode Voucher Token
     *
     * @throws Exception
     */
    public function addPriceRule(PriceRule $priceRule, $voucherCode)
    {
        $priceRules = $this->getPriceRules();
        $exists = false;

        foreach ($priceRules as $ruleItem) {
            $rule = $ruleItem->getPriceRule();

            if ($rule instanceof PriceRule) {
                if ($rule->getId() === $priceRule->getId()) {
                    $exists = true;
                    break;
                }
            }
        }

        if (!$exists) {
            $priceRuleData = \CoreShop\Model\PriceRule\Item::create();

            $priceRuleData->setPriceRule($priceRule);
            $priceRuleData->setVoucherCode($voucherCode);

            $fieldCollection = $this->getPriceRuleFieldCollection() instanceof Fieldcollection ? $this->getPriceRuleFieldCollection() : new Fieldcollection();
            $fieldCollection->add($priceRuleData);

            $this->setPriceRules($fieldCollection->getItems());

            $priceRule->applyRules($this);

            if ($this->getId()) {
                $this->save();
            }
        }
    }

    /**
     * @return \CoreShop\Model\PriceRule\Item[]
     */
    public function getPriceRules()
    {
        $collection = $this->getPriceRuleFieldCollection();

        if ($collection instanceof Fieldcollection) {
            $priceRules = [];

            foreach ($collection->getItems() as $priceRule) {
                if ($priceRule instanceof \CoreShop\Model\PriceRule\Item) {
                    if ($priceRule->getPriceRule()->getActive()) {
                        $priceRules[] = $priceRule;
                    }
                }
            }

            return $priceRules;
        }

        return [];
    }

    /**
     * @param \CoreShop\Model\PriceRule\Item[] $priceRules
     */
    public function setPriceRules($priceRules)
    {
        $fieldCollection = new Fieldcollection();
        $fieldCollection->setItems($priceRules);

        $this->setPriceRuleFieldCollection($fieldCollection);
    }

    /**
     * Returns Customers shipping address.
     *
     * @return Address|bool
     */
    public function getCustomerShippingAddress()
    {
        if ($this->getShippingAddress() instanceof Address) {
            return $this->getShippingAddress();
        }

        return false;
    }

    /**
     * Returns Customers billing address.
     *
     * @return Address|bool
     */
    public function getCustomerBillingAddress()
    {
        if ($this->getBillingAddress() instanceof Address) {
            return $this->getBillingAddress();
        }

        return false;
    }

    /**
     * get customers taxation address.
     *
     * @return bool|Address
     */
    public function getCustomerAddressForTaxation()
    {
        $taxationAddress = Configuration::get('SYSTEM.BASE.TAXATION.ADDRESS');

        if (!$taxationAddress) {
            $taxationAddress = 'shipping';
        }

        if ($taxationAddress === 'shipping') {
            return $this->getCustomerShippingAddress();
        }

        return $this->getCustomerBillingAddress();
    }

    /**
     * Creates order for cart
     *
     * @param Payment $paymentModule
     * @param $language
     *
     * @return Order
     *
     * @throws Exception
     */
    public function createOrder(Payment $paymentModule = null, $language = null)
    {
        Logger::info('Create order for cart ' . $this->getId());

        if (is_null($language)) {
            if (\Zend_Registry::isRegistered("Zend_Locale")) {
                $language = \CoreShop::getTools()->getLocale();
            } else {
                throw new Exception("Language not found in registry and not set as param");
            }
        }

        $orderClass = Order::getPimcoreObjectClass();
        $parentFolder = $orderClass::getPathForNewOrder();
        $orderNumber = $orderClass::getNextOrderNumber();

        $order = Order::create();
        $order->setKey(\Pimcore\File::getValidFilename($orderNumber));
        $order->setOrderNumber($orderNumber);
        $order->setParent($parentFolder);
        $order->setPublished(true);
        $order->setLang($language);
        $order->setCustomer($this->getUser());

        if ($paymentModule instanceof Payment) {
            $order->setPaymentProviderToken($paymentModule->getIdentifier());
            $order->setPaymentProvider($paymentModule->getName());
            $order->setPaymentProviderDescription($paymentModule->getDescription());
        }

        $order->setOrderDate(new Date());

        if (\Pimcore\Config::getFlag("useZendDate")) {
            $order->setOrderDate(Date::now());
        } else {
            $order->setOrderDate(Carbon::now());
        }

        $order->setCurrency($this->getCurrency());
        $order->setShop($this->getShop());

        if ($this->getCarrier() instanceof Carrier) {
            $order->setShippingTaxRate($this->getShippingTaxRate());
            $order->setCarrier($this->getCarrier());

            $order->setShipping($this->getShipping());
            $order->setShippingWithoutTax($this->getShipping(false));
            $order->setShippingTax($this->getShippingTax());

            $order->setBaseShipping($this->getBaseShipping());
            $order->setBaseShippingWithoutTax($this->getBaseShipping(false));
            $order->setBaseShippingTax($this->getBaseShippingTax());
        } else {
            $order->setShippingTaxRate(0);

            $order->setShipping(0);
            $order->setShippingWithoutTax(0);
            $order->setShippingTax(0);

            $order->setBaseShipping(0);
            $order->setBaseShippingWithoutTax(0);
            $order->setBaseShippingTax(0);
        }
        $order->setPaymentFeeTaxRate($this->getPaymentFeeTaxRate());

        $order->setPaymentFee($this->getPaymentFee());
        $order->setPaymentFeeTax($this->getPaymentFeeTax());
        $order->setPaymentFeeWithoutTax($this->getPaymentFee(false));

        $order->setBasePaymentFee($this->getBasePaymentFee());
        $order->setBasePaymentFeeTax($this->getBasePaymentFeeTax());
        $order->setBasePaymentFeeWithoutTax($this->getBasePaymentFee(false));

        $order->setTotalTax($this->getTotalTax());
        $order->setTotal($this->getTotal());
        $order->setTotalWithoutTax($this->getTotal(false));
        $order->setSubtotal($this->getSubtotal());
        $order->setSubtotalWithoutTax($this->getSubtotal(false));
        $order->setSubtotalTax($this->getSubtotalTax());

        $order->setBaseTotalTax($this->getBaseTotalTax());
        $order->setBaseTotal($this->getBaseTotal());
        $order->setBaseTotalWithoutTax($this->getBaseTotal(false));
        $order->setBaseSubtotal($this->getBaseSubtotal());
        $order->setBaseSubtotalWithoutTax($this->getBaseSubtotal(false));
        $order->setBaseSubtotalTax($this->getBaseSubtotalTax());

        if (\CoreShop::getTools()->getVisitor() instanceof Visitor) {
            $order->setVisitorId(\CoreShop::getTools()->getVisitor()->getId());
        }

        \CoreShop::actionHook('order.preSave', ['order' => $order, 'cart' => $this]);

        $order->save();

        if ($this->getShippingAddress() instanceof Address) {
            $order->setShippingAddress($this->copyAddress($order, $this->getShippingAddress(), "shipping"));
        }

        if ($this->getBillingAddress() instanceof Address) {
            $order->setBillingAddress($this->copyAddress($order, $this->getBillingAddress(), "billing"));
        }

        $order->importCart($this);

        $this->save();

        //allow third-parties to hook into order
        \CoreShop::actionHook('order.created', ['order' => $order]);

        return $order;
    }

    /**
     * @param $price
     * @return string
     */
    public function formatPrice($price)
    {
        return \CoreShop::getTools()->formatPrice($price, $this->getBillingAddress() instanceof Address ? $this->getBillingAddress()->getCountry() : null, $this->getCurrency());
    }

    /**
     * Copy Address to order
     *
     * @param Order $order
     * @param Address|null $address
     * @param string $type
     * @return Address
     */
    public function copyAddress(Order $order, Address $address = null, $type = "shipping")
    {
        ObjectService::loadAllObjectFields($address);

        $newAddress = clone $address;
        $newAddress->setId(null);
        $newAddress->setParent($order->getPathForAddresses());
        $newAddress->setKey($type);
        $newAddress->save();

        return $newAddress;
    }

    /**
     * maintenance job.
     */
    public static function maintenance()
    {
        $lastMaintenance = Configuration::get('SYSTEM.CART.AUTO_CLEANUP.LAST_RUN');

        //initial.
        if (is_null($lastMaintenance)) {
            $lastMaintenance = time() - 90000; //t-25h
        }

        $timeDiff = time() - $lastMaintenance;

        Logger::log('CoreShop cart cleanup: start');
        //since maintenance runs every 5 minutes, we need to check if the last update was 24 hours ago
        if ($timeDiff > 24 * 60 * 60) {
            $cleanUpParams = [];

            $days = Configuration::get('SYSTEM.CART.AUTO_CLEANUP.OLDER_THAN_DAYS');
            $anonCart = Configuration::get('SYSTEM.CART.AUTO_CLEANUP.DELETE_ANONYMOUS');
            $userCart = Configuration::get('SYSTEM.CART.AUTO_CLEANUP.DELETE_USER');

            if (!is_null($days)) {
                $cleanUpParams['olderThanDays'] = (int) $days;
            }
            if ($anonCart) {
                $cleanUpParams['deleteAnonymousCart'] = true;
            }
            if ($userCart) {
                $cleanUpParams['deleteUserCart'] = true;
            }

            try {
                $cleanUpCart = new CleanUpCart();
                $cleanUpCart->setOptions($cleanUpParams);

                if (!$cleanUpCart->hasErrors()) {
                    $elements = $cleanUpCart->getCartElements();

                    if (count($elements) > 0) {
                        foreach ($elements as $cart) {
                            $cleanUpCart->deleteCart($cart);
                            Logger::log('CoreShop cart cleanup: remove cart ('.$cart->getId().')');
                        }
                    }

                    Configuration::set('SYSTEM.CART.AUTO_CLEANUP.LAST_RUN', time());
                }
            } catch (\Exception $e) {
                Logger::log('CoreShop cart cleanup error: '.$e->getMessage());
            }
        }
    }

    /**
     * Adds existing order to cart (re-ordering)
     *
     * @param Order $order
     * @param bool $removeExistingItems
     */
    public function addOrderToCart(Order $order, $removeExistingItems = false)
    {
        if ($removeExistingItems) {
            foreach ($this->getItems() as $item) {
                $this->removeItem($item);
            }
        }

        foreach ($order->getItems() as $item) {
            if ($item->getProduct() instanceof Product) {
                $this->addItem($item->getProduct(), $item->getAmount());
            }
        }
    }

    /**
     * Check if Cart is active session cart
     *
     * @return bool
     */
    public function isActiveCart()
    {
        $sessionCart = \CoreShop::getTools()->getCartManager()->getSessionCart();

        if ($sessionCart instanceof Cart) {
            return $sessionCart->getId() === $this->getId();
        }

        return false;
    }

    /**
     * @return string
     */
    public function getCacheKey()
    {
        $fingerprint = $this->getId();

        foreach ($this->getItems() as $item) {
            if ($item instanceof Cart\Item) {
                $fingerprint .= $item->getAmount() . $item->getProduct()->getId();
            }
        }

        return $fingerprint;
    }

    /**
     *
     */
    public function __sleep()
    {
        $parentVars = parent::__sleep();

        $finalVars = [];
        $notAllowedFields = ['shippingWithoutTax', 'shipping'];

        foreach ($parentVars as $key) {
            if (!in_array($key, $notAllowedFields)) {
                $finalVars[] = $key;
            }
        }

        return $finalVars;
    }

    /**
     * Convert Value to Carts - Currency
     *
     * @param $price
     * @return mixed
     */
    public function convertToCurrency($price)
    {
        return \CoreShop::getTools()->convertToCurrency($price, $this->getCurrency());
    }


    /**
     * @return string
     *
     * @throws ObjectUnsupportedException
     */
    public function getName()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param string $name
     *
     * @throws ObjectUnsupportedException
     */
    public function setName($name)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Currency
     *
     * @throws ObjectUnsupportedException
     */
    public function getCurrency()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Currency $currency
     *
     * @throws ObjectUnsupportedException
     */
    public function setCurrency($currency)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return boolean
     *
     * @throws ObjectUnsupportedException
     */
    public function getFreeShipping()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param boolean $freeShipping
     *
     * @throws ObjectUnsupportedException
     */
    public function setFreeShipping($freeShipping)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Item[]
     *
     * @throws ObjectUnsupportedException
     */
    public function getItems()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Item[] $items
     *
     * @throws ObjectUnsupportedException
     */
    public function setItems($items)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Carrier|null
     *
     * @throws ObjectUnsupportedException
     */
    public function getCarrier()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Carrier $carrier
     *
     * @throws ObjectUnsupportedException
     */
    public function setCarrier($carrier)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return PriceRule|null
     *
     * @throws ObjectUnsupportedException
     */
    public function getPriceRule()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param PriceRule $priceRule
     *
     * @throws ObjectUnsupportedException
     */
    public function setPriceRule($priceRule)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Fieldcollection|null
     *
     * @throws ObjectUnsupportedException
     */
    public function getPriceRuleFieldCollection()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Fieldcollection $priceRules
     *
     * @throws ObjectUnsupportedException
     */
    public function setPriceRuleFieldCollection($priceRules)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Order|null
     *
     * @throws ObjectUnsupportedException
     */
    public function getOrder()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Order $order
     *
     * @throws ObjectUnsupportedException
     */
    public function setOrder($order)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return string
     *
     * @throws ObjectUnsupportedException
     */
    public function getPaymentModule()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param string $paymentModule
     *
     * @throws ObjectUnsupportedException
     */
    public function setPaymentModule($paymentModule)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Shop
     *
     * @throws ObjectUnsupportedException
     */
    public function getShop()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Shop $shop
     *
     * @throws ObjectUnsupportedException
     */
    public function setShop($shop)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return User|null
     *
     * @throws ObjectUnsupportedException
     */
    public function getUser()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param User $user
     *
     * @throws ObjectUnsupportedException
     */
    public function setUser($user)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getShippingAddress()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $shippingAddress
     *
     * @throws ObjectUnsupportedException
     */
    public function setShippingAddress($shippingAddress)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getBillingAddress()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $billingAddress
     *
     * @throws ObjectUnsupportedException
     */
    public function setBillingAddress($billingAddress)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }
}
