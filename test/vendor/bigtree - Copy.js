/**
 * Bigtree
 *
 * jQuery plugin for rendering hierarchical data
 * Dependencies:
 *      - jQuery (https://jquery.com)
 *      - jQuery UI (https://jqueryui.com)
 *      - jsRender (https://www.jsviews.com)
 *      - jQuery Throttle (http://benalman.com/projects/jquery-throttle-debounce-plugin/)
 *
 * @author Roso Sasongko <roso@kct.co.id>
 */
(function($, undef){
    // preparing for striptags
    /*var bodyre = '((?:[^"\'>]|"[^"]*"|\'[^\']*\')*)',
        tagsre = new RegExp(
            '<(?:'
            + '!--(?:(?:-*[^->])*--+|-?)'
            + '|script\\b' + bodyre + '>[\\s\\S]*?</script\\s*'
            + '|style\\b' + bodyre + '>[\\s\\S]*?</style\\s*'
            + '|/?[a-z]'
            + bodyre
            + ')>',
            'gi'
        );*/

    /**
     * Cast element to jQuery object
     */
    function make(el) {
        return el instanceof jQuery ? el : $(el);
    }

    /**
     * Get index of element from array.
     * This is fastest method rather than using Array.indexOf by avoiding
     * several type checking. See polyfill:
     * https://developer.mozilla.org/en/docs/Web/JavaScript/Reference/Global_Objects/Array/indexOf
     */
    function indexof(array, elem) {
        var size = array.length, i = 0;
        while(i < size) {
            if (array[i] === elem) {
                return i;
            }
            i++;
        }
        return -1;
    }

    /**
     * Select text inside particular input field.
     * Don't confuse with $.select, which actualy used for triggering `select` event.
     */
    function seltext(input, beg, end) {
        var dom = input[0], range;

        beg = beg === undef ? 0 : beg;
        end = end === undef ? input.val().length : end;
        
        if (dom.setSelectionRange) {
            dom.setSelectionRange(beg, end);
            if (/chrom(e|ium)/.test(navigator.userAgent.toLowerCase())) {
                var evt = jQuery.Event('keydown', {which: 37});
                input.triggerHandler(evt);
            }
        } else if (dom.createTextRange) {
            range = dom.createTextRange();
            range.collapse(true);
            range.moveEnd('character', end);
            range.moveStart('character', beg);
            range.select();
        }
    }

    /**
     * Sanitize (remove) html tags from string
     */
    /*function striptags(txt) {
        var old;
        do {
            old = txt;
            txt = txt.replace(tagsre, '');
        } while (txt != old);
        return txt.replace(/</g, '&lt;');
    }*/

    /**
     * Constructor
     */
    var BigTree = function (element, options) {
        this.element = $(element);
        this.init(options);
    };
    
    /**
     * Default options
     */
    BigTree.defaults = {

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

        params: {
            id     : 'wtt_id',
            text   : 'wtt_title',
            left   : 'wtt_left',
            right  : 'wtt_right',
            level  : 'wtt_depth',
            leaf   : 'wtt_is_leaf',
            path   : 'wtt_path',
            expand : 'wtt_expanded'
        },
        
        // item height
        itemSize: 32,
        
        // drag handle width
        dragSize: 16,
        
        // level width
        stepSize: 25,
        
        // gutter from left
        buffSize: 20,

        // scroll delay
        delay: 25,

        // leading & trailing rendered nodes
        buffer: 10,

        // node markup, can contains templating tags supported by jsRender
        markup: '<div class="bt-node bt-hbox {{:~last($last)}}" '+
                    'data-id="{{:id}}" '+
                    'data-level="{{:level}}" '+
                    'data-leaf="{{:leaf}}">'+
                    '{{for ~elbow(#data)}}'+
                        '<div class="bt-node-elbow {{:type}}">{{:icon}}</div>'+
                    '{{/for}}'+
                    '<div class="bt-node-body bt-flex bt-hbox">'+
                        '<div class="bt-drag"></div>'+
                        '<div class="bt-text bt-flex bt-hbox">{{:text}}</div>'+
                        '<div class="bt-plugin"></div>'+
                        '<div class="bt-trash"></div>'+
                    '</div>'+
                '</div>'
    };

    /**
     * Prototype
     */
    BigTree.prototype = {

        init: function(options) {

            this.options = $.extend(true, {}, BigTree.defaults, options || {});
            this.data = [];
            this.indexes = {};
            this.orphans = [];
            this.visdata = [];
            this.moving = {data: null, desc: [], orig: null};

            this.parent_ = null;
            this.orphan_ = [];

            this.initComponent();
            this.initEvents();

            this.fireEvent('init');
        },

        initComponent: function() {
            var options = this.options,
                params = options.params;

            this.element.addClass('bigtree').attr('tabindex', 1);

            this.editor = $('<div class="bt-editor"><input type="text"></div>');
            this.edtext = this.editor.children('input');

            this.grid   = $('<div class="bt-grid">').appendTo(this.element);

            // init template
            $.templates({
                btnode: {
                    markup: options.markup,
                    helpers: {
                        last: function($last) {
                            return $last ? 'bt-last' : '';
                        },
                        elbow: function(data) {
                            var lines = [],
                                level = +data[params.level],
                                expanded = +data[params.expand] === 1,
                                isparent = +data[params.leaf] === 0,
                                pdata = data.$parent,
                                elbow = [];

                            while(pdata) {
                                lines[pdata[params.level]] = pdata.$last ? 0 : 1;
                                pdata = pdata.$parent;
                            }

                            for (var i = 0; i <= level; i++) {
                                var type = '', 
                                    icon = '';

                                if (i == level) {
                                    type = 'elbow-end';
                                    if (isparent) {
                                        var cls = expanded ? 'elbow-minus' : 'elbow-plus';
                                        icon = '<span class="elbow-expander '+cls+'"></span>';
                                    }
                                } else {
                                    type = lines[i] == 1 ? 'elbow-line' : '';
                                }

                                elbow.push({
                                    type: type,
                                    icon: icon
                                });
                            }
                            return elbow;
                        }
                    }
                }
            });

            // init sortable
            this.element.sortable({
                items: '.bt-node',
                handle: '.bt-drag',
                placeholder: 'bt-node-sortable ui-sortable-placeholder'
            });

        },

        initEvents: function() {
            var options = this.options,
                lasttop = this.element.scrollTop(),
                lastdir = '',
                scroll = 0;

            this.element
                .off('scroll.bt')
                .on('scroll.bt', $.throttle(options.delay, $.proxy(function(){
                    var currtop = this.element.scrollTop(),
                        currdir = currtop > lasttop ? 'down' : 'up';

                    scroll = lastdir != currdir ? 0 : (scroll + Math.abs(currtop - lasttop));

                    if (scroll === 0 || scroll >= (options.buffer * options.itemSize)) {
                        this.render();
                        scroll = 0;
                    }

                    lasttop = currtop;
                    lastdir = currdir;
                }, this)));

            // expander click
            this.element
                .off('click.bt.expander')
                .on('click.bt.expander', '.elbow-expander', $.proxy(function(e){
                    e.stopPropagation();
                    var node = $(e.currentTarget).closest('.bt-node'),
                        data = this.data[this.indexes[node.attr('data-id')]];
                    if (data) {
                        if (data[options.params.expand] == '1') {
                            this.collapse(data);
                        } else {
                            this.expand(data);
                        }
                    }
                }, this));

            // navigation
            this.element
                .off('keydown.bt')
                .on('keydown.bt', $.proxy(this.navigate, this));

            // handle dragdrop event
            this.element
                .off('sortstart.bt')
                .on('sortstart.bt', $.proxy(function(e, ui){
                    this.initMovement(ui.item);
                }, this));

            this.element
                .off('sortstop.bt')
                .on('sortstop.bt', $.proxy(function(e, ui){
                    this.applyMovement(ui.item, ui.position.left);
                }, this));
            
            // selection
            this.element
                .off('click.bt.select')
                .on('click.bt.select', $.proxy(function(){
                    this.deselectAll();
                }, this));

            // text edit
            this.element
                .off('click.bt.startedit')
                .on('click.bt.startedit', '.bt-text', $.proxy(function(e){
                    e.stopPropagation();
                    var node = $(e.currentTarget).closest('.bt-node');
                    this.startEdit(node);
                }, this));

            // editor event
            this.edtext
                .off('click.bt')
                .on('click.bt', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                });

            this.edtext
                .off('keypress.bt')
                .on('keypress.bt', $.proxy(function(e){
                    if (e.keyCode == 13) {
                        e.preventDefault();
                        this.stopEdit(false);
                    }
                }, this));

        },

        hasScroll: function() {
            return this.element[0].scrollHeight > this.element.height();
        },

        load: function(data) {
            var fields = this.options.fields,
                offset = this.data.length,
                size = data.length,
                start,
                end;

            data = data || [];

            var params = this.options.params,
                start = this.data.length,
                dlen,
                i;

            data = data || [];
            dlen = data.length;

            // append to existing
            this.data.push.apply(this.data, data);

            // build index
            for (
                i = dlen;
                i--;
                this.indexes[data[i][params.id]] = i + start
            );

            var fields = this.options.fields,
                start = 0,
                stop = this.data.length;



            for (i = start; i < stop; i++) {
                var current = this.data[i],
                    lft = +current[fields.left],
                    rgt = +current[fields.right];

                if (this.parent_ && rgt < +this.parent_[fields.right]) {
                    current.parent_ = this.parent_;
                    current.last_ = rgt === +current.parent_[fields.right] - 1;
                    current.parent_.child.push(current[fields.id]);
                } else {
                    var tail = this.orphan_[this.orphan_.length - 1];
                    if (tail) {
                        this.data[this.indexes[tail]].last_ = false;
                    }
                    current.last_ = true;
                    this.orphan_.push(current[fields.id]);
                }
                
                if (rgt - lft > 1) {
                    this.parent_ = current;
                    this.parent_.child = [];
                }
            }

            for (i = start; i < stop; i++) {
                var current = this.data[i]
                console.log(current.parent_ && current.parent_.child);
            }

            // build tree
            for (i = 0; i < dlen; i++) {
                var curr = this.data[i + start],
                    ckey = curr[params.id],
                    path = curr[params.path].split('/'),
                    pkey,
                    lkey,
                    ldat;

                path.pop();
                pkey = path.pop();

                curr.$parent = null;

                if (pkey) {
                    var pdat = this.data[this.indexes[pkey]];
                    if (pdat) {
                        pdat.$child = pdat.$child || [];

                        lkey = pdat.$child[pdat.$child.length - 1];
                        ldat = this.data[this.indexes[lkey]];
                            
                        if (ldat) {
                            ldat.$last = false;
                        }

                        curr.$last   = true;
                        curr.$hidden = pdat[params.expand] == '0' || pdat.$hidden;
                        curr.$parent = pdat;
                        
                        pdat.$child.push(ckey);
                    }
                } else {

                    lkey = this.orphans[this.orphans.length - 1];
                    ldat = this.data[this.indexes[lkey]];

                    if (ldat) {
                        ldat.$last = false;
                    }
                            
                    curr.$last = true;
                    curr.$hidden = false;

                    this.orphans.push(ckey);
                }
            }

        },

        reindex: function() {
            var params = this.options.params;
            this.indexes = {};
            for (
                var i = this.data.length;
                i--;
                this.indexes[this.data[i][params.id]] = i
            );
        },

        render: function() {
            
            var stop = this.grid.scrollTop(),
                ptop = this.grid.position().top,
                buff = this.options.buffer * this.options.itemSize,
                spix = stop - ptop - buff,
                epix = spix + this.element.height() + buff * 2,
                data = $.grep(this.data, function(d){ return !d.$hidden; });
            
            spix = spix < 0 ? 0 : spix;

            var begidx = Math.floor(spix / this.options.itemSize),
                endidx = Math.ceil(epix / this.options.itemSize),
                padtop = this.options.itemSize * begidx,
                padbtm = this.options.itemSize * data.slice(endidx).length + 3 * this.options.itemSize;

            this.grid.css({
                paddingTop: padtop,
                paddingBottom: padbtm
            });
            
            // this.tickStart('render');
            this.renderRange(data, begidx, endidx);
            // this.tickStop('render');
        },

        renderRange: function(data, start, end) {
            var range = data.slice(start, end),
                params = this.options.params,
                moved = this.movedNode();

            this.fireEvent('beforenodesrender');

            this.editor.detach();
            this.removableNodes().remove();

            if (moved.length) {
                range = $.grep(range, function(d){
                    return d[params.id] != moved.attr('data-id');
                });
            }

            this.visdata = range;
            this.grid.append($.templates.btnode(range));
            this.element.focus();

            if (moved.length) {
                this.element.sortable('refresh');
            } else {
                this.decorate();
            }

            var visdata = this.visdata,
                visnode = this.visibleNodes();

            this.fireEvent('nodesrender', visnode, visdata);
        },

        decorate: function() { 
            if (this.selected) {
                var snode = this.grid.find('.bt-node[data-id='+this.selected+']');
                if (snode.length) this.select(snode);
            }
        },

        /**
         * Check if data is new (not saved yet)
         */
        isphantom: function(data) {
            return this.indexes[data[this.options.params.id]] === undef;
        },

        isparent: function(data) {
            return +data[this.options.params.leaf] === 0;
        },

        isleaf: function(data) {
            return +data[this.options.params.leaf] === 1;
        },

        /**
         * Get visible or rendered data
         */
        visible: function() {
            return this.visdata;
        },

        /** @private */
        create: function(spec) {
            if ( ! spec) 
                throw new Error("create(): data spec doesn't meet requirement");

            var data = {}, node, prop;

            for (prop in this.options.params) {
                data[prop] = spec[prop] || '';
            }
            
            return {
                node: $.templates.btnode(data),
                data: data
            }
        },

        after: function(curr, data) {
            if (this.isphantom(curr)) 
                throw new Error("after(): current data doesn't exist!");

            if (this.isphantom(data)) {
                // create
            } else {
                // just move
            }

        },

        before: function(target, data) {

        },

        /**
         * Remove data from collection
         */
        remove: function(data) {
            data = data || {};
        },

        /**
         * Update data using provided spec
         */
        update: function(data, spec) {
            data = data || {};
            spec = spec || {};
        },

        get: function(index) {
            return this.data[index];
        },

        index: function(data) {
            var index = this.indexes[data[this.options.params.id]];
            return isNaN(index) ? -1 : index;
        },

        level: function(data) {
            return +data[this.options.params.level];
        },

        first: function() {
            return this.data[0];
        },

        last: function() {
            return this.data[this.data.length - 1];
        },

        parent: function(data) {
            return data.$parent;
        },

        prev: function(data) {
            return this.nearest(data, -1);
        },

        next: function(data) {
            return this.nearest(data, 1);
        },

        descendants: function(data) {
            var arr = [];

            function cascade(data) {
                var child = (data.$child || []).slice(0);
                while(child.length) {
                    var key = child.shift(),
                        idx = this.indexes[key],
                        row = this.data[idx];

                    if (row) {
                        arr.push(row);    
                        if (row.$child !== undef) {
                            cascade.call(this, row);
                        }
                    }
                }    
            }

            cascade.call(this, data);
            return arr;
        },

        children: function(data) {
            var child = data.$child || [],
                len = child.length,
                arr = [];

            for (var i = 0; i < len; i++) {
                var idx = this.indexes[child[i]],
                    row = this.data[idx];
                if (row) {
                    arr.push(row);
                }
            }

            return arr;
        },

        nearest: function(data, offset) {
            var params = this.options.params,
                pdata = data.$parent,
                key = data[params.id];
            if (pdata) {
                var pchild = pdata.$child || [],
                    cindex = indexof(pchild, key);
                return this.data[this.indexes[pchild[cindex + offset]]] || null;
            } else {
                var index = this.indexes[key],
                    level = +data[params.level],
                    near,
                    runlev;

                index = index + offset;
                near  = this.data[index];

                while(near && (runlev = +near[params.level]) >= level) {
                    if (runlev == level) break;
                    index = index + offset;
                    near = this.data[index];
                }
                return near || null;
            }
        },

        movedNode: function() {
            return this.grid.children('.ui-sortable-helper');
        },

        visibleNodes: function() {
            return this.grid.children('.bt-node:not(.ui-sortable-placeholder)');
        },

        removableNodes: function() {
            return this.grid.children('.bt-node:not(.ui-sortable-helper,.ui-sortable-placeholder)');
        },

        selectedNode: function() {
            var node = $({});
            if (this.selected) {
                node = this.grid.children('.bt-node[data-id=' + this.selected + ']');
            }
            return node.length ? node : null;
        },

        nodeKey: function(node) {
            return make(node).attr('data-id');
        },

        nodeData: function(node) {
            var key = this.nodeKey(node),
                idx = this.indexes[key];
            return this.data[idx];
        },

        cascade: function() {

        },

        expand: function(data) {
            var params = this.options.params,
                fshow = function(data) {
                    var ds = this.children(data),
                        dz = ds.length;
                    for (var i = 0; i < dz; i++) {
                        ds[i].$hidden = false;
                        if (ds[i].$child !== undef && ds[i][params.expand] == '1') {
                            fshow.call(this, ds[i]);
                        }
                    }    
                };

            data[params.expand] = '1';
            fshow.call(this, data);

            this.fireEvent('expand', data);
            this.render();
        },

        collapse: function(data) {
            var params = this.options.params,
                fhide = function(data) {
                    var ds = this.children(data),
                        dz = ds.length;
                    for (var i = 0; i < dz; i++) {
                        ds[i].$hidden = true;
                        if (ds[i].$child !== undef && ds[i][params.expand] == '1') {
                            fhide.call(this, ds[i]);
                        }
                    }     
                };

            data[params.expand] = '0'; 
            fhide.call(this, data);

            this.fireEvent('collapse', data);
            this.render();
        },

        expandAll: function() {

        },

        collapseAll: function() {

        },

        toggle: function(node, silent, force) {
            var expander = node.find('.elbow-expander');
            silent = silent === undef ? true : silent;
            if (expander.length) {
                if (silent) {
                    // just update style
                    var state = expander.hasClass('elbow-plus') ? 'elbow-minus' : 'elbow-plus';
                    if (force !== undef) {
                        state = force == 'expand' ? 'elbow-minus' : 'elbow-plus';
                    }
                    expander.removeClass('elbow-plus elbow-minus').addClass(state); 
                } else {
                    // perform expand/collapse
                }
            }
        },

        /** @private */
        select: function(node) {
            this.selected = node.attr('data-id');
            node.addClass('bt-selected');
        },

        /** @private */
        deselect: function(node) {
            this.selected = null;
            node.removeClass('bt-selected');
        },

        /** @private */
        deselectAll: function() {
            this.selected = null;
            this.grid.children('.bt-selected').removeClass('bt-selected');
        },

        selection: function() {
            var node = this.grid.children('.bt-selected');
            return node.length ? this.data[this.indexes[this.selected]] : null;
        },

        /** @private */
        startEdit: function(node) {
            var data = this.data[this.indexes[node.attr('data-id')]],
                params = this.options.params,
                holder = node.find('.bt-text'),
                text = data[params.text];

            // remove query hightlight
            if (data.$orig && data.$orig.text) {
                text = data.$orig.text;
            }

            // drop previous editing
            this.stopEdit(true);

            // ensure selection
            this.select(node);

            // place editor
            this.editor.appendTo(holder);
            this.edtext.val(text).focus();

            // defer text select
            var defer = $.debounce(1, function(){
                seltext(this.edtext, text.length);
            });

            defer.call(this);
        },

        /** @private */
        stopEdit: function(deselect) {
            var params = this.options.params,
                node = this.editor.closest('.bt-node');
                
            if (node.length) {
                var data = this.data[this.indexes[node.attr('data-id')]],
                    text = this.edtext.val(),
                    orig = data[params.text],
                    disp = text;

                if (data.$orig && data.$orig.text) {
                    orig = data.$orig.text;
                    disp = data.$orig.disp;
                }

                data[params.text] = disp;
                deselect = deselect === undef ? true : deselect;
                
                if (deselect) {
                    this.deselect(node);
                    // remove editor
                    this.editor.detach();
                    node.find('.bt-text').html(disp);
                }

                if (text != orig) {
                    this.fireEvent('edit', data, text);
                }
            } else {
                // manual deselect...
                this.selected = null;
                this.grid.find('.bt-selected').removeClass('bt-selected');
            }

        },

        search: function(query) {

            var params = this.options.params,
                regex = new RegExp('('+query+')', 'igm'),
                size = this.data.length,
                text,
                data,
                disp,
                i;

            for (i = 0; i < size; i++) {

                data  = this.data[i];

                // reset first
                if (data.$orig) {
                    data.$hidden = data.$orig.hidden;
                    
                    data[params.expand] = data.$orig.expand;
                    data[params.text] = data.$orig.text;

                    delete data.$orig;
                }

                if (query) {
                    text = data[params.text];

                    data.$orig = {
                        hidden: data.$hidden,
                        expand: data[params.expand],
                        text: text
                    };

                    var found = regex.exec(text);

                    data.$hidden = true;

                    if (found) {

                        disp = data[params.text].replace(
                            found[1], 
                            '<span class="bt-hightlight">'+found[1]+'</span>'
                        );

                        data.$hidden = false;
                        data.$orig.disp = disp;
                        data[params.text] = disp;

                        var pdat = data.$parent;

                        while(pdat) {
                            if (pdat.$hidden) {
                                pdat.$hidden = false;
                            }
                            pdat[params.expand] = '1';
                            pdat = pdat.$parent;
                        }
                    }
                }

            }

            regex = null;
            this.render();
        },

        /**
         * Move data programmaticaly
         * impact to node movement
         */
        move: function(type, data, dest, orig) {
            var opts = this.options,
                prop = opts.params,
                xnod = this.grid.children('.bt-node[data-id='+data[prop.id]+']'),
                ynod = this.grid.children('.bt-node[data-id='+dest[prop.id]+']'),
                adds = 5;

            // validate destination
            var vdat = this.next(data);

            if (vdat) {
                var vidx, yidx;
                vidx = this.indexes[vdat[prop.id]];
                yidx = this.indexes[dest[prop.id]];
                if (yidx < vidx) {
                    throw new Error("move(): can't move parent to his children!");
                    return;
                }
            }

            if (ynod.length) {
                var pos;
                
                if ( ! xnod.length) {
                    xnod = $.templates.btnode(data);
                }

                this.initMovement(xnod);

                switch(type) {
                    case 'append':
                        ynod.after(xnod);
                        pos = +dest[prop.level] * opts.stepSize + opts.buffSize + opts.dragSize + adds;
                    break;

                    case 'after':
                        ynod.after(xnod);
                        pos = +dest[prop.level] * opts.stepSize + opts.buffSize + adds;
                    break;

                    case 'before':
                        ynod.before(xnod);
                        pos = +dest[prop.level] * opts.stepSize + opts.buffSize + adds;
                    break;
                }
                this.applyMovement(xnod, pos);
            } else {
                var npos;
                switch(type) {
                    case 'append':
                        npos = +dest[prop.right];
                    break;

                    case 'after':
                        npos = +dest[prop.left] + (+dest[prop.right] - (+dest[prop.left])) + 1 + 2;
                    break;

                    case 'before':
                        npos = +dest[prop.left];
                    break;
                }
                this.operation(data, npos, orig);
            }
            
        },

        /** @private */
        initMovement: function(node) {
            var params = this.options.params,
                data = this.nodeData(node);
            
            // autoselect
            this.deselectAll();
            this.select(node);

            // add moving state
            node.addClass('bt-moving');

            // we have to detach from collection
            if (data) {
                var isparent = data[params.leaf] == '0',
                    expanded = data[params.expand] == '1',
                    desc = (isparent && this.descendants(data)) || [],
                    size = desc.length,
                    pdat = data.$parent,
                    key = data[params.id],
                    idx = this.indexes[key];

                // reset
                this.moving.data = this.data.splice(idx, 1)[0];
                this.moving.desc = [];

                this.moving.orig = {
                    '$index': null,
                    '$parent': null,
                    '$posidx': null,
                    '$prev': null
                };

                if (size) {
                    this.moving.desc = this.data.splice(idx, size);
                    if (expanded) {
                        this.toggle(node, true, 'collapse');
                        var attrs = desc.map(function(d){return '[data-id='+d[params.id]+']';}).join(',');
                        this.grid.find(attrs).remove();
                    }
                }

                this.moving.orig.$index = idx;
                this.moving.orig.$prev = this.prev(data);

                if (pdat) {
                    var posidx = indexof(pdat.$child, key);
                    
                    this.moving.data.$parent = null;
                    this.moving.orig.$parent = pdat;
                    this.moving.orig.$posidx = posidx;

                    pdat.$child.splice(posidx, 1);
                    
                    if ( ! pdat.$child.length) {
                        pdat.$child = undefined;
                        pdat[params.leaf] = '1';
                    }
                }

                this.reindex();
            }
        },

        /** @private */
        applyMovement: function(node, offset, silent) {
            var options = this.options,
                params = options.params,
                prev = node.prev('.bt-node'),
                next = node.next('.bt-node'),
                oidx = -1;

            node.removeClass('bt-moving');

            silent = silent === undef ? false : silent;

            if (this.moving.data) {
                
                // take advantages by ommiting `prev` index calculation
                if (next.length) {
                    oidx = this.indexes[next.attr('data-id')];
                } else if (prev.length) {
                    var xkey = prev.attr('data-id'),
                        xidx = this.indexes[xkey],
                        xdat = this.data[xidx];

                    if (xdat[params.leaf] == '0' && xdat[params.expand] == '0') {
                        var xdes = this.descendants(xdat);
                        oidx = xidx + xdes.length + 1;
                    } else {
                        oidx = xidx + 1;
                    }
                }

                this.data.splice(oidx, 0, this.moving.data);

                if (this.moving.desc.length) {
                    oidx++;
                    Array.prototype.splice.apply(
                        this.data, 
                        [oidx, 0].concat(this.moving.desc)
                    );
                }

                this.reindex();

                var currkey = this.moving.data[params.id],
                    curridx = this.indexes[currkey],
                    currdat = this.data[curridx],
                    currlev = +currdat[params.level],
                    datalev = null,
                    posleft;

                // fixup origin
                if (this.moving.orig.$parent) {
                    var echild = this.moving.orig.$parent.$child || [];
                    if (echild.length) {
                        var enddat = this.data[this.indexes[echild[echild.length - 1]]];
                        if (enddat) enddat.$last = true;
                    }
                } else if (this.moving.orig.$prev) {
                    if (currdat.$last) this.moving.orig.$prev.$last = true;
                }

                posleft = offset - options.buffSize;

                // setup new position
                if (posleft < -options.dragSize) { // to left
                    datalev = currlev - (Math.round(Math.abs(posleft) / options.stepSize));
                } else if (posleft > options.dragSize) { // to right
                    datalev = currlev + (Math.round(posleft / options.stepSize));
                } else { // none
                    datalev = currlev;
                }

                datalev = datalev < 0 ? 0 : datalev;

                var prevkey, previdx, prevdat, prevlev, prevpos,
                    nextkey, nextidx, nextdat;
                    
                if (prev.length) {
                    prevkey = prev.attr('data-id');
                    previdx = this.indexes[prevkey];
                    prevdat = this.data[previdx];
                    prevlev = +prevdat[params.level];
                    
                    if (prevdat[params.leaf] == '1') {
                        if (datalev > prevlev) {
                            // as new child
                            currdat.$parent = prevdat;
                            currdat.$last = true;
                            currdat[params.level] = prevlev + 1;

                            prevdat.$child = [currkey];

                            if (prevdat.$parent) {
                                prevdat.$last = indexof(prevdat.$parent.$child, prevkey) == prevdat.$parent.$child.length - 1;
                            } else {
                                prevdat.$last = ! this.next(prevdat);
                            }
                            prevdat[params.leaf] = '0';
                            prevdat[params.expand] = '1';

                        } else if (datalev == prevlev) {
                            // as sibling
                            if (prevdat.$parent) {
                                prevpos = indexof(prevdat.$parent.$child, prevkey);
                                currdat.$parent = prevdat.$parent;
                                currdat.$last = prevpos == prevdat.$parent.$child.length - 1;
                                currdat[params.level] = datalev;
                                
                                prevdat.$last = false;
                                prevdat.$parent.$child.splice(prevpos + 1, 0, currkey);
                            } else {
                                currdat.$parent = null;
                                currdat.$last = ! this.next(currdat);
                                currdat[params.level] = prevlev;
                                prevdat.$last = false;
                            }
                        } else {
                            // ugh... bubbling
                            this.bubbleMove(currdat, curridx, datalev, previdx);
                        }
                    } else {
                        if (prevdat[params.expand] == '0') {
                            if (datalev > prevlev || datalev == prevlev) {
                                // as sibling
                                if (prevdat.$parent) {
                                    prevpos = indexof(prevdat.$parent.$child, prevkey);
                                    currdat.$parent = prevdat.$parent;
                                    currdat.$last = prevpos == prevdat.$parent.$child.length - 1;
                                    currdat[params.level] = prevlev;

                                    prevdat.$last = false;
                                    prevdat.$parent.$child.splice(prevpos + 1, 0, currkey);
                                } else {
                                    currdat.$parent = null;
                                    currdat.$last = this.data[curridx + 1] === false;
                                    currdat[params.level] = prevlev;   
                                }
                            } else {
                                // ugh... bubbling
                                this.bubbleMove(currdat, curridx, datalev, previdx);
                            }
                        } else {
                            if (datalev > prevlev) {
                                // as fists child
                                currdat.$parent = prevdat;
                                currdat.$last = false;
                                currdat[params.level] = prevlev + 1;
                                prevdat.$child.unshift(currkey);
                            } else {
                                this.bubbleMove(currdat, curridx, datalev, previdx);
                            }
                        }
                    }
                } else if (next.length) {
                    nextkey = next.attr('data-id');
                    nextidx = this.indexes[nextkey];
                    nextdat = this.data[nextidx];

                    currdat.$parent = nextdat.$parent;
                    currdat.$last = false;
                    currdat[params.level] = nextdat[params.level];
                } else {
                    currdat.$parent = null;
                    currdat.$last = true;
                    currdat[params.level] = 0;
                }

                if (this.moving.desc) {
                    var dlen = this.moving.desc.length, 
                        width = +currdat[params.level] - currlev,
                        i;
                    for (i = 0; i < dlen; i++) {
                        this.moving.desc[i][params.level] = +this.moving.desc[i][params.level] + width;
                    }
                }

                if ( ! silent) 
                    this.render();

                // check whole changes
                var changed = currdat.$parent != this.moving.orig.$parent || 
                              this.indexes[currkey] !== this.moving.orig.$index;

                // broadcast change
                if (changed) {
                    
                    // perfom operation
                    var orig = this.moving.orig,
                        curr = currdat,
                        args = [],
                        dest,
                        npos;

                    if ((dest = this.next(curr))) {
                        npos = +dest[params.left];
                    } else if ((dest = this.prev(curr))) {
                        npos = +dest[params.left] + (+dest[params.right] - (+dest[params.left])) + 1 + 2;
                    } else if ((dest = curr.$parent)) {
                        npos = +dest[params.right];
                    }

                    args = [curr, npos, orig];

                    this.operation.apply(this, args);

                    if ( ! silent) 
                        this.fireEvent.apply(this, ['move'].concat(args));
                }
                
                this.moving.data = null;
                this.moving.desc = [];
                this.moving.orig = null;

            }
            
        },

        /** @private */
        bubbleMove: function(currdat, curridx, offlev, offidx) {
            var params = this.options.params,
                currkey = currdat[params.id],
                bubble = this.data[offidx],
                prevs = [],
                stop = offlev - 1,
                bublev;

            while(bubble) {
                bublev = +bubble[params.level];
                if (bublev == offlev) 
                    prevs.push(bubble);
                if (bublev == stop) 
                    break;
                bubble = this.data[offidx--];
            }

            var invalid = false,
                isparent = currdat[params.leaf] == '0',
                desc = this.descendants(currdat),
                dlen = desc.length,
                next,
                i;

            if (isparent) {
                if (currdat[params.expand] == '1') {
                    next = this.data[this.indexes[desc[dlen - 1][params.id]] + 1];
                } else {
                    next = this.data[curridx + 1];
                }
            } else {
                next = this.data[curridx + 1];
            }

            if (next) {
                invalid = +next[params.level] > offlev;
            }
            
            if ( ! invalid) {
                var prevlen = prevs.length;
                if (prevlen)
                    for (i = 0; i < prevlen; i++) 
                        prevs[i].$last = false;

                if (bubble) {
                    currdat.$parent = bubble;
                    bubble.$child = bubble.$child || [];
                    bubble.$child.splice(prevlen, 0, currkey);
                    currdat.$last = prevlen === bubble.$child.length - 1;
                    currdat[params.level] = offlev;
                } else {
                    currdat.$parent = null;
                    currdat[params.level] = 0;
                    
                    next = this.next(currdat);
                    if (next) currdat.$last = false;
                }
            } else {
                // hard reset
                var orig = this.moving.orig;


                if (orig.$parent) {
                    currdat.$parent = orig.$parent;
                    if (orig.$parent.$child === undef) {
                        orig.$parent.$child = [currkey];
                        orig.$parent[params.leaf] = '0';
                    } else {
                        orig.$parent.$child.splice(orig.$posidx, 0, currkey);
                    }
                }
                
                if (orig.$prev && currdat.$last) {
                    orig.$prev.$last = false;
                }

                desc.unshift(currdat);

                this.data.splice(curridx, desc.length);
                Array.prototype.splice.apply(this.data, [orig.$index, 0].concat(desc));
                this.reindex();
            }

            prevs = null;
        },

        /**
         * Swap item
         * @private
         */
        swap: function(from, to, reindex) {
            var size = this.data.length, tmp, i;
            if (from != to && from >= 0 && from <= size && to >= 0 && to <= size) {
                tmp = this.data[from];
                if (from < to) {
                    for (i = from; i < to; i++) {
                        this.data[i] = this.data[i+1];
                    }
                } else {
                    for (i = from; i > to; i--) {
                        this.data[i] = this.data[i-1];
                    }
                }

                this.data[to] = tmp;

                reindex = reindex === undef ? true : reindex;
                if (reindex) this.reindex();
            }
        },
        
        operation: function(data, npos, orig) {

            var params = this.options.params,
                clsreg = new RegExp('.*(?=' + (orig.$parent ? '/' : '') + data[params.id] + '/?)'),
                trmreg = new RegExp('^/');

            // short var for readibility
            var p = npos, 
                l = +data[params.left], 
                r = +data[params.right],
                j = this.data.length,
                s = this.indexes[data[params.id]],
                t = s + (this.descendants(data) || []).length,
                x,
                y, 
                i

            for (i = 0; i < j; i++) {

                x = +this.data[i][params.left];
                y = +this.data[i][params.right];

                if (r < p || p < l) {
                    if (p > r) {
                        if (r < x && x < p) {
                            x += l - r - 1;
                        } else if (l <= x && x < r) {
                            x += p - r - 1;
                        } else {
                            x += 0;
                        }

                        if (r < y && y < p) {
                            y += l - r - 1;
                        } else if (l < y && y <= r) {
                            y += p - r - 1;
                        } else {
                            y += 0;
                        }
                    } else {
                        if (p <= x && x < l) {
                            x += r - l + 1;
                        } else if (l <= x && x < r) {
                            x += p - l;
                        } else {
                            x += 0;
                        }

                        if (p <= y && y < l) {
                            y += r - l + 1;
                        } else if (l < y && y <= r) {
                            y += p - l;
                        } else {
                            y += 0;
                        }
                    }

                    this.data[i][params.left]  = x;
                    this.data[i][params.right] = y;

                    if (i >= s && i <= t) {
                        this.data[i][params.path] = 
                            (data.$parent ? data.$parent[params.path] + '/' : '') + 
                                this.data[i][params.path].replace(clsreg, '').replace(trmreg, '');
                    }

                }

            }
            
            clsreg = null;
            trmpath = null;

        },

        // internal function
        navigate: function(e) {
            var code = e.keyCode || e.which;
            
            if (code == 9 || code == 38 || code == 40) {
                var node = this.grid.find('.bt-selected'),
                    next,
                    prev;

                e.preventDefault();

                if (node.length) {

                    switch(code) {
                        // tab
                        case 9:
                            var method = e.shiftKey ? 'prev' : 'next',
                                target = node[method].call(node);
                            if (target.length) this.startEdit(target);
                        break;
                        // up
                        case 38:
                            prev = node.prev('.bt-node');
                            if (prev.length) this.startEdit(prev);
                        break;
                        // down
                        case 40:
                            next = node.next('.bt-node');
                            if (next.length) this.startEdit(next);
                        break;

                    }    
                }

            }
            
        },

        maps: function() {
            var args = $.makeArray(arguments),
                size = args.length,
                maps = this.data.map(function(d){
                    var t = [], i = 0;
                    for (; i < size; i++) t.push(d[args[i]]);
                    return t;
                });
            console.log(maps);
        },

        plugin: function() {
            return this;
        },

        tickStart: function(name) {
            this.markers = this.markers || {};
            name = name === undef ? '_' : name;
            this.markers[name] = new Date();
        },

        tickStop: function(name) {
            this.markers = this.markers || {};
            name = name === undef ? '_' : name;
            if (this.markers[name] !== undef) {
                var elapsed = ((new Date() - this.markers[name]) / 1000) + 'ms';
                console.log(name + ': ' + elapsed);
            }
        },

        fireEvent: function() {
            var args = $.makeArray(arguments),
                name = (args.shift()) + '.bt';
            this.element.trigger(name, args);
        },

        destroy: function(remove) {
            this.edtext.off('.bt');
            this.element.off('.bt');
            this.element.sortable('destroy');

            $.removeData(this.element.get(0), 'bigtree');

            this.data = null;
            this.indexes = null;
            this.orphans = null;
            this.selected = null;

            if (remove !== undef && remove === true) {
                this.editor.remove();
                this.element.remove();
            }
        }

    };

    $.fn.bigtree = function(options) {
        var args = $.makeArray(arguments),
            init = $.type(args[0]) !== 'string',
            list,
            func;

        list = this.each(function(){
            var obj = $.data(this, 'bigtree');
            
            if ( ! obj) {
                $.data(this, 'bigtree', (obj = new BigTree(this, options)));
            }

            if ( ! init) {
                var method = args.shift();
                if ($.isFunction(obj[method])) {
                    func = obj[method].apply(obj, args);    
                } else {
                    throw Error(method + ' is not function!');
                }
            }
        });

        return init ? list : func;
    };

}(jQuery));