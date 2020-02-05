// Largely copied from core code's MyBB.whoPosted() but with support for a date range added.
function activethreads_whoPosted(tid, min_dateline, max_dateline, sortby) {
	var sort = '', url, body;

	if (typeof sortby === 'undefined') {
		sortby = '';
	}

	if (sortby == 'username') {
		sort = '&sort=' + sortby;
	}
	url = '/activethreads.php?action=whoposted&tid='+tid+sort+'&min_dateline='+min_dateline+'&max_dateline='+max_dateline+'&modal=1';
	
	// if the modal is already open just replace the contents
	if ($.modal.isActive()) {
		// don't waste a query if we are already sorted correctly
		if (sortby == MyBB.whoPostedSort) {
			return;
		}
		
		MyBB.whoPostedSort = sortby;
		
		$.get(rootpath + url, function(html) {
			// just replace the inner div
			body = $(html).children('div');
			$('div.modal').children('div').replaceWith(body);
		});
		return;
	}
	MyBB.whoPostedSort = '';
	MyBB.popupWindow(url);
}
