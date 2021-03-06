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

namespace CoreShop\Bundle\CoreBundle\Shipping\Calculator;

use CoreShop\Bundle\ShippingBundle\Calculator\CarrierPriceCalculatorInterface;
use CoreShop\Component\Address\Model\AddressInterface;
use CoreShop\Component\Core\Model\CarrierInterface as CoreCarrierInterface;
use CoreShop\Component\Shipping\Model\CarrierInterface as BaseCarrierInterface;
use CoreShop\Component\Core\Model\TaxRuleGroupInterface;
use CoreShop\Component\Core\Taxation\TaxCalculatorFactoryInterface;
use CoreShop\Component\Order\Model\CartInterface;
use CoreShop\Component\Shipping\Model\ShippableInterface;
use CoreShop\Component\Taxation\Calculator\TaxCalculatorInterface;

final class TaxedCarrierPriceRuleCalculator implements CarrierPriceCalculatorInterface
{
    /**
     * @var CarrierPriceCalculatorInterface
     */
    private $inner;

    /**
     * @var TaxCalculatorInterface
     */
    private $taxCalculator;

    /**
     * @var TaxCalculatorFactoryInterface
     */
    private $taxCalculatorFactory;

    /**
     * @param CarrierPriceCalculatorInterface     $inner
     * @param TaxCalculatorFactoryInterface       $taxCalculatorFactory
     */
    public function __construct(
        CarrierPriceCalculatorInterface $inner,
        TaxCalculatorFactoryInterface $taxCalculatorFactory
    ) {
        $this->inner = $inner;
        $this->taxCalculatorFactory = $taxCalculatorFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrice(BaseCarrierInterface $carrier, ShippableInterface $shippable, AddressInterface $address, $withTax = true)
    {
        $netPrice = $this->inner->getPrice($carrier, $shippable, $address, $withTax);

        if ($withTax && $carrier instanceof CoreCarrierInterface) {
            $taxCalculator = $this->getTaxCalculator($carrier, $address);

            if ($taxCalculator instanceof TaxCalculatorInterface) {
                $netPrice = $taxCalculator->applyTaxes($netPrice);
            }
        }

        return $netPrice;
    }

    /**
     * {@inheritdoc}
     */
    private function getTaxCalculator(CoreCarrierInterface $carrier, AddressInterface $address)
    {
        if (is_null($this->taxCalculator)) {
            $taxRuleGroup = $carrier->getTaxRule();

            if ($taxRuleGroup instanceof TaxRuleGroupInterface) {
                $this->taxCalculator = $this->taxCalculatorFactory->getTaxCalculatorForAddress($taxRuleGroup, $address);
            } else {
                $this->taxCalculator = null;
            }
        }

        return $this->taxCalculator;
    }
}
