define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'clipboard.min'], function ($, undefined, Backend, Table, Form, ClipboardJS) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/payment/index' + location.search,
                    add_url: 'order/payment/add',
                    edit_url: 'order/payment/edit',
                    // del_url: 'order/payment/del',
                    multi_url: 'order/payment/multi',
                    import_url: 'order/payment/import',
                    table: 'payment',
                }
            });
            //绑定复制事件
            var clipboard = new ClipboardJS('.btn-copy');
            clipboard.on('success', function (e) {
                Toastr.success('复制成功');
            });
            clipboard.on('error', function (e) {
                Toastr.error('复制失败，请刷新后重试');
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
                        // {field: 'id', title: __('Id')},
                        {field: 'order_id', title: __('Order_id'), operate: 'LIKE'},//订单id、
                        {
                            field: 'time',
                            title: __('Time'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false
                        },//下单时间、
                        {
                            field: 'seller_note',
                            title: __('Seller_note'),
                            operate: 'LIKE',
                            table: table,
                            class: 'autocontent',
                            formatter: Table.api.formatter.content
                        },// 卖家备注、
                        {field: 'total', title: __('Total'), operate: 'LIKE', table: table},// 订单总价、
                        {field: 'device', title: "处理设备", operate: 'LIKE', table: table},// 处理设备、
                        {field: 'accountName', title: "账号名称", operate: 'LIKE', table: table},// 账号名称、
                        {field: 'merchant_code', title: __('Merchant_code'), operate: 'LIKE', table: table},// 商品编号、
                        {
                            field: 'customInfos_value',
                            title: __('CustomInfos_value'),
                            operate: 'LIKE',
                            table: table,
                            class: 'autocontent',
                            formatter: function (value, row, index) {
                                return '<a href="javascript:;"  data-clipboard-text="' + value + '" class="btn-copy" data-toggle="tooltip" data-original-title="点击复制">' + value + '</a>';
                            }
                        },             // 充值账号、
                        {field: 'system_status_name', title: __('System_status'), operate: 'LIKE', table: table}, // 处理状态
                        // {field: 'buyer_note', title: __('Buyer_note'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        // {field: 'total', title: __('Total'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        // {field: 'group_status', title: __('Group_status')},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'is_wei_order', title: __('Is_wei_order')},
                        // {field: 'f_seller_id', title: __('F_seller_id')},
                        // {field: 'express_type', title: __('Express_type'), operate:'BETWEEN'},
                        // 订单状态编号，返回值： 10 待付款；20 已付款，待发货；21 部分付款 (例如：定金预售)；30 已发货；31 部分发货；40 已确认收货；50 已完成；60 已关闭;注意：目前订单列表中的status和订单详情中的status输出不一致，请注意区别
                        // {field: 'status', title: __('Status'), searchList: {10:__('待付款'),20:__('已付款'),21:__('部分付款'),30:__('已发货'),31:__('部分发货'),40:__('已确认收货'),50:__('已完成'),60:__('已关闭')}, formatter: Table.api.formatter.status},
                        // {field: 'img', title: __('Img'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        // {field: 'express_no', title: __('Express_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        // 0或空值（""） 未发起退款；
                        // 1 申请退款或退款；
                        // 3 拒绝退款；
                        // 4 退货流程同意退货；
                        // 5 退货流程拒绝退货；
                        // 6 退货流程已提交退货物流信息；
                        // 7 换货发货（退货流程已提交退货物流信息）
                        // 9 退款取消；
                        // 10 退款完成（同意退款或收到退货后同意退款，退款流程完成，资金流程开始）
                        // {field: 'express_fee', title: __('Express_fee'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        // {field: 'refundStatus', title: __('Refundstatus'),searchList: {"":__('未发起退款'),0:__('未发起退款'),1:__('申请退款或退款'),3:__('拒绝退款'),4:__('退货流程同意退货'),5:__('退货流程拒绝退货'),6:__('退货流程已提交退货物流信息'),7:__('换货发货'),9:__('退款取消'),10:__('退款完成')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('创建时间'), operate: 'LIKE', table: table},// 订单总价、
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $('.panel-heading .nav-custom-condition a[data-toggle="tab"]', table.closest(".panel-intro")).on('shown.bs.tab', function (e) {
                var value = $(this).data("value");
                var options = table.bootstrapTable('getOptions');
                var queryParams = options.queryParams;
                options.pageNumber = 1;
                options.queryParams = function (params) {
                    //这一行必须要存在,否则在点击下一页时会丢失搜索栏数据
                    params = queryParams(params);

                    //如果希望追加搜索条件,可使用
                    var filter = params.filter ? JSON.parse(params.filter) : {};
                    var op = params.op ? JSON.parse(params.op) : {};
                    if (value) {
                        //这里可以自定义多个筛选条件
                        filter.order_type = value;
                        op.order_type = '=';
                    } else {
                        //选全部时要移除相应的条件
                        delete filter.order_type;
                        delete op.order_type;
                    }

                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);

                    //如果希望忽略搜索栏搜索条件,可使用
                    //params.filter = JSON.stringify(value?{admin_id: value}:{});
                    //params.op = JSON.stringify(value?{admin_id: '='}:{});
                    return params;
                };

                table.trigger("uncheckbox");
                table.bootstrapTable('refresh', {pageNumber: 1});

                return false;
            });
            // 启动和暂停按钮
            $(document).on("click", ".btn-start,.btn-pause,.btn-reload", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                var ids = Table.api.selectedids(table);
                Table.api.multi("changestatus", ids.join(","), table, this);
                setTimeout(function () {
                    location.reload()
                }, 3000)

            });
            setInterval(function () {
                table.bootstrapTable('refresh', {silent: true});
            }, 3000);


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
