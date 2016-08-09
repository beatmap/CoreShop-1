/**
 * CoreShop
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

pimcore.registerNS('pimcore.plugin.coreshop.rules.conditions.personas');

pimcore.plugin.coreshop.rules.conditions.personas = Class.create(pimcore.plugin.coreshop.rules.conditions.abstract, {

    type : 'personas',

    getForm : function () {
        var me = this;
        var store = pimcore.globalmanager.get('personas');

        var personas = {
            fieldLabel: t('coreshop_condition_personas'),
            typeAhead: true,
            listWidth: 100,
            width : 500,
            store: store,
            displayField: 'text',
            valueField: 'id',
            forceSelection: true,
            multiselect : true,
            triggerAction: 'all',
            name:'personas',
            maxHeight : 400,
            listeners: {
                beforerender: function () {
                    if (!store.isLoaded() && !store.isLoading())
                        store.load();

                    if (me.data && me.data.personas)
                        this.setValue(me.data.personas);
                }
            }
        };

        personas = new Ext.ux.form.MultiSelect(personas);

        if (this.data && this.data.personas) {
            personas.value = this.data.personas;
        }

        this.form = new Ext.form.FieldSet({
            items : [
                personas
            ]
        });

        return this.form;
    }
});