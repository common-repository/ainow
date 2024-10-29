jQuery( document ).ready(function(){
	jQuery( "#ainow-legend-controller" ).on("click", function(){
		if ( jQuery( "#ainow-legend" ).hasClass( "active" ) ) {
			jQuery( "#ainow-legend-controller #indentifier" ).html( "+" );
			jQuery( "#ainow-legend" ).removeClass( "active" ).slideUp( "medium" );
		} else {
			jQuery( "#ainow-legend-controller #indentifier" ).html( "-" );
			jQuery( "#ainow-legend" ).addClass( "active" ).slideDown( "medium" );
		}
	});
});