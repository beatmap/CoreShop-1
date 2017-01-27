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

use CoreShop\Model\Cart\Item;
use CoreShop\Model\Cart\PriceRule;
use CoreShop\Model\PriceRule\Condition\AbstractCondition;
use CoreShop\Model\Product\AbstractProductPriceRule;
use CoreShop\Model\Product\SpecificPrice;
use CoreShop\Model\User\Address;
use Pimcore\Cache;
use Pimcore\File;
use Pimcore\Model\Asset;
use Pimcore\Model\Object;
use Pimcore\Model\Asset\Image;
use CoreShop\Exception\ObjectUnsupportedException;
use CoreShop\Tool\Service as ToolService;

/**
 * Class Product
 * @package CoreShop\Model
 *
 * @method static Object\Listing\Concrete getByLocalizedfields ($field, $value, $locale = null, $limit = 0)
 * @method static Object\Listing\Concrete getByEan ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByArticleNumber ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByEnabled ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByAvailableForOrder ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByIsVirtualProduct ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByVirtualAsset ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByManufacturer ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByShops ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByCategories ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByWholesalePrice ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByRetailPrice ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByTaxRule ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByPriceWithTax ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByQuantity ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByOutOfStockBehaviour ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByDepth ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByWidth ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByHeight ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByWeight ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByImages ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByCustomProperties ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByVariants ($value, $limit = 0)
 * @method static Object\Listing\Concrete getByClassificationStore ($value, $limit = 0)
 */
class Product extends Base
{
    /**
     * Pimcore Object Class.
     *
     * @var string
     */
    public static $pimcoreClass = 'Pimcore\\Model\\Object\\CoreShopProduct';

    /**
     * @var string
     */
    public static $staticRoute = "coreshop_detail";
    /**
     * OUT_OF_STOCK_DENY denies order of product if out-of-stock.
     */
    const OUT_OF_STOCK_DENY = 0;

    /**
     * OUT_OF_STOCK_ALLOW allows order of product if out-of-stock.
     */
    const OUT_OF_STOCK_ALLOW = 1;

    /**
     * OUT_OF_STOCK_DEFAULT Default behaviour for out of stock.
     */
    const OUT_OF_STOCK_DEFAULT = 2;

    /**
     * @var float
     */
    protected $cheapestDeliveryPrice = null;

    /**
     * @var []
     */
    protected $validPriceRules = null;

    /**
     * @var TaxCalculator|boolean
     */
    protected $taxCalculator;

    /**
     * @var bool
     */
    public static $unitTests = false;

    /**
     * @static
     *
     * @param int $id
     *
     * @return null|static
     */
    public static function getById($id)
    {
        $object = Object\AbstractObject::getById($id);

        if ($object instanceof static) {
            return $object;
        }

        return null;
    }

    /**
     * Get all Products.
     *
     * @return array
     */
    public static function getAll()
    {
        $list = self::getList();
        $list->setCondition('enabled=1');

        return $list->load();
    }

    /**
     * Get latest Products.
     *
     * @param int $limit
     *
     * @return array|mixed
     */
    public static function getLatest($limit = 8)
    {
        $cacheKey = 'coreshop_latest';

        if (!$objects = \Pimcore\Cache::load($cacheKey)) {
            $list = self::getList();
            $list->setCondition("enabled = 1 AND shops LIKE '%,".Shop::getShop()->getId().",%'");
            $list->setOrderKey('o_creationDate');
            $list->setOrder('DESC');

            if ($limit) {
                $list->setLimit($limit);
            }

            $objects = $list->load();
        }

        return $objects;
    }

    /**
     * get price cache tag for product-id
     *
     * @param Product $product
     * @return string
     */
    public static function getPriceCacheTag($product)
    {
        return 'coreshop_product_'.$product->getId().'_price_' . $product->getTaxRate() . '' . \CoreShop::getTools()->getFingerprint();
    }

    /**
     * Get Image for Product.
     *
     * @return bool|\Pimcore\Model\Asset
     */
    public function getImage()
    {
        if (count($this->getImages()) > 0) {
            return $this->getImages()[0];
        }

        return $this->getDefaultImage();
    }

    /**
     * Get default Image for Product.
     *
     * @return bool|\Pimcore\Model\Asset
     */
    public function getDefaultImage()
    {
        $defaultImage = Configuration::get('SYSTEM.PRODUCT.DEFAULTIMAGE');

        if ($defaultImage) {
            $image = Image::getByPath($defaultImage);

            if ($image instanceof Image) {
                return $image;
            }
        }

        return false;
    }

    /**
     * Get Product is new.
     *
     * @return bool
     */
    public function getIsNew()
    {
        $markAsNew = Configuration::get('SYSTEM.PRODUCT.DAYSASNEW');

        if (is_int($markAsNew) && $markAsNew > 0) {
            $creationDate = new \Zend_Date($this->getCreationDate());
            $nowDate = new \Zend_Date();

            $diff = $nowDate->sub($creationDate)->toValue();
            $days = ceil($diff / 60 / 60 / 24) + 1;

            if ($days <= $markAsNew) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Product is in Categry.
     *
     * @param Category $category
     *
     * @return bool
     */
    public function inCategory(Category $category)
    {
        foreach ($this->getCategories() as $c) {
            if ($c->getId() == $category->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all Variants Differences.
     *
     * @param $language
     * @param $type
     * @param $field
     *
     * @return array|boolean
     */
    public function getVariantDifferences($language = null, $type = 'objectbricks', $field = 'variants')
    {
        $cacheKey = 'coreshop_variant_differences'.$this->getId();

        if ($differences = Cache::load($cacheKey)) {
            return $differences;
        }

        if ($language) {
            $language = \Zend_Registry::get('Zend_Locale')->getLanguage();
        }

        $master = $this->getVariantMaster();

        if ($master instanceof self) {
            $differences = ToolService::getProductVariations($master, $this, $type, $field, $language);

            Cache::save($differences, $cacheKey);

            return $differences;
        }

        return false;
    }

    /**
     * Clear Cache for this Product Price
     */
    public function clearPriceCache()
    {
        Cache::clearTag(self::getPriceCacheTag($this));
    }

    /**
     * get all valid specific price riles
     *
     * @return SpecificPrice[]
     */
    public function getValidSpecificPriceRules()
    {
        if (is_null($this->validPriceRules) || self::$unitTests) {
            $specificPrices = $this->getSpecificPrices();
            $rules = [];

            foreach ($specificPrices as $specificPrice) {
                $conditions = $specificPrice->getConditions();

                $isValid = true;

                foreach ($conditions as $condition) {
                    if ($condition instanceof AbstractCondition) {
                        if (!$condition->checkConditionProduct($this, $specificPrice)) {
                            $isValid = false;
                            break;
                        }
                    }
                }

                //Conditions are not valid, so continue with next rule
                if (!$isValid) {
                    continue;
                }

                $rules[] = $specificPrice;
            }

            $this->validPriceRules = $rules;
        }

        return $this->validPriceRules;
    }

    /**
     * Get Specific Price.
     *
     * @return float|boolean
     */
    public function getSpecificPrice()
    {
        $specificPrices = $this->getValidSpecificPriceRules();
        $price = false;

        foreach ($specificPrices as $specificPrice) {
            $actionsPrice = $specificPrice->getPrice($this);

            if ($actionsPrice !== false) {
                $price = $actionsPrice;
            }
        }

        return $price;
    }

    /**
     * Get Discount from Specific Prices.
     *
     * @todo: add some caching?!
     *
     * @return float
     */
    public function getDiscount()
    {
        $price = $this->getSalesPrice(false);

        $specificPrices = $this->getValidSpecificPriceRules();
        $discount = 0;

        foreach ($specificPrices as $specificPrice) {
            if ($specificPrice instanceof AbstractProductPriceRule) {
                $discount += $specificPrice->getDiscount($price, $this);
            }
        }

        //TODO: With this, we can apply post-tax discounts, but this needs to be more tested
        /*if(\CoreShop::getTools()->getPricesAreGross()) {
            $taxCalculator = $this->getTaxCalculator();

            if($taxCalculator) {
                $discount = $taxCalculator->removeTaxes($discount);
            }
        }*/

        return $discount;
    }

    /**
     * Get Sales Price (without discounts) without Currency conversion
     *
     * @param bool $withTax
     * @return float
     */
    public function getSalesPrice($withTax = true)
    {
        $cacheKey = self::getPriceCacheTag($this);

        if ((!$price = Cache::load($cacheKey)) || true) {
            $price = $this->getRetailPrice();
            $specificPrice = $this->getSpecificPrice();

            if ($specificPrice) {
                $price = $specificPrice;
            }

            Cache::save($price, $cacheKey, ['coreshop_product_price', $cacheKey]);
        }

        $calculator = $this->getTaxCalculator();

        if ($withTax) {
            if (!\CoreShop::getTools()->getPricesAreGross()) {
                if ($calculator) {
                    $price = $calculator->addTaxes($price);
                }
            }
        } else {
            if (\CoreShop::getTools()->getPricesAreGross()) {
                if ($calculator) {
                    $price = $calculator->removeTaxes($price);
                }
            }
        }

        //Should we convert this price to the selected currency?
        return $price;
    }

    /**
     * Get retail price with tax
     *
     * @return float
     */
    public function getRetailPriceWithTax()
    {
        $price = $this->getRetailPrice();

        $calculator = $this->getTaxCalculator();

        if (!\CoreShop::getTools()->getPricesAreGross()) {
            if ($calculator) {
                $price = $calculator->addTaxes($price);
            }
        }

        return $price;
    }

    /**
     * Get retail price without tax
     *
     * @return float
     */
    public function getRetailPriceWithoutTax()
    {
        $price = $this->getRetailPrice();

        $calculator = $this->getTaxCalculator();

        if (\CoreShop::getTools()->getPricesAreGross()) {
            if ($calculator) {
                $price = $calculator->removeTaxes($price);
            }
        }

        return $price;
    }

    /**
     * Get Product Price without Currency conversion
     *
     * @param boolean $withTax
     * @param boolean $doCurrencyConvert @deprecated the cart is responsible for conversion stuff
     *
     * @return double
     */
    public function getPrice($withTax = true, $doCurrencyConvert = true) {
        $netPrice = $this->getSalesPrice(false);

        //Apply Discounts on Price, currently, only net-discounts are supported
        $netPrice = $netPrice - $this->getDiscount();

        if ($withTax) {
            $calculator = $this->getTaxCalculator();

            if ($calculator) {
                $netPrice = $calculator->addTaxes($netPrice);
            }
        }

        if ($doCurrencyConvert) {
            return \CoreShop::getTools()->convertToCurrency($netPrice);
        }

        return $netPrice;
    }

    /**
     * get possible min price of Product according to price-rules
     *
     * @return bool|float
     */
    public function getMinPrice()
    {
        return $this->getMinMaxPrice("<");
    }

    /**
     * get possible max price of Product according to price-rules
     *
     * @return bool|float
     */
    public function getMaxPrice()
    {
        return $this->getMinMaxPrice(">");
    }

    /**
     * get possible min/max price of Product according to price-rules
     *
     * @param string $operator
     * @return bool|float
     */
    public function getMinMaxPrice($operator = "<")
    {
        $priceRules = $this->getSpecificPrices();
        $price = $this->getRetailPrice() ? $this->getRetailPrice() : 0;

        foreach ($priceRules as $rule) {
            $priceRulePrice = doubleval($rule->getPrice($this));

            if ($priceRulePrice) {
                if ($operator === "<") {
                    if ($priceRulePrice < $price) {
                        $price = $priceRulePrice;
                    }
                } else {
                    if ($priceRulePrice > $price) {
                        $price = $priceRulePrice;
                    }
                }
            }
        }

        return $price;
    }

    /**
     * returns variant with cheapest price.
     *
     * @return float|mixed
     */
    public function getCheapestVariantPrice()
    {
        $cacheKey = self::getPriceCacheTag($this) . "_cheapest";

        if ($price = Cache::load($cacheKey)) {
            return $price;
        }

        if ($this->getType() == 'object') {
            $childs = $this->getChilds([self::OBJECT_TYPE_VARIANT]);

            $prices = [$this->getPrice()];

            if (empty($childs)) {
                return $this->getPrice();
            } else {
                foreach ($childs as $child) {
                    $prices[] = $child->getPrice();
                }

                $price = min($prices);

                Cache::save($price, $cacheKey, ['coreshop_product_price']);

                return $price;
            }
        }

        return $this->getPrice();
    }

    /**
     * Get Tax Rate.
     *
     * @return float
     */
    public function getTaxRate()
    {
        $calculator = $this->getTaxCalculator();

        if ($calculator) {
            return $calculator->getTotalRate();
        }

        return 0;
    }

    /**
     * Get Product Tax Amount.
     *
     * @param bool $asArray
     *
     * @return float|array
     */
    public function getBaseTaxAmount($asArray = false)
    {
        $calculator = $this->getTaxCalculator();

        if ($calculator) {
            return $calculator->getTaxesAmount($this->getPrice(false), $asArray);
        }

        if ($asArray) {
            return [];
        }

        return 0.0;
    }

    /**
     * Get Product Tax Amount.
     *
     * @param bool $asArray
     *
     * @return float|array
     */
    public function getTaxAmount($asArray = false)
    {
        $calculator = $this->getTaxCalculator();

        if ($calculator) {
            return $calculator->getTaxesAmount($this->getPrice(false), $asArray);
        }

        if ($asArray) {
            return [];
        }

        return 0.0;
    }

    /**
     * get TaxCalculator.
     *
     * @param Address $address
     *
     * @return bool|TaxCalculator
     */
    public function getTaxCalculator(Address $address = null)
    {
        if (is_null($this->taxCalculator)) {
            if (is_null($address)) {
                $cart = \CoreShop::getTools()->getCart();

                $address = $cart->getCustomerAddressForTaxation();

                if (!$address instanceof Address) {
                    $address = Address::create();
                    $address->setCountry(\CoreShop::getTools()->getCountry());
                }
            }

            $taxRule = $this->getTaxRule();

            if ($taxRule instanceof TaxRuleGroup) {
                $taxManager = TaxManagerFactory::getTaxManager($address, $taxRule->getId());
                $taxCalculator = $taxManager->getTaxCalculator();

                $this->taxCalculator = $taxCalculator;
            } else {
                $this->taxCalculator = false;
            }
        }

        return $this->taxCalculator;
    }

    /**
     * Adds $delta to current Quantity.
     *
     * @param $delta
     */
    public function updateQuantity($delta)
    {
        $this->setQuantity($this->getQuantity() + $delta);
        $this->save();
    }

    /**
     * Is Available when out-of-stock.
     *
     * @return bool
     *
     * @throws ObjectUnsupportedException
     */
    public function isAvailableWhenOutOfStock()
    {
        $outOfStockBehaviour = $this->getOutOfStockBehaviour();

        if (is_null($outOfStockBehaviour)) {
            $outOfStockBehaviour = self::OUT_OF_STOCK_DEFAULT;
        }

        if (intval($outOfStockBehaviour) === self::OUT_OF_STOCK_DEFAULT) {
            return intval(Configuration::get('SYSTEM.STOCK.DEFAULTOUTOFSTOCKBEHAVIOUR')) === self::OUT_OF_STOCK_ALLOW;
        }

        return intval($outOfStockBehaviour) === self::OUT_OF_STOCK_ALLOW;
    }

    /**
     * get all specific prices.
     *
     * @return SpecificPrice[]|null
     */
    public function getSpecificPrices()
    {
        return array_merge(SpecificPrice::getSpecificPrices($this), Product\PriceRule::getPriceRules());
    }

    /**
     * get cheapest delivery price for product.
     *
     * @return float
     */
    public function getCheapestDeliveryPrice()
    {
        if (is_null($this->cheapestDeliveryPrice)) {
            $cart = Cart::create();
            $cartItem = Item::create();
            $cartItem->setPublished(true);
            $cartItem->setAmount(1);
            $cartItem->setProduct($this);
            $cart->setItems([$cartItem]);
            $cart->getItems();

            PriceRule::autoAddToCart($cart);
            $this->cheapestDeliveryPrice = $cart->getShipping();
        }

        return $this->cheapestDeliveryPrice;
    }

    /**
     * get url for product -> returns false if the product is not available for the shop
     *
     * @param $language
     * @param bool $reset
     * @param Shop|null $shop
     *
     * @return bool|string
     */
    public function getProductUrl($language, $reset = false, Shop $shop = null)
    {
        return $this->getUrl($language, ["product" => $this->getId(), "name" => File::getValidFilename($this->getName())], static::$staticRoute, $reset, $shop);
    }

    /**
     *
     */
    public function __sleep()
    {
        $parentVars = parent::__sleep();

        $finalVars = [];
        $notAllowedFields = ['cheapestDeliveryPrice', 'validPriceRules', 'taxCalculator'];

        foreach ($parentVars as $key) {
            if (!in_array($key, $notAllowedFields)) {
                $finalVars[] = $key;
            }
        }

        return $finalVars;
    }

    /**
     * Determines if product should be indexed.
     *
     * @return bool
     */
    public function getDoIndex()
    {
        return true;
    }

    /**
     * @return string
     *
     * @throws ObjectUnsupportedException
     */
    public function getEan()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param string $ean
     *
     * @throws ObjectUnsupportedException
     */
    public function setEan($ean)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return string
     *
     * @throws ObjectUnsupportedException
     */
    public function getArticleNumber()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param string $articleNumber
     *
     * @throws ObjectUnsupportedException
     */
    public function setArticleNumber($articleNumber)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return boolean
     *
     * @throws ObjectUnsupportedException
     */
    public function getEnabled()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param boolean $enabled
     *
     * @throws ObjectUnsupportedException
     */
    public function setEnabled($enabled)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return boolean
     *
     * @throws ObjectUnsupportedException
     */
    public function getAvailableForOrder()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param boolean $availableForOrder
     *
     * @throws ObjectUnsupportedException
     */
    public function setAvailableForOrder($availableForOrder)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return boolean
     *
     * @throws ObjectUnsupportedException
     */
    public function getisVirtualProduct()
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
     * @return Manufacturer|null
     *
     * @throws ObjectUnsupportedException
     */
    public function getManufacturer()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Manufacturer|int $manufacturer
     *
     * @throws ObjectUnsupportedException
     */
    public function setManufacturer($manufacturer)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return int[]
     *
     * @throws ObjectUnsupportedException
     */
    public function getShops()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param int[] $shops
     *
     * @throws ObjectUnsupportedException
     */
    public function setShops($shops)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Category[]
     *
     * @throws ObjectUnsupportedException
     */
    public function getCategories()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Category[] $categories
     *
     * @throws ObjectUnsupportedException
     */
    public function setCategories($categories)
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
     * @return TaxRule|null mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getTaxRule()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param TaxRuleGroup $taxRule
     *
     * @throws ObjectUnsupportedException
     */
    public function setTaxRule($taxRule)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return int
     *
     * @throws ObjectUnsupportedException
     */
    public function getQuantity()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param int $quantity
     *
     * @throws ObjectUnsupportedException
     */
    public function setQuantity($quantity)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return int
     *
     * @throws ObjectUnsupportedException
     */
    public function getOutOfStockBehaviour()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param int $outOfStockBehaviour
     *
     * @throws ObjectUnsupportedException
     */
    public function setOutOfStockBehaviour($outOfStockBehaviour)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return float
     *
     * @throws ObjectUnsupportedException
     */
    public function getDepth()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param float $depth
     *
     * @throws ObjectUnsupportedException
     */
    public function setDepth($depth)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getWidth()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param float $width
     *
     * @throws ObjectUnsupportedException
     */
    public function setWidth($width)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return float
     *
     * @throws ObjectUnsupportedException
     */
    public function getHeight()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param float $height
     *
     * @throws ObjectUnsupportedException
     */
    public function setHeight($height)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return float
     *
     * @throws ObjectUnsupportedException
     */
    public function getWeight()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param float $weight
     *
     * @throws ObjectUnsupportedException
     */
    public function setWeight($weight)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return Asset[]
     *
     * @throws ObjectUnsupportedException
     */
    public function getImages()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param Asset[] $images
     *
     * @throws ObjectUnsupportedException
     */
    public function setImages($images)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getCustomProperties()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $customProperties
     *
     * @throws ObjectUnsupportedException
     */
    public function setCustomProperties($customProperties)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getVariants()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $variants
     *
     * @throws ObjectUnsupportedException
     */
    public function setVariants($variants)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @return mixed
     *
     * @throws ObjectUnsupportedException
     */
    public function getClassificationStore()
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }

    /**
     * @param mixed $classificationStore
     *
     * @throws ObjectUnsupportedException
     */
    public function setClassificationStore($classificationStore)
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
     * @param Asset $virtualAsset
     *
     * @throws ObjectUnsupportedException
     */
    public function setVirtualAsset($virtualAsset)
    {
        throw new ObjectUnsupportedException(__FUNCTION__, get_class($this));
    }
}
