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
 * @copyright  Copyright (c) 2015-2016 Dominik Pfaffenbauer (http://www.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Model\Carrier;

use CoreShop\Exception;
use CoreShop\Model\Carrier;
use CoreShop\Model\Carrier\ShippingRule\Action\AbstractAction;
use CoreShop\Model\Carrier\ShippingRule\Condition\AbstractCondition;
use CoreShop\Model\Cart;
use CoreShop\Model\Rules\AbstractRule;
use CoreShop\Model\User\Address;
use Pimcore\Cache;

/**
 * Class ShippingRule
 * @package CoreShop\Model\Carrier
 */
class ShippingRule extends AbstractRule
{
    /**
     * possible types of a condition.
     *
     * @var array
     */
    public static $availableConditions = array('countries', 'amount', 'weight', 'dimension', 'zones', 'postcodes', 'products', 'categories', 'customerGroups');

    /**
     * possible types of a action.
     *
     * @var array
     */
    public static $availableActions = array('fixedPrice', 'additionAmount', 'additionPercent', 'discountAmount', 'discountPercent');

    /**
     * Check if Shipping Rule is valid
     *
     * @param Carrier $carrier
     * @param Cart $cart
     * @param Address $address
     *
     * @return bool
     */
    public function checkValidity(Carrier $carrier, Cart $cart, Address $address)
    {
        $valid = true;

        foreach($this->getConditions() as $condition) {
            if($condition instanceof AbstractCondition) {
                if(!$condition->checkCondition($cart, $address, $this)) {
                    $valid = false;
                    break;
                }
            }
        }

        return $valid;
    }

    /**
     * get price modifications
     *
     * @param Cart $cart
     * @param Address $address
     * @param $price
     * @return float
     */
    public function getPriceModification(Cart $cart, Address $address, $price) {
        $priceModification = 0;

        foreach($this->getActions() as $action) {
            if($action instanceof AbstractAction) {
                $priceModificator = $action->getPriceModification($cart, $address, $price);
                if($priceModificator !== 0) {
                    $priceModification += $action->getPriceModification($cart, $address, $price);
                }
            }
        }

        return $priceModification;
    }

    /**
     * get price
     *
     * @param Cart $cart
     * @param Address $address
     *
     * @return float
     */
    public function getPrice(Cart $cart, Address $address) {
        $price = 0;

        foreach($this->getActions() as $action) {
            if($action instanceof AbstractAction) {
                if($action->getPrice($cart, $address)) {
                    $price = $action->getPrice($cart, $address);
                }
            }
        }

        return $price + $this->getPriceModification($cart, $address, $price);
    }
}