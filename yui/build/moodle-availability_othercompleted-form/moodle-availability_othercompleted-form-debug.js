YUI.add('moodle-availability_othercompleted-form', function (Y, NAME) {

    /**
     * JavaScript for form editing other completion conditions.
     *
     * @module moodle-availability_othercompleted-form
     */
    M.availability_othercompleted = M.availability_othercompleted || {};
    
    /**
     * @class M.availability_othercompleted.form
     * @extends M.core_availability.plugin
     */
    M.availability_othercompleted.form = Y.Object(M.core_availability.plugin);
    
    /**
     * Initialises this plugin.
     *
     * @method initInner
     * @param {Array} datcms Array of objects containing cmid => name
     */
    M.availability_othercompleted.form.initInner = function(datcms) {
        this.datcms = datcms;
    };
    
    M.availability_othercompleted.form.getNode = function(json) {
        // Create HTML structure.
        var html =  '<span class="col-form-label p-r-1"> ' + M.util.get_string('title', 'availability_othercompleted') + '</span>' +
                    '<span class="availability-group form-group"><label>' +
                    '<span class="accesshide">' + M.util.get_string('label_cm', 'availability_othercompleted') + ' </span>' +
                    '<select class="custom-select" name="cm" title="' + M.util.get_string('label_cm', 'availability_othercompleted') + '">' +
                    '<option value="0">' + M.util.get_string('choosedots', 'moodle') + '</option>';
        for (var i = 0; i < this.datcms.length; i++) {
            var cm = this.datcms[i];
            // String has already been escaped using format_string.
            html += '<option value="' + cm.id + '">' + cm.name + '</option>';
        }
        html += '</select></label> <label><span class="accesshide">' +
                    M.util.get_string('label_completion', 'availability_othercompleted') +
                ' </span><select class="custom-select" ' +
                                'name="e" title="' + M.util.get_string('label_completion', 'availability_othercompleted') + '">' +
                '<option value="1">' + M.util.get_string('option_complete', 'availability_othercompleted') + '</option>' +
                '<option value="0">' + M.util.get_string('option_incomplete', 'availability_othercompleted') + '</option>' +
                '</select></label></span>';
        var node = Y.Node.create('<span class="form-inline">' + html + '</span>');
    
        // Set initial values.
        if (json.cm !== undefined &&
                node.one('select[name=cm] > option[value=' + json.cm + ']')) {
            node.one('select[name=cm]').set('value', '' + json.cm);
        }
        if (json.e !== undefined) {
            node.one('select[name=e]').set('value', '' + json.e);
        }
    
        // Add event handlers (first time only).
        if (!M.availability_othercompleted.form.addedEvents) {
            M.availability_othercompleted.form.addedEvents = true;
            var root = Y.one('.availability-field');
            root.delegate('change', function() {
                // Whichever dropdown changed, just update the form.
                M.core_availability.form.update();
            }, '.availability_othercompleted select');
        }
    
        return node;
    };
    
    M.availability_othercompleted.form.fillValue = function(value, node) {
        value.cm = parseInt(node.one('select[name=cm]').get('value'), 10);
        value.e = parseInt(node.one('select[name=e]').get('value'), 10);
    };
    
    M.availability_othercompleted.form.fillErrors = function(errors, node) {
        var cmid = parseInt(node.one('select[name=cm]').get('value'), 10);
        if (cmid === 0) {
            errors.push('availability_othercompleted:error_selectcmid');
        }
        var e = parseInt(node.one('select[name=e]').get('value'), 10);
        if (((e === 2) || (e === 3))) {
            this.datcms.forEach(function(cm) {
                if (cm.id === cmid) {
                    if (cm.completiongradeitemnumber === null) {
                        errors.push('availability_othercompleted:error_selectcmidpassfail');
                    }
                }
            });
        }
    };
    
    
    }, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
    