<?php die; ?>

git test

0.2.0 * New options in EM:
		a) "Render COA_GO as COA" If set to TRUE it will disable COA_GO and the COA_GO will be rendered as normal COA. It allows you to simply compare times of rendering with or witout COA_GO.
		b) "hash from cObj path" If set to TRUE then hashes will be made of cObject path. For example if you have "page.10.subpart.myMenu < temp.myMenu" then hash will have name "subpart_myMenu". This is solution if you are too lazy to write your own name for hash using "cache.hash" property. Take under consideration that in some cases this do not guarantee uniqness.
		c) "Clear cache on table changes" If set to TRUE and you will change a record in backend then all cache entries created by COA_GO for this specific table will be removed from cache. This is an easy way to refresh cached values on base of backend user activity. Example - if user changes page title then all COA_GO that have "cache.clearCacheOnTableChange" set to "pages" will be removed from cache and the frontend will be up to date.
		
	  * "hash.special.unique.pidList" - here you can enter the page uids (comma separated) which is a parent for other pages. Pages with uids that have this pid will have its own entry in the cache. Example of usage
	  * "hash.special.unique.uidList" - here you can enter the page uids (comma separated) which will have its own entry in the cache. 
	  * "hash.special.lang" - if set to "1" then language (eg. en, de, pl) will be added to hash name. Use in multilanguage sites.
	  * "clearCacheOnTableChange" - here you can enter one table name. If records belonging to that table will be changed in BE then all the cache (in db and files) of that table created by COA_GO will be deleted. For example if you set "clearCacheOnTableChange = pages" and change title of a page then files in cacheDirectory and entries in cache_hash belonfing to COA_GO_pages will be deleted and this way the change of a title will be visible at frontend.
	   
	  
0.1.5 * request method in ajax call changed from POST to GET. With POST method there was some problems on Safari at MAC (many thanks to Kurt Knote for fighting with this bug!)
	  * fixed bug in beforeCache_db. No assign to $content after get from cache.

0.1.4 * NOTE! Remember to include static TS from extension if you use afterCacheFileAjax method.
      * cObject regeneration are now conducted using special PAGE type. This should be much faster than calling regular page with parameter no_cache=1
	  * New options:
        a) "debug" - show some information at the end of content element (time of generation). It helps to see if afterCacheFile and afterCacheFileAjax methods works as expected.
        b) "refresh" (is stdWrap) - time in seconds telling ajax script how often to fetch the content without user interaction. You need to distinguish between "refresh" and "period". "Period" says when the content object expires and "refresh" only fetch the file. So if you set "period=10" and "refresh=1" then the javascript will fetch the content 10 times and after 10 times cObject will be regenerated. "Refresh" is useful if you do not know exactly what is the cache period. For example: you have "latest_comments" at the page set as "afterCacheFileAjax". You set this content object to have refresh=5 seconds. You can program you comments application to delete "typo3temp/cached_cobj/latest_comments.html" after new comment will be added. So the content object "latest_comments" will fetch the file "typo3temp/cached_cobj/latest_comments.html" every 5 seconds and if there will be no "typo3temp/cached_cobj/latest_comments.html" file (deleted by your application after commens had been added) it will regenerate the cObj "latest_comments" and fetch the new version with new comment!
        c) "onLoading" (is stdWrap) - javascript code set to div contating the content elements. Allows you to inform user that the content is just fetching. Note: its part of right javascript assignment, so id this is just a text wrap it into single quote like 'content'. Example: '<img src="typo3conf/ext/coago/res/ajax/1.gif" />'
        d) "fileExtension" (is stdWrap) - extension added automatically to hash value
      * New config option in Extension Manager
        a) cacheFileExtension - extension added automatically to hash value
      * Code optimalization
      * Hook for own javascript ajax fetch  
        
0.1.3 * Fix ajax problem in IE + compress the script. Now ajax works in all browsers.
	  * Setting default cacheDirectory if this from EM is not set.
	  * Setting default type of caching to 'beforeCache_db'. Renaming $hash to $cacheHash.
	  * Cache period checks are now put in file only if cachePeriod is really set.
	  * Fix bug with using filemtime on non existing file.
	  * type, period and hash are now stdWrap.

0.1.2 Initial upload

