<div id="product-360" class="panel product-tab">
<input type="hidden" name="submitted_tabs[]" value="Images360"/>

<div class="panel-heading tab">
    {l s='Images' mod="m00ncr4wler360"}
    <span class="badge" id="countImage_360">{$countImages}</span>
</div>
<div class="row">
    <div class="form-group">
        <label class="control-label col-lg-3 file_upload_label">
				<span class="label-tooltip" data-toggle="tooltip" title="{l s='Format:' mod="m00ncr4wler360"} JPG, GIF, PNG. {l s='Filesize:' mod="m00ncr4wler360"} {$max_image_size|string_format:"%.2f"} {l s='MB max.' mod="m00ncr4wler360"}">
					{if isset($id_image)}{l s='Edit this product\'s image:' mod="m00ncr4wler360"}{else}{l s='Add a new image to this product' mod="m00ncr4wler360"}{/if}
				</span>
        </label>

        <div class="col-lg-9">
            {$image_uploader}
        </div>
    </div>
</div>
<table class="table tableDnD" id="imageTable_360">
    <thead>
    <tr class="nodrag nodrop">
        <th class="fixed-width-lg"><span class="title_box">{l s='Image' mod="m00ncr4wler360"}</span></th>
        <th class="fixed-width-xs"><span class="title_box">{l s='Position' mod="m00ncr4wler360"}</span></th>
        <th class="fixed-width-xs"><span class="title_box">{l s='Cover' mod="m00ncr4wler360"}</span></th>
        <th></th>
        <!-- action -->
    </tr>
    </thead>
    <tbody id="imageList_360">
    </tbody>
</table>
<table id="lineType_360" style="display:none;">
    <tr id="image_id_360">
        <td>
            <a href="{$smarty.const._THEME_360_DIR_}image_path.jpg" class="fancybox">
                <img
                    src="#{$smarty.const._THEME_360_DIR_}{$iso_lang}-default-{$imageType}.jpg"
                    alt="legend"
                    title="legend"
                    class="img-thumbnail"/>
            </a>
        </td>
        <td id="td_image_id_360" class="pointer dragHandle center positionImage">
            image_position
        </td>
        <td class="cover">
            <a href="#">
                <i class="icon-check-empty icon-2x covered_360"></i>
            </a>
        </td>
        <td>
            <a href="#" class="delete_product_image_360 pull-right btn btn-default">
                <i class="icon-trash"></i> {l s='Delete this image' mod="m00ncr4wler360"}
            </a>
        </td>
    </tr>
</table>
<div class="panel-footer">
    <a href="{$link->getAdminLink('AdminProducts')}" class="btn btn-default"><i class="process-icon-cancel"></i> {l s='Cancel'}</a>
    <button type="submit" name="submitAddproduct" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save'}</button>
    <button type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save and stay'}</button>
</div>
<script type="text/javascript">
    var upbutton = '{l s='Upload an image' mod="m00ncr4wler360"}';
    var come_from = '{$table}';
    var success_add = '{l s='The image has been successfully added.' mod="m00ncr4wler360"}';
    var id_tmp = 0;

    {literal}
    //Ready Function

    function imageLine_360(id, path, position, cover) {
        line = $("#lineType_360").html();
        line = line.replace(/image_id/g, id);
        line = line.replace(/src="#/, 'src="');
        line = line.replace(/(\/)?[a-z]{0,2}-default/g, function ($0, $1) {
            return $1 ? $1 + path : $0;
        });
        line = line.replace(/image_path/g, path);
        line = line.replace(/image_position/g, position);
        line = line.replace(/icon-check-empty/g, cover);
        line = line.replace(/<tbody>/gi, "");
        line = line.replace(/<\/tbody>/gi, "");
        $("#imageList_360").append(line);
    }

    $(document).ready(function () {
        {/literal}
        {foreach from=$images item=image}
        assoc = {literal}"{"{/literal};
        if (assoc != {literal}"{"{/literal}) {
            assoc = assoc.slice(0, -1);
            assoc += {literal}"}"{/literal};
            assoc = jQuery.parseJSON(assoc);
        }
        else
            assoc = false;
        imageLine_360({$image->id}, "{$image->getExistingImgPath()}", {$image->position}, "{if $image->cover}icon-check-sign{else}icon-check-empty{/if}");
        {/foreach}
        {literal}
        var originalOrder = false;

        $("#imageTable_360").tableDnD(
        {
            onDragStart: function (table, row) {
                originalOrder = $.tableDnD.serialize();
            },
            onDrop: function (table, row) {
                if (originalOrder != $.tableDnD.serialize()) {
                    current = $(row).attr("id") + "_360";
                    stop = false;
                    image_up = "{";
                    $("#imageList_360").find("tr").each(function (i) {
                        $("#td_" + $(this).attr("id")).html(i + 1);
                        if (!stop || (i + 1) == 2)
                            image_up += '"' + $(this).attr("id") + '" : ' + (i + 1) + ',';
                    });
                    image_up = image_up.slice(0, -1);
                    image_up += "}";
                    updateImagePosition_360(image_up);
                }
            }
        });
        /**
         * on success function
         */
        function afterDeleteProductImage_360(data) {
            data = $.parseJSON(data);
            if (data) {
                cover = 0;
                id = data.content.id;
                if (data.status == 'ok') {
                    if ($("#" + id + '_360 .covered_360').hasClass('icon-check-sign'))
                        cover = 1;
                    $("#" + id + "_360").remove();
                }
                if (cover)
                    $("#imageTable_360 tr").eq(1).find(".covered_360").addClass('icon-check-sign');
                $("#countImage_360").html(parseInt($("#countImage_360").html()) - 1);
                refreshImagePositions($("#imageTable_360"));
                showSuccessMessage(data.confirmations);
            }
        }

        $('.delete_product_image_360').die().live('click', function (e) {
            e.preventDefault();
            id = $(this).parent().parent().attr('id').replace('_360', '');
            if (confirm("{/literal}{l s='Are you sure?' js=1 mod="m00ncr4wler360"}{literal}"))
                doAdminAjax({
                            "action": "deleteProductImage360",
                            "id_image": id,
                            "id_product": {/literal}{$id_product}{literal},
                            "id_category": {/literal}{$id_category_default}{literal},
                            "token": "{/literal}{$token}{literal}",
                            "tab": "AdminProducts",
                            "ajax": 1
                        }, afterDeleteProductImage_360
                );
        });

        $('.covered_360').die().live('click', function (e) {
            e.preventDefault();
            id = $(this).parent().parent().parent().attr('id').replace('_360', '');
            $("#imageList_360 .cover i").each(function (i) {
                $(this).removeClass('icon-check-sign').addClass('icon-check-empty');
            });
            $(this).removeClass('icon-check-empty').addClass('icon-check-sign');

            $(this).parent().parent().parent().children('td input').attr('check', true);
            doAdminAjax({
                "action": "updateCover360",
                "id_image": id,
                "id_product": {/literal}{$id_product}{literal},
                "token": "{/literal}{$token}{literal}",
                "controller": "AdminProducts",
                "ajax": 1
            });
        });

        function updateImagePosition_360(json) {
            doAdminAjax(
                    {
                        "action": "updateImagePosition360",
                        "json": json,
                        "token": "{/literal}{$token}{literal}",
                        "tab": "AdminProducts",
                        "ajax": 1
                    });
        }

        $('.fancybox').fancybox();
    });

    hideOtherLanguage(default_language);
    {/literal}
</script>
</div>