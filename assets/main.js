jQuery(document).ready(function($) {
    try {
        $('.orbisius_seo_editor_dropdown').select2({
            dropdownAutoWidth : true
            //width: 'resolve' // need to override the changed default
            //width: '100%'
        });
    } catch (e) {

    }

    let orbisius_seo_editor_loading = "<span class='orbisius_seo_editor_loading' style='background: yellow;padding:3px;'>Processing...</span>";

    // There are multiple submit buttons in this form. We'll hide the one that was clicked.
    // It's confusing to see multiple loading texts on the page.
    $('.orbisius_seo_editor_form :submit').on('click', function (e) {
        $('.orbisius_seo_editor_loading').remove();
        var cur_submit_btn = $(this);
        cur_submit_btn.hide().after(orbisius_seo_editor_loading);
        cur_submit_btn.prop('readonly', true);

        // In some instances e.g. export the page doesn't reload because the browser receives a download
        // we'll have to manually show the submit buttons and hide the loading.
        setTimeout(function () {
            cur_submit_btn.show();
            cur_submit_btn.prop('readonly', false);
            $('.orbisius_seo_editor_loading').remove();
        }, 4000);

        return true;
    });

    // Let's get the selected CSV file from the dropdown
    // we could parse it and preselect the other dropdown
    // that way we won't have to parse the CSV. We rely on the seo plugin to be present
    // when the plugin loads.
    // stackoverflow.com/questions/6365858/use-jquery-to-get-the-file-inputs-selected-filename-without-the-path
    $('.orbisius_seo_editor_file').on('change', function (e) {
        let sel_seo = $('#orbisius_seo_editor_search_target_seo_plugin').val();

        if (sel_seo !== '') { // if already select don't touch it
            console.log("orbisius_seo_editor: source SEO plugin selected so don't parse the selected file for plugin");
            return true;
        }

        let file_base_name = $(this).val().replace(/.*(\/|\\)/, '');

        // developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/match
        // If the g flag is used, all results matching the complete regular expression will be returned, but capturing groups are not included.
        // important!: unly use \w in the group because we have a separator - and addons are lowercase and underscore as sep
        const regex = /seo_plugin[_\-]+([\w]+)/i; // we can't use : or other special chars because it's a filename
        const matches = file_base_name.match(regex);

        if (matches) {
            console.log("orbisius_seo_editor: detected plugin: " + matches[1]);
            // We call trigger change so select2 can update the value too
            $('#orbisius_seo_editor_search_target_seo_plugin').val(matches[1]).trigger('change');
        }
    });

    // When the src SEO plugin is selected/changed we'll make an ajax call to see what are its supported fields to edit.
    $('#orbisius_seo_editor_search_src_seo_plugin').on('change', function (e) {
        let sel_seo_addon = $(this).val() || '';

        if (sel_seo_addon === '') { // if already select don't touch it
            return true;
        }

        $('.orbisius_seo_editor_loading').remove();

        // We'll show the loading, hide the wrapper that has the dropdown so the user
        // gets an idea that we'll be loading the field dropdown menu for each plugin
        // because we support different fields for each SEO plugin.
        jQuery('#orbisius_seo_editor_search_filter_supported_addon_fields_select_wrapper').hide();
        jQuery('#orbisius_seo_editor_search_src_field').empty().trigger('change');
        jQuery('#orbisius_seo_editor_search_filter_supported_addon_fields_select_wrapper').after(orbisius_seo_editor_loading);

        jQuery.ajax({
            url: ajaxurl + '?action=orbisius_seo_editor_search_load_supported_addon_fields',
            method: "POST",
            data : { 'orbisius_seo_editor_search[src_seo_plugin]' : sel_seo_addon }
            //data: jQuery(some_form).serialize()
        }).done(function (json) {
            jQuery('#orbisius_seo_editor_search_filter_supported_addon_fields_select_wrapper').show();

            if ( json.status ) {
                $('.orbisius_seo_editor_loading').remove();
                let $select_el = $('#orbisius_seo_editor_search_src_field');
                $.each(json.data.supported_fields, function(my_plugin_key, label) {
                    $select_el.append(
                        $('<option></option>')
                            .attr("value", my_plugin_key)
                            .text(label)
                    );
                });

                if (json.data.src_field !== '') { // preselect
                    $select_el.val(json.data.src_field);
                }
            } else {
                $('.orbisius_seo_editor_loading').html(json.msg);
            }

            return false;
        });
    });

    // If there's a value for the SEO plugin we need to refresh the supported fields.
    // as we didn't have that info when we rendered the field.
    // or it would have been complicated to load it in the backend.
    if ($('#orbisius_seo_editor_search_src_seo_plugin').val() !== '') {
        $('#orbisius_seo_editor_search_src_seo_plugin').trigger('change');
    }

    // selecting a file from the dropdown triggers download but we now want to upload csv
    /*$('.orbisius_seo_editor_file').on('change', function (e) {
        $('.orbisius_seo_editor_loading').remove();
        $('.orbisius_seo_editor_upload_form_submit_btn').hide();
        $('.orbisius_seo_editor_upload_form_submit_btn').after(orbisius_seo_editor_loading);
        $('.orbisius_seo_editor_upload_form').trigger('submit');
        return true;
    });*/

    // Upload button triggers file selection. We have only submit button and not choose file for better UI.
    $('.orbisius_seo_editor_upload_form_submit_btn').on('click', function (e) {
        var sel_file = $('.orbisius_seo_editor_file').val();

        if (sel_file == '') {
            $('.orbisius_seo_editor_file').trigger('click'); // trigger file selection from computer
            return false;
        }

        $('.orbisius_seo_editor_loading').remove();
        cur_submit_btn.prop('readonly', false);

        return true;
    });
} );