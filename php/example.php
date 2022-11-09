<?php
// This is a simple example of how to call the Awasu API.

require_once( dirname(__FILE__) . '/AwasuApi.php' ) ;

// initialize
define( 'API_URL' , 'http://localhost:2604' ) ;
define( 'API_TOKEN' , '' ) ;

// initialize
$apiArgs = array() ; // nb: list of "key=val" values to pass into the API call
$postData = '' ; // some API calls expect data to be passed in
$rawMode = false ; // set this to true if you want to see the raw response sent back by Awasu

// call the Awasu API
$awasuApi = new AwasuApi( API_URL, API_TOKEN ) ;
$response = $awasuApi->callApi( 'stats', $apiArgs, $postData, $rawMode, true ) ;
$responseHeaders = $response[0] ;
$responseBody = $response[1] ;

// output the response
print "<pre>" ;
print "Response headers:\n" ;
foreach( $responseHeaders as $key => $val )
    print "  $key = $val\n" ;
print "\n" ;
if ( $rawMode ) {
    // just output the raw response
    print $responseBody ;
} else {
    // output a formatted dump of the XML response
    if ( gettype( $responseBody ) == 'object' )
        AwasuApiUtils::dumpXmlTree( $responseBody ) ;
    else
        print_r( $responseBody ) ;
}
print "</pre>" ;
