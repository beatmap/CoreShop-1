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

namespace CoreShop\Bundle\FrontendBundle\Controller;

use CoreShop\Component\Order\Checkout\CheckoutManagerInterface;
use CoreShop\Component\Order\Checkout\CheckoutStepInterface;
use Payum\Core\Payum;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

class CheckoutController extends FrontendController
{
    /**
     * @var CheckoutManagerInterface
     */
    private $checkoutManager;

    /**
     * @param CheckoutManagerInterface $checkoutManager
     */
    public function __construct(CheckoutManagerInterface $checkoutManager)
    {
        $this->checkoutManager = $checkoutManager;
    }

    /**
     * @param Request $request
     * @param $stepIdentifier
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function processAction(Request $request, $stepIdentifier)
    {
        /**
         * @var CheckoutStepInterface
         */
        $step = $this->checkoutManager->getStep($stepIdentifier);

        if (!$step instanceof CheckoutStepInterface) {
            return $this->redirectToRoute('coreshop_shop_index');
        }

        //Check all previous steps if they are valid, if not, redirect back
        foreach ($this->checkoutManager->getPreviousSteps($stepIdentifier) as $previousStep) {
            if (!$previousStep->validate($this->getCart())) {
                return $this->redirectToRoute('coreshop_shop_checkout', ['stepIdentifier' => $previousStep->getIdentifier()]);
            }
        }

        if ($step->validate($this->getCart()) && $step->doAutoForward()) {
            $nextStep = $this->checkoutManager->getNextStep($stepIdentifier);

            if ($nextStep) {
                return $this->redirectToRoute('coreshop_shop_checkout', ['stepIdentifier' => $nextStep->getIdentifier()]);
            }
        }

        if ($request->isMethod('POST')) {
            if ($step->commitStep($this->getCart(), $request)) {
                $nextStep = $this->checkoutManager->getNextStep($stepIdentifier);

                if ($nextStep) {
                    return $this->redirectToRoute('coreshop_shop_checkout', ['stepIdentifier' => $nextStep->getIdentifier()]);
                }
            }
        }

        $this->get('coreshop.tracking.manager')->trackCheckoutStep($this->getCart(), $step);

        $dataForStep = $step->prepareStep($this->getCart());

        $dataForStep = array_merge(is_array($dataForStep) ? $dataForStep : [], [
            'cart' => $this->getCart(),
            'checkoutSteps' => $this->checkoutManager->getSteps(),
            'currentStep' => $this->checkoutManager->getCurrentStepIndex($stepIdentifier),
            'step' => $step,
            'identifier' => $stepIdentifier,
        ]);

        return $this->render(sprintf('@CoreShopFrontend/Checkout/steps/%s.html.twig', $stepIdentifier), $dataForStep);
    }

    public function doCheckoutAction(Request $request)
    {
        /*
         * after the last step, we come here
         *
         * what are we doing here?
         *  1. Create Order with Workflow State: initialized
         *  2. Use Payum and redirect to Payment Provider
         *  3. PayumBundle takes care about payment stuff
         *  4. After Payment is done, we return to PayumBundle PaymentController and further process it
         *
         * therefore we need the CartToOrderTransformerInterface here
        */        /*
         * Before we do anything else, lets check if the checkout is still valid
         * Check all previous steps if they are valid, if not, redirect back
         */
        /*
         * @var $step CheckoutStepInterface
         */
        foreach ($this->checkoutManager->getSteps() as $stepIdentifier) {
            $step = $this->checkoutManager->getStep($stepIdentifier);

            if (!$step->validate($this->getCart())) {
                return $this->redirectToRoute('coreshop_shop_checkout', ['stepIdentifier' => $step->getIdentifier()]);
            }
        }

        $this->get('coreshop.tracking.manager')->trackCheckoutAction($this->getCart(), count($this->checkoutManager->getSteps()));

        /**
         * If everything is valid, we continue with Order-Creation.
         */
        $order = $this->getOrderFactory()->createNew();
        $order = $this->getCartToOrderTransformer()->transform($this->getCart(), $order);

        /*
         * TODO: Not sure if we should create payment object right here, if so, the PaymentBundle would'nt be responsible for it :/
        */
        return $this->redirectToRoute('coreshop_shop_payment', ['orderId' => $order->getId()]);
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function thankYouAction(Request $request)
    {
        $orderId = $request->getSession()->get('coreshop_order_id', null);

        if (null === $orderId) {
            return $this->redirectToRoute('coreshop_shop_index');
        }

        $request->getSession()->remove('coreshop_order_id');
        $order = $this->get('coreshop.repository.order')->find($orderId);
        Assert::notNull($order);

        $this->get('coreshop.tracking.manager')->trackCheckoutComplete($order);

        return $this->render('@CoreShopFrontend/Checkout/thank-you.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * @return \CoreShop\Component\Order\Model\CartInterface
     */
    private function getCart()
    {
        return $this->getCartManager()->getCart();
    }

    /**
     * @return \CoreShop\Bundle\OrderBundle\Manager\CartManager
     */
    private function getCartManager()
    {
        return $this->get('coreshop.cart.manager');
    }

    /**
     * @return \CoreShop\Bundle\OrderBundle\Transformer\CartToOrderTransformer
     */
    private function getCartToOrderTransformer()
    {
        return $this->get('coreshop.order.transformer.cart_to_order');
    }

    /**
     * @return \CoreShop\Component\Resource\Factory\PimcoreFactory
     */
    private function getOrderFactory()
    {
        return $this->get('coreshop.factory.order');
    }

    /**
     * @return Payum
     */
    protected function getPayum()
    {
        return $this->get('payum');
    }
}
