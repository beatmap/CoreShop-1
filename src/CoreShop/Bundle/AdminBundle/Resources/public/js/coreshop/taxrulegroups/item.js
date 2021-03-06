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

pimcore.registerNS('pimcore.plugin.coreshop.taxrulegroups.item');

pimcore.plugin.coreshop.taxrulegroups.item = Class.create(pimcore.plugin.coreshop.abstract.item, {

    iconCls: 'coreshop_icon_tax_rule_groups',

    url: {
        save: '/admin/coreshop/tax_rule_groups/save'
    },

    getItems: function () {
        return [this.getFormPanel()];
    },

    getFormPanel: function () {
        var data = this.data;

        var items = [
            {
                name: 'name',
                fieldLabel: t('name'),
                width: 400,
                value: data.name
            },
            {
                xtype: 'checkbox',
                name: 'active',
                fieldLabel: t('active'),
                width: 250,
                checked: data.active
            }
        ];

        if (this.getMultishopSettings()) {
            items.push(this.getMultishopSettings());
        }

        this.formPanel = new Ext.form.Panel({
            bodyStyle: 'padding:20px 5px 20px 5px;',
            border: false,
            region: 'center',
            autoScroll: true,
            forceLayout: true,
            defaults: {
                forceLayout: true
            },
            buttons: [
                {
                    text: t('save'),
                    handler: this.save.bind(this),
                    iconCls: 'pimcore_icon_apply'
                }
            ],
            items: [
                {
                    xtype: 'fieldset',
                    autoHeight: true,
                    labelWidth: 350,
                    defaultType: 'textfield',
                    defaults: {width: '100%'},
                    items: items
                },
                this.getGrid()
            ]
        });

        return this.formPanel;
    },

    getGrid: function () {
        var listeners = {};

        var modelName = 'coreshop.model.taxrules';

        if (!Ext.ClassManager.get(modelName)) {
            Ext.define(modelName, {
                    extend: 'Ext.data.Model',
                    fields: ['id', 'taxRuleGroup', 'country', 'tax', 'behavior']
                }
            );
        }

        this.store = new Ext.data.Store({
            restful: false,
            idProperty: 'id',
            model: modelName,
            listeners: listeners,
            data: this.data.taxRules
        });

        var statesStore = new Ext.data.Store({
            restful: false,
            proxy: new Ext.data.HttpProxy({
                url: '/admin/coreshop/state/country'
            }),
            reader: new Ext.data.JsonReader({}, [
                {name: 'id'},
                {name: 'name'}
            ]),
            listeners: {
                load: function (store) {
                    var rec = {id: 0, name: t('coreshop_all')};
                    store.insert(0, rec);

                    this.grid.getView().refresh()
                }.bind(this)
            }
        });

        var stateEditor = new Ext.form.ComboBox({
            store: statesStore,
            valueField: 'id',
            displayField: 'name',
            queryMode: 'local',
            disabled: true
        });

        var countryStore = new Ext.data.Store({
            restful: false,
            proxy: new Ext.data.HttpProxy({
                url: '/admin/coreshop/countries/list'
            }),
            autoLoad: true,
            reader: new Ext.data.JsonReader({}, [
                {name: 'id'},
                {name: 'text'}
            ]),
            listeners: {
                load: function (store) {
                    var rec = {id: 0, name: t('coreshop_all')};
                    store.insert(0, rec);

                    this.grid.getView().refresh()
                }.bind(this)
            }
        });
        countryStore.load();

        var countryEditor = new Ext.form.ComboBox({
            store: countryStore,
            valueField: 'id',
            displayField: 'name',
            queryMode: 'local',
            disabled: false
        });

        var gridColumns = [
            {
                header: t('coreshop_tax_rule_country'),
                width: 200,
                dataIndex: 'country',
                editor: countryEditor,
                renderer: function (country) {
                    var store = countryStore;
                    var pos = store.findExact('id', country);
                    if (pos >= 0) {
                        return store.getAt(pos).get('name');
                    }

                    return t('coreshop_all');
                }
            },
            {
                header: t('coreshop_tax_rule_state'),
                width: 200,
                dataIndex: 'state',
                editor: stateEditor,
                renderer: function (state) {
                    var store = statesStore;
                    var pos = store.findExact('id', state);
                    if (pos >= 0) {
                        return store.getAt(pos).get('name');
                    }

                    return t('coreshop_all');
                }
            },
            {
                header: t('coreshop_tax_rule_tax'),
                width: 200,
                dataIndex: 'taxRate',
                editor: new Ext.form.ComboBox({
                    store: pimcore.globalmanager.get('coreshop_tax_rates'),
                    valueField: 'id',
                    displayField: 'name',
                    queryMode: 'local'
                }),
                renderer: function (taxRate) {
                    var record = pimcore.globalmanager.get('coreshop_tax_rates').getById(taxRate);

                    if (record) {
                        return record.get('name');
                    }

                    return null;
                }
            },
            {
                header: t('coreshop_tax_rule_behavior'),
                width: 300,
                dataIndex: 'behavior',
                editor: new Ext.form.ComboBox({
                    store: [[0, t('coreshop_tax_rule_behavior_disable')], [1, t('coreshop_tax_rule_behavior_combine')], [2, t('coreshop_tax_rule_behavior_on_after_another')]],
                    triggerAction: 'all',
                    editable: false,
                    queryMode: 'local'
                }),
                renderer: function (behavior) {
                    switch (parseInt(behavior)) {
                        case 0:
                            return t('coreshop_tax_rule_behavior_disable');
                            break;

                        case 1:
                            return t('coreshop_tax_rule_behavior_combine');
                            break;

                        case 2:
                            return t('coreshop_tax_rule_behavior_on_after_another');
                            break;
                    }
                }
            },
            {
                xtype: 'actioncolumn',
                width: 40,
                tooltip: t('delete'),
                icon: '/pimcore/static6/img/icon/cross.png',
                handler: function (grid, rowIndex) {
                    grid.getStore().removeAt(rowIndex);
                }.bind(this)
            }
        ];

        this.cellEditing = Ext.create('Ext.grid.plugin.CellEditing', {
            clicksToEdit: 1,
            listeners: {
                beforeedit: function (editor, context) {
                    if (context.record) {
                        if (context.record.get('country')) {
                            stateEditor.enable();
                            /*stateEditor.getStore().load({
                             params : {
                             country : context.record.get('country')
                             }
                             });*/
                        }
                    }
                }
            }
        });

        var gridConfig = {
            frame: false,
            store: this.store,
            border: true,
            columns: gridColumns,
            loadMask: true,
            columnLines: true,
            stripeRows: true,
            trackMouseOver: true,
            viewConfig: {
                forceFit: false
            },
            selModel: Ext.create('Ext.selection.RowModel', {}),
            tbar: [
                {
                    text: t('add'),
                    handler: function () {
                        this.store.add({
                            id: null,
                            taxRuleGroup: this.data.id,
                            country: null,
                            tax: null,
                            behavior: 0
                        });
                    }.bind(this),
                    iconCls: 'pimcore_icon_add'
                }
            ],
            plugins: [
                this.cellEditing
            ]
        };

        this.grid = Ext.create('Ext.grid.Panel', gridConfig);

        return this.grid;
    },

    getSaveData: function () {
        var values = this.formPanel.getForm().getFieldValues();
        var taxRules = [];

        this.store.getRange().forEach(function (range, index) {
            var data = range.data;

            if (range.phantom) {
                delete data['id'];
            }

            if (data.state === 0) {
                delete data.state;
            }

            if (data.country === 0) {
                delete data.country;
            }

            taxRules.push(data);
        });

        if (!values['active']) {
            delete values['active'];
        }

        values['taxRules'] = taxRules;

        return values;
    }
});
