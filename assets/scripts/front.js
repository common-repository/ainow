/*
*	LEGEND:
*	- AINOW_UUID:
*		- AINOW stays for Artificial Intelligence Knowledge.
*		- UUID stays for Unique User ID.
*	- Ainow-Lsh: AINOW-Loading-Sign-Holder
 */

 var loading_sign = "<div id='ainow-lsh'><div class='loader'></div></div>";

jQuery( document ).ready(function( $ ){
	if ( typeof(Storage) !== "undefined" ) {
    	//localStorage.removeItem( "AINOW_UUID" ); // Uncomment this line only for test purposes!
    
    	hostname = window.location.hostname;
    	if ( localStorage.AINOW_UUID !== "undefined" && localStorage.AINOW_UUID !== undefined && localStorage.AINOW_UUID != "" ) {
	    	if ( localStorage.AINOW_UUID.indexOf( hostname ) > -1 ) {
	    		if ( sessionStorage.CURRENT_UUID === "undefined" || sessionStorage.CURRENT_UUID === undefined || sessionStorage.CURRENT_UUID == "" ) {
		    		allIDS = localStorage.AINOW_UUID.split( "&" );
		    		for ( count = 0; count < allIDS.length; count++ ) { if ( allIDS[ count ].indexOf( hostname ) > -1 ) { sessionStorage.CURRENT_UUID = allIDS[ count ]; break; } }
		    	}

	    		jQuery.post(
	    			ajaxurl,
	    			{
	    				'action': 'ainow_setup_uuid_global',
	    				'data': sessionStorage.CURRENT_UUID
	    			},
	    			function(response) { console.log( "UUID is set!" ); }
	    		);
	    	} else { registerNewUUID(); }
	    } else { registerNewUUID(); }
	} else {
	    console.log( "The clients browser don't support localStorage. So I can't work :-(" );
	}

	jQuery( "#ainow-load-more-posts" ).on("click", function(){
		jQuery( "#ainow-posts-list" ).append( loading_sign );
		jQuery.post(
			ajaxurl,
			{
				'action': 'ainow_load_more_posts',
				'data': ""
			},
			function(response) { 
				jQuery( "#ainow-lsh" ).remove();
				if ( response != "" ) { jQuery( "#ainow-posts-list" ).append( response ); }
				else { jQuery( "#ainow-load-more-posts" ).remove(); }
			}
		);
	});
});

// Register new UUID function
function registerNewUUID() {
	jQuery.post(
	    ajaxurl, 
	    {
	        'action': 'ainow_uuid_maker',
	        'data': ""
	    }, 
	    function(response) {
	    	if ( response == "-1" ) { console.log( "Something went very wrong in function: ainow_uuid_maker();" ); }
	    	else {
	    	 	localStorage.AINOW_UUID += "&"+ response;
	    	}
	    }
	);
}