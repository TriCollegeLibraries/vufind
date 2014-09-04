// code adapted from http://viralpatel.net/blogs/2010/12/dynamically-shortened-text-show-more-link-jquery.html

$(document).ready(function(){

var ellipsestext = "...";
var moretext = "more";
var lesstext = "less";

function initialize_more_less(showChar, dom_element) {
    // showChar is number of characters to show in "less" view
    var content = dom_element.html();
    content = $.trim(content);

    if(content.length > showChar) {
        // we actually have enough content that we want to shorten it.
        var brief = '';
        var remaining = '';
        var has_tags = false;
        if (content.indexOf("<") >= 0) {
            has_tags = true;
        }

        if (has_tags) {
            // make sure we don't split in the middle of a link.
            var left = 0;
            var right = 0;
            var close = 0;
            var finished = false;
            while (!finished) {
                // first test for <br>
                if ((right > left) && ((brief.substring(left+1, right) == "br") || (brief.substring(left+1, right) == "BR"))){
                    finished = true;
                }
                // otherwise test for normal tags that open and close.
                else if ((left == close) && (right > close)) {
                    finished = true;
                }
                else {
                    brief = content.substr(0, showChar);
                    remaining = content.substr(showChar, content.length - showChar);
                }
                left = brief.lastIndexOf("<");
                right = brief.lastIndexOf(">");
                close = brief.lastIndexOf("</");
                showChar = brief.length + remaining.indexOf(">") + 1;
            }
        }
        else {
            brief = content.substr(0, showChar);
            remaining = content.substr(showChar, content.length - showChar);
        }

        if (brief.length < content.length) {

            var html = brief + '<span class="moreellipses">' + ellipsestext + '&nbsp;</span><span class="morecontent hidden">' + remaining + '</span><span>&nbsp;&nbsp;<a href="" class="morelink">' + moretext + '</a></span>';

            //var html += "<br />content: +"<br />"+content;

            dom_element.html(html);
        }
    }
}

// want to show more of some fields (description) and less of others (creator)
// any span class starting with "more-less" will be picked up by this script.
$('.more-less-short').each(function() {
    var dom_element = $(this);
    initialize_more_less(115, dom_element);
});
$('.more-less-links').each(function() {
    var dom_element = $(this);
    initialize_more_less(700, dom_element);
});
$('.more-less-long').each(function() {
    var dom_element = $(this);
    initialize_more_less(250, dom_element);
});


$(".morelink").click(function(event){
    // change link text
    event.preventDefault();
    if($(this).hasClass("less")) {
        $(this).removeClass("less");
        $(this).html(moretext);
    } else {
        $(this).addClass("less");
        $(this).html(lesstext);
    }
    // toggle visibility of ellipses and more content
    // find an ancestor with class that starts with "more-less" and nav down.
    $(this).closest('span[class^="more-less"]').children('.morecontent').toggleClass("hidden");
    $(this).closest('span[class^="more-less"]').children('.moreellipses').toggleClass("hidden");
});

});
