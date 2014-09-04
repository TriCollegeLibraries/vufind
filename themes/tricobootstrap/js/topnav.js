/*!
 * jQuery JavaScript Library v1.4.4
 * http://jquery.com/
 *
 * Copyright 2010, John Resig
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * Includes Sizzle.js
 * http://sizzlejs.com/
 * Copyright 2010, The Dojo Foundation
 * Released under the MIT, BSD, and GPL Licenses.
 *
 * Date: Thu Nov 11 19:04:53 2010 -0500
 */

/** enable academics drop down in main site **/
$.fn.igxAcadNav = function(){
	var navWidth = $(this).width();
	$(this).children('.main-nav-item').hover(function() {
		var dd = $(this).find('.main-nav-dd');
		// get the left position of this tab
		var leftPos = $(this).find('.main-nav-tab').position().left;
		// get the width of the dropdown
		var ddWidth = dd.width();
		// get the tab width
		var tabWidth = $(this).find('.main-nav-tab').width();
		// position the dropdown
		if(tabWidth == 70){
		    dd.css('left', (leftPos + tabWidth) -ddWidth + 26);
		}else{
		    dd.css('left', (leftPos + tabWidth) -ddWidth + 28);
		}

	        // show the dropdown
		$(this).addClass('main-nav-item-active');
                $(this).removeClass('main-nav-item-hidden');
	}, function() {
		// hide the dropdown
		$(this).removeClass('main-nav-item-active');
                $(this).addClass('main-nav-item-hidden');
	});
        
        $('div.main-nav-tab, .main-nav-dd-column a', $(this)).focus(function() {
                var dd = $(this).parents("li").find('.main-nav-dd');
                // get the left position of this tab
                var leftPos = $(this).parents("li").find('.main-nav-tab').position().left;
                // get the width of the dropdown
                var ddWidth = dd.width();
                // get the tab width
                var tabWidth = $(this).parents("li").find('.main-nav-tab').width();
                // position the dropdown
                if(tabWidth == 70){  
                  dd.css('left', (leftPos + tabWidth) -ddWidth + 26);
                }else{
                  dd.css('left', (leftPos + tabWidth) -ddWidth + 28);
                }
                
                // show the dropdown
                $(this).parents("li").removeClass('main-nav-item-hidden');
                $(this).parents("li").addClass('main-nav-item-active');
        }).blur(function() {
                // hide the dropdown
                //$(this).parents("li").addClass('main-nav-test');
                $(this).parents("li").removeClass('main-nav-item-active');
                $(this).parents("li").addClass('main-nav-item-hidden');
        });
};


$(document).ready(function(){
	$('#main-nav').igxAcadNav();
	//keep the copyright date current
	$('.swat-copy-date').each(function(){
		var d=new Date();
		$(this).html(d.getFullYear());
	});
});
