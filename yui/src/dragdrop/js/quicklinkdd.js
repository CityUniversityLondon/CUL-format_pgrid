/**
 * Quick link drag and drop.
 *
 * @class M.format_pgrid_dragdrop
 * @constructor
 */

var QUICKLINK = function() {
    QUICKLINK.superclass.constructor.apply(this, arguments);
};

Y.extend(QUICKLINK, M.core.dragdrop, {

    goingLeft: null,

    initializer: function() {
        // Set group for parent class.
        this.groups = [CSS.QUICKLINKDRAGGABLE];
        // Initialise quicklinks dragging.
        this.quicklinklistselector = '.' + CSS.QUICKLINK;

        if(Y.Node.all(this.quicklinklistselector).size()) {
            this.setup_for_quicklink(this.quicklinklistselector);
            this.samenodeclass = CSS.QUICKLINK;
            this.parentnodeclass = CSS.QUICKLINKCONTAINER;

            // Make each li element in the lists of quicklinks draggable.
            var del = new Y.DD.Delegate({
                container: '.' + CSS.QUICKLINKCONTAINER,
                nodes: '.' + CSS.QUICKLINKDRAGGABLE,
                target: true,
                handles: ['.' + CSS.MOVE],
                dragConfig: {groups: this.groups}
            });
            del.dd.plug(Y.Plugin.DDProxy, {
                // Don't move the node at the end of the drag.
                moveOnEnd: false,
                cloneNode: true
            });
            del.dd.plug(Y.Plugin.DDConstrained, {
                // Keep it inside the ul.
                constrain: '.' + CSS.QUICKLINKCONTAINER
            });
            del.dd.plug(Y.Plugin.DDWinScroll);
        }
    },

    /**
     * Apply dragdrop features to the specified selector or node that refers to quicklink(s)
     *
     * @method setup_for_quicklink
     * @param {String} baseselector The CSS selector or node to limit scope to
     */
    setup_for_quicklink: function(baseselector) {
        Y.Node.all(baseselector).each(function(quicklinknode) {
            // Replace move icons.
            var move = quicklinknode.one('a' + '.' + CSS.MOVE);

            if (move) {
                var str = move.get('text');
                move.replace(this.get_drag_handle(str,
                             CSS.MOVE, CSS.ICONCLASS, true));
            }
        }, this);
    },

    /*
     * Drag-dropping related functions
     */
    drag_start: function(e) {
        // Get our drag object.
        var drag = e.target;
        // Set some styles here.
        drag.get('dragNode').set('innerHTML', drag.get('node').get('innerHTML'));
    },

    drag_drag: function(e) {
        // Core dragdrop checks for goingUp but our list is fluid
        // so we also need to check goingLeft.
        var drag = e.target,
            info = e.info;

        // Check that drag object belongs to correct group.
        if (!this.in_group(drag)) {
            return;
        }

        // Note, we test both < and > situations here. We don't want to
        // effect a change in direction if the user is only moving up
        // and down with no X position change.

        // Detect changes in the position relative to the start point.
        if (info.start[0] < info.xy[0]) {
            this.absgoingleft = true;

        } else if (info.start[0] > info.xy[0]) {
            // Otherwise we're going right.
            this.absgoingleft = false;
        }

        // Detect changes in the position relative to the last movement.
        if (info.delta[0] < 0) {
            this.goingleft = true;

        } else if (info.delta[0] > 0) {
            // Otherwise we're going right.
            this.goingleft = false;
        }

        // Detect if we are going up or down.
        if (info.delta[1] != info.xy[1]) {
            this.vertical = true;
        } else {
            this.vertical = false;
        }
    },

    drag_dropmiss: function(e) {
        // Missed the target, but we assume the user intended to drop it
        // on the last last ghost node location, e.drag and e.drop should be
        // prepared by global_drag_dropmiss parent so simulate drop_hit(e).
        this.drop_hit(e);
    },

    drop_over: function(e) {
        // Get a reference to our drag and drop nodes.
        var drag = e.drag.get('node'),
            drop = e.drop.get('node'),
            where;

        if (this.goingleft) {
            where = 'before';
        } else {
            where = 'after';
        }

        if (this.goingup) {
            where = 'before';
        }

        // Add the node contents so that it's moved, otherwise only the drag handle is moved.
        drop.insert(drag, where);
    },

    drop_hit: function(e) {
        var drop = e.drop.get('node'),
            drag = e.drag.get('node'),
            params = {};

        // Add spinner if it not there.
        var actionarea = drag.one('.' + CSS.ACTIONAREA);
        var spinner = M.util.add_spinner(Y, actionarea);

        // Prepare request parameters
        params.sesskey = M.cfg.sesskey;
        params.courseid = this.get('courseid');
        params.moveto = drop.getData('position');
        params.copy = drag.getData('position');
        params.action = 2;
        params.name = drag.getData('setting');

        // Perform the AJAX request.
        var uri = M.cfg.wwwroot + this.get('ajaxurl');

        Y.io(uri, {
            method: 'POST',
            data: params,
            on: {
                start: function() {
                    // this.lock_drag_handle(drag, CSS.MOVE);
                    spinner.show();
                },
                success: function() {
                    // this.unlock_drag_handle(drag, CSS.MOVE);
                    window.setTimeout(function() {
                        spinner.hide();
                    }, 250);
                },
                failure: function(tid, response) {
                    this.ajax_failure(response);
                    // this.unlock_drag_handle(drag, CSS.MOVE);
                    spinner.hide();
                    // TODO: revert nodes location
                }
            },
            context: this
        });
    }

}, {
    NAME : 'format_pgrid_quicklinkdd',
        ATTRS: {
        courseid: {
            value: null
        },
        ajaxurl: {
            value: 0
        },
        config: {
            value: 0
        }
    }
});

M.format_pgrid = M.format_pgrid || {};
M.format_pgrid.init_quicklinkdd = function(params) {
    new QUICKLINK(params);
};


