
define([
	'dojo/_base/declare',
	'dojo/_base/lang',
	'app/common/modscript/controllers/Base',
	'app/worklistopt/modscript/views/Index'
], function(
	declare,
	lang,
	BaseController,
	IndexView
){

	var proxy = lang.hitch;

	var Class = declare([BaseController], {

		indexAction: function() {

			this.fetchModule().then(proxy(this, function(module){
				
				this.loadPage(IndexView, {
					module: module
				});

			}));
			
		}

	});

	return Class;

});