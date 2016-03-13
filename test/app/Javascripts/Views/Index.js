
define([
    'dojo/_base/declare',
    'dojo/_base/lang',
    'micro/ui/Confirm',
    'app/common/modscript/views/Page',

    // plugins
    'app/worklistopt/modscript/plugins/buttons/UserButton',
    'app/worklistopt/modscript/plugins/buttons/TagsButton',
    'app/worklistopt/modscript/plugins/buttons/SyncButton',
    'app/worklistopt/modscript/plugins/buttons/InfoButton',
    'app/worklistopt/modscript/plugins/buttons/MenuButton',
    'app/worklistopt/modscript/plugins/buttons/DateButton',
    'app/worklistopt/modscript/plugins/buttons/StatusButton',
    'app/worklistopt/modscript/plugins/buttons/TrashButton',

    'app/worklist/modscript/stores/RefStatus',
    'app/worklist/modscript/stores/RefMenu',
    'app/worklist/modscript/stores/RefPriority',

    'app/worklistopt/modscript/models/Task',
    'app/worklistopt/modscript/stores/Task',

    'dojo/text!app/worklistopt/modhtml/index.tpl',
    'dojo/text!app/worklistopt/modhtml/task.tpl',
    'xstyle/css!app/worklistopt/modhtml/index.css'
], function(
    declare,
    lang,
    Confirm,
    Page,

    // plugins
    UserButton,
    TagsButton,
    SyncButton,
    InfoButton,
    MenuButton,
    DateButton,
    StatusButton,
    TrashButton,

    RefStatusStore,
    RefMenuStore,
    RefPriorityStore,

    TaskModel,
    TaskStore,

    IndexTpl,
    TaskTpl
){

    var Class = declare([Page], {

        templateString: IndexTpl,

        events: {
            'change .task-search': 'onQuery',
            'click .btn-cls-search': 'onClearQuery',
            'keypress .task-input': 'onInputEnter',
            'focusin .task-input, .task-desc': 'onInputFocus',
            'focusout .task-input, .task-desc': 'onInputBlur',
            'click .btn-add-collapse, .workspace-body': 'onWorkspaceClick',
            'click .btn-add-task': 'onAddTask'
        },

        socketListeners: {
            taskmove: function() {

            }
        },

        constructor: function() { 
            this.tasks = [];

            var stores = [
                {store: RefStatusStore, storeId: 'worklist/refstatus'},
                {store: RefPriorityStore, storeId: 'worklist/refpriority'},
                {store: RefMenuStore, storeId: 'worklist/refmenu'}
            ];

            $.each(stores, $.proxy(function(i, item){
                var ds = this.loadStore(item.store, {storeId: item.storeId});
                ds.initializeData();
            }, this));

            this.store = this.loadStore(TaskStore, {storeId: this.uniqid('worklist/task')});
        },

        pageReady: function() {
            this.handleTree();
            this.loadTasks();
        },

        handleTree: function() {
            this.$tree = $('.treeview', this.$element);

            var enums = [
                'beforerender.bt',
                'render.bt',
                'expand.bt',
                'collapse.bt',
                'create.bt',
                'edit.bt',
                'move.bt'
            ];

            this.$tree.off(enums.join(' '));

            this.$tree.on({
                'init.bt': function() {
                    $(this).children('.bt-grid').spinbars();
                },
                'beforerender.bt': $.proxy(this.onBeforeTaskRender, this),
                'render.bt': $.proxy(this.onTaskRender, this),
                'expand.bt': $.proxy(function(e, task){
                    task.set('wtt_expanded', 1);
                }, this),
                'collapse.bt': $.proxy(function(e, task){
                    task.set('wtt_expanded', 0);
                }, this),
                'create.bt': $.proxy(function(e){

                }, this), 
                'edit.bt': $.proxy(function(e, task, text){
                    task.set('wtt_title', text);
                }, this),
                'move.bt': $.proxy(this.onTaskMove, this)
            });

            this.$tree.bigtree({ 
                markup: TaskTpl,
                fields: {
                    id: 'wtt_id',
                    text: 'wtt_title',
                    left: 'wtt_left',
                    right: 'wtt_right',
                    level: 'wtt_depth',
                    leaf: 'wtt_is_leaf',
                    path: 'wtt_path',
                    expand: 'wtt_expanded' 
                }, 
                plugins: [
                    // new UserButton({id: 'user'}),
                    new TagsButton({id: 'tags'}),
                    new SyncButton({id: 'sync'}),
                    new InfoButton({id: 'info'}),
                    // new MenuButton({id: 'menu'}),
                    // new DateButton({id: 'date'}),
                    // new StatusButton({id: 'status'}),
                    new TrashButton({id: 'trash'})
                ]
            }); 

        },

        loadTasks: function(params) {
            var 
                project = this.getProject(),
                tree = this.$tree.bigtree('instance'),
                store = this.store,
                timer;

            params = {
                start: 0,
                limit: 1000,
                wtt_sp_id: project.sp_id
            };

            function load(scope) {
                store.findBy(params).then($.proxy(function(res){
                    var 
                        tasks = res.data,
                        total = res.total;

                    tree.load(tasks);

                    if (tasks.length && ! tree.hasScroll()) {
                        tree.render();
                    }

                    params.start += params.limit;

                    if (params.start < total) {
                        timer = setTimeout($.proxy(function(){
                            clearTimeout(timer);
                            timer = null;
                            load(scope);
                        }, scope), 20);
                    }
                }, scope));
            }

            load(this);
        },

        createTask: function(data) {
            var 
                project = this.getProject(),
                tree = this.$tree.bigtree('instance'),
                owner = tree.selection(),
                first = tree.first(),
                type,
                dest;

            if (owner) {
                type = 'append';
                dest = owner.wtt_id;
            } else if (first) {
                type = 'before';
                dest = first.wtt_id;
            } else {
                type = 'none';
            }

            data = data || {};
            data.wtt_sp_id = project.sp_id;

            $.ajax({
                url: siteUrl('worklistopt/task/create'),
                type: 'post',
                dataType: 'json',
                data: {
                    spec: JSON.stringify(data),
                    type: type,
                    dest: dest
                }
            }).done(function(res){
                console.log(arguments);
            });
        },

        updateTask: function(task, data, opts) {
            var keys = {wtt_id: task.wtt_id};
            
            task.plugins.sync.mask();

            return this.store.update(data, keys, true, opts).then(function(){
                task.plugins.sync.unmask();
            });
        },

        removeTask: function(task) {
            var keys = {wtt_id: task.wtt_id};
            // return this.store.remove(keys);
        },

        monitorTask: function(task, field, oldValue, newValue) {
            var sync = task.sync;
            if (sync) {
                var opts = {headers: {'X-Update-Field': field}}, data = {};
                data[field] = newValue;
                this.updateTask(task, data, opts);    
            }
        },

        onBeforeTaskRender: function(e, tasks) {
            var observables = $.grep(tasks, function(task){
                if ( ! task._observe) {
                    task._observe = true;
                    return true;
                }
                return false;
            });

            if (observables.length) {
                this.store.mon(observables, this.monitorTask, this);
                this.setupTasks(observables);
            }
        },

        onTaskRender: function(e, $nodes, tasks) {
            
        },

        setupTasks: function(tasks) {
            // setup task users
            var 
                tree = this.$tree.bigtree('instance'),
                keys = $.map(tasks, function(task){
                    return task.wtt_id;
                });

            // setup task relations
            // we need ajax queue here...
            $.qajax({
                url: siteUrl('worklistopt/task/relations'),
                type: 'post',
                dataType: 'json',
                data: {
                    keys: JSON.stringify(keys)
                }
            }).done($.proxy(function(res){
                var task, col, id;
                for (id in res) {
                    task = tree.get(id);
                    for (col in res[id]) {
                        task.set(col, res[id][col], false);
                    }
                }
            }, this));
        },

        onQuery: function(e) {
            var value = $(e.currentTarget).val();
            this.$tree.bigtree('query', value);
        },

        onClearQuery: function(e) {
            e.preventDefault();

            this.$tree.bigtree('query', '');
            $('.task-search', this.$element).val('').focus();
        },

        onTaskMove: function(e, task, position, origin) {
            $.ajax({
                url: siteUrl('worklistopt/task/move'),
                type: 'post',
                dataType: 'json',
                data: {
                    task: task.wtt_id,
                    position: position
                }
            }).done(function(res){
                if (res.success) {

                }
            });
        },

        onWorkspaceClick: function(e) {
            e.preventDefault();
            this.$element.find('.task-input-wrapper').removeClass('pull-up');
        },

        onInputFocus: function(e) {
            var el = $(e.currentTarget);
            if ( ! el.data('placeholder')) 
                el.data('placeholder', el.attr('placeholder'));
            el.attr('placeholder', '');
            el.closest('.task-input-wrapper').addClass('pull-up');
        },

        onInputBlur: function(e) {
            var el = $(e.currentTarget); 
            el.attr('placeholder', el.data('placeholder') || '');
        },

        onInputEnter: function(e) {
            if (e.keyCode === 13) {
                $('.btn-add-task').click();
            }
        },

        onAddTask: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $title = $('.task-input', this.$element),
                $desc = $('.task-desc', this.$element),
                title = $title.val(),
                desc = $desc.val(),
                data = {};

            if (title) {
                data.wtt_title = title;
                data.wtt_desc = desc;

                this.createTask(data);

                $desc.val(''); 
                $title.val('').focus();
            }
        },

        destroy: function() {  
            var btree = this.$tree.bigtree('instance'),
                datas = btree.data(),
                dsize = datas.length;

            var name, i;

            for (i = 0; i < dsize; i++) {
                for (name in datas[i].plugins) {
                    if (datas[i].plugins[name]) {
                        datas[i].plugins[name].destroy();
                        datas[i].plugins[name] = null;    
                    }
                }
            }

            btree.destroy(true);
            btree = null;

            this.store.mun();
            this.inherited(arguments);
        }

    });

    return Class;

});