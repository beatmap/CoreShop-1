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

namespace CoreShop\Component\Order\Cart\Rule;

use CoreShop\Component\Order\Model\CartInterface;
use CoreShop\Component\Order\Model\CartPriceRuleInterface;
use CoreShop\Component\Order\Model\OrderInterface;
use CoreShop\Component\Order\Model\SaleInterface;

interface CartPriceRuleOrderProcessorInterface
{
    /**
     * @param CartPriceRuleInterface $cartPriceRule
     * @param $usedCode
     * @param CartInterface $cart
     * @param SaleInterface $sale
     *
     * @return mixed
     */
    public function process(CartPriceRuleInterface $cartPriceRule, $usedCode, CartInterface $cart, SaleInterface $sale);
}
