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

namespace CoreShop\Bundle\AdminBundle\Controller;

use CoreShop\Bundle\CoreBundle\Application\Version;
use CoreShop\Bundle\ResourceBundle\Controller\AdminController;
use CoreShop\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class SettingsController.
 */
class SettingsController extends AdminController
{
    /**
     * @param FilterControllerEvent $event
     *
     * @throws \Exception
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        // permission check
        $access = $this->getUser()->getPermission('coreshop_permission_settings');
        if (!$access) {
            throw new \Exception(sprintf('this function requires "%s" permission!', 'coreshop_permission_settings'));
        }
    }

    public function getSettingsAction(Request $request)
    {
        $classes = $this->getParameter('coreshop.pimcore');
        $classMapping = [];

        foreach ($classes as $key => $definition) {
            $alias = explode('.', $key);
            $alias = $alias[1];

            $class = str_replace('Pimcore\\Model\\Object\\', '', $definition['classes']['model']);
            $class = str_replace('\\', '', $class);

            $classMapping[$alias] = $class;
        }

        $settings = [
            'classMapping' => $classMapping,
            'bundle' => [
                'version' => Version::getVersion(),
                'build' => Version::getBuild(),
            ],
        ];

        return $this->json($settings);
    }

    /**
     * @return FactoryInterface
     */
    public function getShopFactory()
    {
        return $this->get('coreshop.factory.shop');
    }
}
