jQuery(document).ready(function(){var t=jQuery("#be-filters"),n=t.parents(".remodal");if(!t.length)return!0;n.on("submit",function(e){e.preventDefault();var r=t.serialize();return t.find("select.select2").each(function(){var e=jQuery(this),t=e.val();t&&(!Array.isArray(t)||t.length)||(r+="&"+e.attr("name")+"=")}),t.find("input:checkbox").each(function(){var e=jQuery(this);if(!e.prop("checked")&&e.attr("name")){var t=e.data("unchecked-value")||"";r+="&"+e.attr("name")+"="+t}}),beAddRowsFilter(r),vgseReloadSpreadsheet(!0,vgse_editor_settings.texts.save_changes_before_remove_filter),n.is(":visible")&&n.find(".remodal-cancel").trigger("click"),!1}),jQuery("body").on("vgSheetEditor/beforeIntialLoadingRows",function(){vgseFilters.setLastSessionFilters()})}),jQuery(document).ready(function(){jQuery("body").on("vgSheetEditor:beforeRowsInsert",function(e,t){if(void 0===window.cellLocatorAlreadyInit){window.cellLocatorAlreadyInit=!0;var r=document.getElementById("cell-locator-input");r&&Handsontable.dom.addEvent(r,"keyup",function(e){if(13==e.keyCode){var t=hot.getPlugin("search").query(this.value);t.length?hot.scrollViewportTo(t[0].row,t[0].col,!0):this.value&&alert("Cells not found. Try with another search criteria."),hot.render(),jQuery("#responseConsole .rows-located").length||jQuery("#responseConsole").append('. <span class="rows-located"></span>'),jQuery("#responseConsole .rows-located").text(t.length+" cells located")}})}if(void 0===window.columnLocatorAlreadyInit){window.columnLocatorAlreadyInit=!0;var n=document.getElementById("column-locator-input");n&&Handsontable.dom.addEvent(n,"keyup",function(e){if(13==e.keyCode){var t=this.value;if("number"!=typeof(n=hot.propToCol(t))&&(n=null),"number"!=typeof(n=n||hot.propToCol(t.toLowerCase()))&&(n=null),n)i=1;else{var r=hot.getSettings().colHeaders,n=null,o=this.value.toLowerCase(),i=0;r.forEach(function(e,t){e&&e.indexOf("<input")<0&&-1<e.toLowerCase().indexOf(o)&&(0===i&&(n=t),i++)})}null!==n?hot.selectColumns(n):this.value&&(alert(vgse_editor_settings.texts.column_not_found),vgse_editor_settings.texts.hint_missing_column_on_scroll&&notification({mensaje:vgse_editor_settings.texts.hint_missing_column_on_scroll,tipo:"info",time:4e4,position:"bottom"})),jQuery("#responseConsole .columns-located").length||jQuery("#responseConsole").append('. <span class="columns-located"></span>'),jQuery("#responseConsole .columns-located").text(i+" columns located")}})}})}),jQuery(document).ready(function(){if("undefined"==typeof hot)return!0;jQuery("body").on("keyup",".button-container.run_filters-container .toolbar-submenu input",function(e){13==e.keyCode&&vgseFilters.startQuickSearch(),27==e.keyCode&&vgseFilters.restoreHoverEffectOnSubmenu()}),jQuery("body").on("click",".button-container.run_filters-container .toolbar-submenu .wpse-start-quick-search",function(e){e.preventDefault(),vgseFilters.startQuickSearch()}),document.addEventListener("keydown",function(e){if("f"===e.key.toLowerCase()&&e.ctrlKey&&!jQuery(".remodal-is-opened").length){e.preventDefault(),hot.deselectCell();var t=jQuery("#vgse-wrapper .vg-toolbar .button-container.run_filters-container .toolbar-submenu");t.css({width:360,display:"block"}),t.find(".wpse-quick-search, .wpse-advanced-filters-value-selector").last().focus().select()}})});var vgseFilters={restoreHoverEffectOnSubmenu:function(){jQuery(".button-container.run_filters-container .toolbar-submenu").css({width:"",display:""})},startQuickSearch:function(){jQuery(".button-container.run_filters-container .toolbar-submenu").find("input,select,textarea").each(function(){var e=jQuery(this).attr("name");if(e){var t=jQuery(this).val(),r=jQuery("#be-filters").find("input,select,textarea").filter(function(){return jQuery(this).attr("name")===e});"checkbox"===r.attr("type")?r.prop("checked",jQuery(this).prop("checked")):r.val(t),r.trigger("change")}}),vgseFilters.restoreHoverEffectOnSubmenu(),jQuery("#be-filters").submit()},setLastSessionFilters:function(){vgse_editor_settings.last_session_filters&&(beAddRowsFilter(vgse_editor_settings.last_session_filters),this.fillSearchForm(vgse_editor_settings.last_session_filters),this.fillFavoriteSearchForm(vgse_editor_settings.last_session_filters),window.vgseShowLastSessionFiltersTip=!0,jQuery("body").on("vgSheetEditor:beforeRowsInsert",function(){if(!window.vgseShowLastSessionFiltersTip)return!0;window.vgseShowLastSessionFiltersTip=!1,vgseCustomTooltip(jQuery(".vgse-current-filters a").last(),vgse_editor_settings.texts.last_session_filters_notice,"right",!1,"success")}))},setFiltersSilently:function(e){e&&beAddRowsFilter(e)},fillFavoriteSearchForm:function(e){var l=jQuery(".vg-toolbar .button-container.run_filters-container .toolbar-submenu"),t="string"==typeof e?beParseParams(e):e,c=l;jQuery.each(t,function(e,t){if("meta_query"===e){if(l.find(".advanced-filters-toggle").prop("checked",!0).trigger("change"),"object"==typeof t){var r=(t=vgObjectToArray(t)).length,n=c.find("li.advanced-field:not(.base)").length;if(n<r)for(var o=r-n,i=0;i<o;i++){l.find(".new-advanced-filter").last().trigger("click")}var s=0;t.forEach(function(e){var t=c.find(".advanced-field:eq("+s+")");t.find(".wpse-advanced-filters-field-selector").val(e.key).trigger("change"),t.find(".wpse-advanced-filters-operator-selector").val(e.compare).trigger("change"),t.find(".wpse-advanced-filters-value-selector").val(e.value).trigger("change"),s++})}}else if(e){var a=l.find("input,select,textarea").filter(function(){return jQuery(this).attr("name")===e||jQuery(this).attr("name")===e+"[]"});"checkbox"===a.attr("type")?a.prop("checked",t):("SELECT"===a.prop("tagName")&&a.append('<option value="'+t+'">'+t+"</option>"),a.val(t)),a.trigger("change")}})},fillSearchForm:function(e){var l=jQuery("#be-filters"),t="string"==typeof e?beParseParams(e):e,c=l.find(".advanced-filters-list");jQuery.each(t,function(e,t){if("meta_query"===e){if(l.find(".advanced-filters-toggle").prop("checked",!0).trigger("change"),"object"==typeof t){var r=(t=vgObjectToArray(t)).length,n=c.find("li.advanced-field:not(.base)").length;if(n<r)for(var o=r-n,i=0;i<o;i++){l.find(".new-advanced-filter").last().trigger("click")}var s=0;t.forEach(function(e){var t=c.find(".advanced-field:eq("+s+")");t.find(".wpse-advanced-filters-field-selector").val(e.key).trigger("change"),t.find(".wpse-advanced-filters-operator-selector").val(e.compare).trigger("change"),t.find(".wpse-advanced-filters-value-selector").val(e.value).trigger("change"),s++})}}else if(e){var a=l.find("input,select,textarea").filter(function(){return jQuery(this).attr("name")===e||jQuery(this).attr("name")===e+"[]"});"checkbox"===a.attr("type")?a.prop("checked",t):("SELECT"===a.prop("tagName")&&a.append('<option value="'+t+'">'+t+"</option>"),a.val(t)),a.trigger("change")}})}};