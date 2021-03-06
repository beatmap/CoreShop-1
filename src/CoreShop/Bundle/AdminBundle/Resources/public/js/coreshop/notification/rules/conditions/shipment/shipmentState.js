/*
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2017 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 *
 */

pimcore.registerNS('pimcore.plugin.coreshop.notification.rules.conditions.shipmentState');

pimcore.plugin.coreshop.notification.rules.conditions.shipmentState = Class.create(pimcore.plugin.coreshop.rules.conditions.abstract, {
    type: 'shipmentState',

    getForm: function () {
        this.form = Ext.create('Ext.form.FieldSet', {
            items: [
                {
                    xtype: 'combo',
                    fieldLabel: t('coreshop_condition_shipmentState'),
                    name: 'shipmentState',
                    value: this.data ? this.data.shipmentState : 3,
                    width: 250,
                    store: [[1, t('coreshop_shipment_partial')], [2, t('coreshop_shipment_full')], [3, t('coreshop_shipment_all')]],
                    triggerAction: 'all',
                    typeAhead: false,
                    editable: false,
                    forceSelection: true,
                    queryMode: 'local'
                }
            ]
        });

        return this.form;
    }
});
