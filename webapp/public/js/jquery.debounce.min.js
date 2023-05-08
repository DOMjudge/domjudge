/**
 * Debounce and throttle function's decorator plugin 1.0.6
 *
 * Copyright (c) 2009 Filatov Dmitry (alpha@zforms.ru)
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 */(function(a){a.extend({debounce:function(a,b,c,d){arguments.length==3&&typeof c!="boolean"&&(d=c,c=!1);var e;return function(){var f=arguments;d=d||this,c&&!e&&a.apply(d,f),clearTimeout(e),e=setTimeout(function(){c||a.apply(d,f),e=null},b)}},throttle:function(a,b,c){var d,e,f;return function(){e=arguments,f=!0,c=c||this,d||function(){f?(a.apply(c,e),f=!1,d=setTimeout(arguments.callee,b)):d=null}()}}})})(jQuery);
