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

namespace CoreShop\Bundle\ProductBundle\Calculator;

use CoreShop\Component\Product\Model\ProductInterface;
use Webmozart\Assert\Assert;

class ProductSpecificPriceRuleCalculator extends AbstractPriceRuleCalculator
{
    /**
     * {@inheritdoc}
     */
    protected function getPriceRules($subject)
    {
        Assert::isInstanceOf($subject, ProductInterface::class);

        /*
         * @var $subject ProductInterface
         */
        return $subject->getSpecificPriceRules();
    }
}
