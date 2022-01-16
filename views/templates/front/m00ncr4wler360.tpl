<section class="page-product-box">
    <h3 class="page-product-heading">
        {l s='360° Viewer' mod='m00ncr4wler360'}
    </h3>

    <div class="img360-wrapper">
        <div class="img360-container">
            <img src="{$smarty.const._THEME_360_DIR_}{$image_start}"
                 width="{$image_width}"
                 height="{$image_height}"
                 id="image360"
                 data-fancybox-group="img360-views"
                 itemprop="image"
            />
            <span class="span_link no-print img360-start">{l s='View 360°' mod='m00ncr4wler360'}</span>
        </div>
    </div>
</section>
<script type="text/javascript">
    {literal}
    $(document).ready(function () {
        var Viewer = new function () {
            var public = {
                init: function () {
                    _.initImages();
                    _.initReel();
                    _.initViewer();
                }
            };
            var _ = {
                images: [],
                thickboxImages: [],
                thickboxId: '.img360-thickbox',
                reelId: '#image360',
                reelStartId: '.img360-start',
                reelContainerId: '.img360-container',
                reelBtnLeft: '.img360-left',
                reelBtnRight: '.img360-right',
                realBtnInterval: null,
                realBtnEvent: null,
                initImages: function () {
                    {/literal}
                    {foreach from=$images item=image}
                    var image = "{$smarty.const._THEME_360_DIR_}{$image}";
                    _.images.push(image);
                    _.thickboxImages.push({literal}{{/literal}href: image.replace('{$image_type}', '{$image_type_fancybox}'), title: ''{literal}
                    }{/literal});
                    {/foreach}
                    {literal}
                },
                initReel: function () {
                    $(_.reelId).reel({
                        {/literal}
                        clickfree: {$reel_clickfree},
                        cw: {$reel_cw},
                        shy: {$reel_shy},
                        responsive: {$reel_responsive},
                        throwable: {$reel_throwable},
                        steppable: {$reel_steppable},
                        draggable: {$reel_draggable},
                        loops: {$reel_loops},
                        orientable: {$reel_orientable},
                        revolution: {$image_width},
                        images: _.images,
                        frame: {$id_image_start},
                        frames: {$image_count},
                        footage: {$image_count}
                        {literal}
                    });
                },
                initViewer: function () {
                    $(_.reelStartId).click(function () {
                        $(_.reelId).trigger('click');
                        $(_.reelStartId).remove();
                        $(_.reelContainerId).append('<span class="no-print img360-step img360-left" data-direction="left"></span><span class="no-print img360-step img360-right" data-direction="right"></span><span class="span_link no-print img360-thickbox">{/literal}{l s='View larger'}{literal}</span>');
                        $(_.reelBtnLeft + ',' + _.reelBtnRight).bind('mousedown mouseup touchstart touchend', _.eventBtnDown);
                        $(_.thickboxId).click(_.eventThickbox);
                    });
                    if (!{/literal}{$reel_shy}{literal}) {
                        $(_.reelStartId).trigger('click');
                    }
                },
                eventThickbox: function (e) {
                    $.fancybox.open(_.thickboxImages, {
                        index: $(_.reelId).reel('frame') - 1,
                        beforeClose: function () {
                            $(_.reelId).reel('frame', $.fancybox.current.index + 1);
                        }
                    });
                },
                eventBtnDown: function (e) {
                    clearInterval(_.realBtnInterval);
                    e.stopPropagation();
                    e.preventDefault();
                    switch (e.type) {
                        case 'touchstart':
                        case 'mousedown':
                            _.realBtnEvent = e;
                            _.realStepHandler();
                            _.realBtnInterval = setInterval(_.realStepHandler, 200);
                            break;
                    }
                },
                realStepHandler: function () {
                    dir = $(_.realBtnEvent.currentTarget).data('direction');
                    cw = {/literal}{$reel_cw}{literal};
                    if (!cw) {
                        if (dir == 'left')
                            dir = 'right';
                        else
                            dir = 'left';
                    }
                    switch (dir) {
                        case 'left':
                            $(_.reelId).trigger('stepLeft');
                            break;

                        case 'right':
                            $(_.reelId).trigger('stepRight');
                            break;
                    }
                }
            };
            return public;
        };

        Viewer.init();
    });
    {/literal}
</script>