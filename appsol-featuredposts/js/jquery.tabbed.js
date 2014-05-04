(function($){
    /* jQuery tabbed interface */
    $.fn.tabbedModule = function(options){
        var settings = $.extend({
            'speed': 5000,
            'responsive_mode': false,
            'responsive_threshhold': 480,
            'responsive_accordion': true
        }, options);
        return this.each(function(){
            var parent_element = $(this);
            var timer = (settings['speed'] != 0)? setInterval(slideTimer, settings['speed']) : 0;
            if (options['responsive_mode'])
            {
                tabbed_interface_html = $(parent_element).html();
                accordion_html = responsiveHTML();
                var current_width = window.innerWidth;
                var threshhold = (options['responsive_threshhold']) ? options['responsive_threshhold'] : 480 ;
                if(current_width <= threshhold)
                {
                        $(parent_element).html(accordion_html);
                        declareEvents();
                        current_width = window.innerWidth;
                }
                $(window).resize(function()
                {
                    if(current_width >= threshhold && window.innerWidth <= threshhold)
                    {
                        $(parent_element).html(accordion_html);
                        declareEvents();
                        current_width = window.innerWidth;
                    }
                    else if(current_width <= threshhold && window.innerWidth >= threshhold)
                    {
                        $(parent_element).html(tabbed_interface_html);
                        declareEvents();
                        current_width = window.innerWidth;
                    }
                });
            }
            declareEvents();
            function declareEvents()
            {
                var selector = $(parent_element).find('.content-tabs').length != 0 ? '.content-tabs li' : 'h3';
                changeSlide($(parent_element).find(selector+':first-child'));
                $(parent_element).find(selector).click(function()
                {
                    changeSlide($(this));
                    clearInterval(timer);
                });
                $(parent_element).removeClass('no-javascript');
            }
            function slideTimer()
            {
                if ($(parent_element).find('.content-tabs li.active').next().length == 0)
                {
                    changeSlide($(parent_element).find('.content-tabs li').first());
                }
                else
                {
                    changeSlide($(parent_element).find('.content-tabs li.active').next());
                }
            }
            function changeSlide(target)
            {
                var target_id = $(target).attr('id');
                $(parent_element).find('.active').removeClass('active');
                $(target).addClass('active');
                if ($(parent_element).find('.content-boxes').length)
                {
                    $(parent_element).find('.content-boxes > div').hide();
                    $(parent_element).find('.content-boxes > div.'+target_id).fadeIn(400);
                }
                else
                {
                    $(parent_element).children('div').css('display', 'block').hide();
                    $(parent_element).find('div.'+target_id).show();
                }
                $(window).trigger('changedTab');
            }
            function responsiveHTML()
            {
                var output = '';
                var accordion = options['responsive_accordion'];
                if (accordion)
                    {
                    $(parent_element).find('.content-tabs li').each(function(){
                        var id = this.id;
                        var title = $(this).text();
                        var text = $('.'+id).html();
                        output += '<h3 id="'+id+'">'+title+'</h3>';
                        output += '<div class="'+id+'">'+text+'</div>';
                    });}
                else
                {
                  $(parent_element).find('.content-tabs li').each(function(){
                      var id = this.id;
                      var css_class = '.'+id;
                      var label = $(this).text();
                      var title = $(css_class).find('h4').text();
                      var link = $(css_class).find('a');
                      output += '<p><span class="label">'+label+':</span> ';
                      output += '<span class="title">'+title+'</span>...';
                      output += '<a href="'+link[0].href+'">Read More</a>';
                  });
                }
                return output;
            }
        });
    };
})( jQuery );


