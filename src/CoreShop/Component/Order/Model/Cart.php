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

namespace CoreShop\Component\Order\Model;

use CoreShop\Component\Resource\ImplementedByPimcoreException;
use CoreShop\Component\Resource\Pimcore\Model\AbstractPimcoreModel;
use Webmozart\Assert\Assert;

class Cart extends AbstractProposal implements CartInterface
{
    use ProposalPriceRuleTrait;

    /**
     * {@inheritdoc}
     */
    public function getTotalTax($withTax = true)
    {
        return $this->getTotal(true) - $this->getTotal(false);
    }

    /**
     *  {@inheritdoc}
     */
    public function getPaymentFeeTaxRate()
    {
        //TODO: Use PaymentProvider TaxRule (still not implemented) to determine TaxRate
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentFee($withTax = true)
    {
        return $withTax ? $this->getPaymentFeeGross() : $this->getPaymentFeeNet();
    }

    /**
     * {@inheritdoc}
     */
    public function getTotal($withTax = true)
    {
        $total = $this->getTotalWithoutDiscount($withTax);
        $discount = $this->getDiscount($withTax);

        return $total - $discount;
    }

    /**
     * calculates the total without discount.
     *
     * @param bool $withTax
     *
     * @return float
     */
    protected function getTotalWithoutDiscount($withTax = true)
    {
        $subtotal = $this->getSubtotal($withTax);
        $payment = $this->getPaymentFee($withTax);

        return $subtotal + $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubtotal($withTax = true)
    {
        $subtotal = 0;

        foreach ($this->getItems() as $item) {
            if ($item instanceof CartItemInterface) {
                $subtotal += $item->getTotal($withTax);
            }
        }

        return $subtotal;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubtotalTax()
    {
        return $this->getSubtotal(true) - $this->getSubtotal(false);
    }

    /**
     * {@inheritdoc}
     */
    public function getDiscount($withTax = true)
    {
        return $this->getContainer()->get('coreshop.cart.discount_calculator')->getDiscount($this, $withTax);
    }

    /**
     * {@inheritdoc}
     */
    public function getWeight()
    {
        $weight = 0;

        foreach ($this->getItems() as $item) {
            $weight += $item->getTotalWeight();
        }

        return $weight;
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected function getContainer()
    {
        return \Pimcore::getContainer();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentStep()
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentStep($name)
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder($order)
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }
}
