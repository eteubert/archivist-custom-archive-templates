(function($) {
    "use strict";
     
    function updateURLParameter(url, param, paramVal) {
        var TheAnchor = null;
        var newAdditionalURL = "";
        var tempArray = url.split("?");
        var baseURL = tempArray[0];
        var additionalURL = tempArray[1];
        var temp = "";

        if (additionalURL) 
        {
            var tmpAnchor = additionalURL.split("#");
            var TheParams = tmpAnchor[0];
                TheAnchor = tmpAnchor[1];
            if(TheAnchor)
                additionalURL = TheParams;

            tempArray = additionalURL.split("&");

            for (var i=0; i<tempArray.length; i++)
            {
                if(tempArray[i].split('=')[0] != param)
                {
                    newAdditionalURL += temp + tempArray[i];
                    temp = "&";
                }
            }        
        }
        else
        {
            var tmpAnchor = baseURL.split("#");
            var TheParams = tmpAnchor[0];
                TheAnchor  = tmpAnchor[1];

            if(TheParams)
                baseURL = TheParams;
        }

        if(TheAnchor)
            paramVal += "#" + TheAnchor;

        var rows_txt = temp + "" + param + "=" + paramVal;
        return baseURL + "?" + newAdditionalURL + rows_txt;
    }

    function handle_pagination_click(e) {
        e.preventDefault();

        var page = $(this).data('page');
        var old_wrapper = $(".archivist_wrapper");

        old_wrapper.addClass('archivist-loading');

        $.get(
            archivist.ajaxurl,
            {
                action: 'archivist_paginate',
                archivist_page: page,
                shortcode_attributes: archivist_shortcode_attributes
            },
            function (response) {
                var new_wrapper = $(response).find('.archivist_wrapper');

                $(new_wrapper).replaceAll(old_wrapper);

                // update URL
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({} , '', updateURLParameter(window.location.href, 'archivist_page', page));
                }
            }
        )
    }

    $(document).on('click', '.archivist_wrapper .archivist-pagination-item a', handle_pagination_click);
 
})(jQuery);
