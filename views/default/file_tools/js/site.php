<?php

?>
//<script>
elgg.provide("elgg.file_tools");
elgg.provide("elgg.file_tools.uploadify");
elgg.provide("elgg.file_tools.tree");

// extend jQuery with a function to serialize to JSON
(function( $ ){
	$.fn.serializeJSON = function() {
		var json = {};
		jQuery.map($(this).serializeArray(), function(n, i){
			if (json[n['name']]){
				if (!json[n['name']].push) {
					json[n['name']] = [json[n['name']]];
				}
				json[n['name']].push(n['value'] || '');
			} else {
				json[n['name']] = n['value'] || '';
			}
		});
		return json;
	};
})( jQuery );

elgg.file_tools.uploadify.init = function(){
	$uploadifyButton = $('#uploadify-button-wrapper');
	
	if($uploadifyButton.length){
		$uploadifyButton.uploadify({
			swf: "<?php echo $vars["url"]; ?>mod/file_tools/vendors/uploadify/uploadify.swf",
			uploader: "<?php echo $vars["url"]; ?>mod/file_tools/procedures/upload/multi.php",
			height: 18,
			width: 100,
			buttonClass: "elgg-button elgg-button-action",
			buttonText: elgg.echo("file_tools:forms:browse"),
			queueID: "uploadify-queue-wrapper",
			fileTypeExts: "<?php echo file_tools_allowed_extensions(true); ?>",
			fileSizeLimit: "50MB",
			fileObjName: "upload",
			auto: false,
			onQueueComplete: function(queueData){
				var folder = $('#file_tools_file_parent_guid').val();
				
				document.location.href = file_tools_uploadify_return_url + "#" + folder;
			},
			onUploadStart: function(file){
				$('#uploadify-button-wrapper').uploadify("settings", "formData", $('#file-tools-file-upload-form').serializeJSON());
			},
			onUploadSuccess: function(file, data, response){
				data = $.parseJSON(data);
				
				if(data && data.system_messages){
					elgg.register_error(data.system_messages.error);
					elgg.system_message(data.system_messages.success);
				}
			},
			onUploadError: function(file, data, response){
				data = $.parseJSON(data);
				
				if(data && data.system_messages){
					elgg.register_error(data.system_messages.error);
					elgg.system_message(data.system_messages.success);
				}
			}
		});
	}
}

elgg.file_tools.uploadify.cancel = function(){
	$('#uploadify-button-wrapper').uploadify("cancel", "*");
}

elgg.file_tools.uploadify.upload = function(event){
	$('#uploadify-button-wrapper').uploadify("upload", "*");
	
	return false;
}

elgg.file_tools.tree.init = function(){
	$tree = $('#file-tools-folder-tree');

	if($tree.length){
		$tree.tree({
			rules: {
				multiple: false,
				drag_copy: false,
				valid_children : [ "root" ]
			},
			ui: {
				theme_name: "classic"
			},
			callback: {
				onload: function(tree){
					var hash = window.location.hash;

					if(hash){
						tree.select_branch($tree.find('a[href="' + hash + '"]'));
						tree.open_branch($tree.find('a[href="' + hash + '"]'));

						var folder_guid = hash.substr(1);
					} else {
						tree.select_branch($tree.find('a[href="#"]'));
						tree.open_branch($tree.find('a[href="#"]'));

						var folder_guid = 0;
					}

					elgg.file_tools.load_folder(folder_guid);
					
					$tree.show();
				},
				onselect: function(node, tree){
					var hash = $(node).find('a:first').attr("href").substr(1);

					window.location.hash = hash;
				},
				onmove: function(node, ref_node, type, tree_obj, rb){
					var parent_node = tree_obj.parent(node);

					var folder_guid = $(node).find('a:first').attr('href').substr(1);
					var parent_guid = $(parent_node).find('a:first').attr('href').substr(1);
										
					var order = [];
					$(parent_node).find('>ul > li > a').each(function(k, v){
						var guid = $(v).attr('href').substr(1);
						order.push(guid);
					});

					if(parent_guid == window.location.hash.substr(1)){
						$("#file_tools_list_files_container .elgg-ajax-loader").show();
					}
					
					elgg.action("file_tools/folder/reorder", {
						data: {
							folder_guid: folder_guid,
							parent_guid: parent_guid,
							order: order
						},
						success: function(){
							if(parent_guid == window.location.hash.substr(1)){
								elgg.file_tools.load_folder(parent_guid);
							}
						}
					});
				}
			}
		});
	}
}

elgg.file_tools.breadcrumb_click = function(event) {
	var href = $(this).attr("href");
	var hash = elgg.parse_url(href, "fragment");

	if(hash){
		window.location.hash = hash;
	} else {
		window.location.hash = "#";
	}

	event.preventDefault();
}

elgg.file_tools.load_folder = function(folder_guid){
	var query_parts = elgg.parse_url(window.location.href, "query", true);
	var search_type = 'list';
	
	if(query_parts && query_parts.search_viewtype){
		search_type = query_parts.search_viewtype;
	}
	
	var url = elgg.get_site_url() + "file_tools/list/" + elgg.get_page_owner_guid() + "?folder_guid=" + folder_guid + "&search_viewtype=" + search_type;

	$("#file_tools_list_files_container .elgg-ajax-loader").show();
	$("#file_tools_list_files_container").load(url, function(){
		var add_link = $('ul.elgg-menu-title li.elgg-menu-item-add a').attr("href");

		var path = elgg.parse_url(add_link, "path");
		var new_add_link = elgg.get_site_url() + path.substring(1) + "?folder_guid=" + folder_guid;
		
		$('ul.elgg-menu-title li.elgg-menu-item-add a').attr("href", new_add_link);
	});
}

elgg.file_tools.select_all = function(e){
	e.preventDefault();

	if($(this).find("span:first").is(":visible")){
		// select all
		$('#file_tools_list_files input[type="checkbox"]').attr("checked", "checked");
	} else {
		// deselect all
		$('#file_tools_list_files input[type="checkbox"]').removeAttr("checked");
	}

	$(this).find("span").toggle();
}

elgg.file_tools.bulk_delete = function(e){
	e.preventDefault();

	$checkboxes = $('#file_tools_list_files input[type="checkbox"]:checked');

	if($checkboxes.length){
		if(confirm(elgg.echo("deleteconfirm"))) {
			var postData = $checkboxes.serializeJSON();

			if($('#file_tools_list_files input[type="checkbox"][name="folder_guids[]"]:checked').length && confirm(elgg.echo("file_tools:folder:delete:confirm_files"))){
				postData.files = "yes";
			}

			$("#file_tools_list_files_container .elgg-ajax-loader").show();
			
			elgg.action("file/bulk_delete", {
				data: postData,
				success: function(res){
					$.each($checkboxes, function(key, value){
						$('#elgg-object-' + $(value).val()).remove();
					});

					$("#file_tools_list_files_container .elgg-ajax-loader").hide();
				}
			});
		}
	}
}

elgg.file_tools.bulk_download = function(e){
	e.preventDefault();

	$checkboxes = $('#file_tools_list_files input[type="checkbox"]:checked');

	if($checkboxes.length){
		elgg.forward("file/bulk_download?" + $checkboxes.serialize());
	}
}

elgg.file_tools.new_folder = function(event){
	event.preventDefault();

	var hash = window.location.hash.substr(1);
	var link = elgg.get_site_url() + "file_tools/folder/new/" + elgg.get_page_owner_guid() + "?folder_guid=" + hash;
	
	$.fancybox({
		href: link,
		titleShow: false
	});
}

elgg.file_tools.init = function(){
	// uploadify functions
	elgg.file_tools.uploadify.init();
	$('#file-tools-uploadify-cancel').live("click", elgg.file_tools.uploadify.cancel);
	$('#file-tools-file-upload-form').submit(elgg.file_tools.uploadify.upload);

	// tree functions
	elgg.file_tools.tree.init();
	
	$('#file_tools_breadcrumbs a').live("click", elgg.file_tools.breadcrumb_click);
	$('#file_tools_select_all').live("click", elgg.file_tools.select_all);
	$('#file_tools_action_bulk_delete').live("click", elgg.file_tools.bulk_delete);
	$('#file_tools_action_bulk_download').live("click", elgg.file_tools.bulk_download);

	$('#file_tools_list_new_folder_toggle').live('click', elgg.file_tools.new_folder);
}

// register init hook
elgg.register_hook_handler("init", "system", elgg.file_tools.init);