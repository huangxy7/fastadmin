define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: '订单列表/index' + location.search,
                    add_url: '订单列表/add',
                    edit_url: '订单列表/edit',
                    del_url: '订单列表/del',
                    multi_url: '订单列表/multi',
                    import_url: '订单列表/import',
                    table: 'payment',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'total', title: __('Total'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'group_status', title: __('Group_status')},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'is_wei_order', title: __('Is_wei_order')},
                        {field: 'order_id', title: __('Order_id'), operate: 'LIKE'},
                        {field: 'buyer_note', title: __('Buyer_note'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'time', title: __('Time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'seller_note', title: __('Seller_note'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'f_seller_id', title: __('F_seller_id')},
                        {field: 'express_type', title: __('Express_type'), operate:'BETWEEN'},
                        {field: 'status', title: __('Status'), searchList: {"255":__('Status 255')}, formatter: Table.api.formatter.status},
                        {field: 'img', title: __('Img'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'express_no', title: __('Express_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'express_fee', title: __('Express_fee'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'refundStatus', title: __('Refundstatus'), operate: 'LIKE', formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
