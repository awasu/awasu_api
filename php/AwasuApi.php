<?php
/*
* COPYRIGHT:    (c) Awasu Pty. Ltd. 2015 (all rights reserved).
*               Unauthorized use of this code is prohibited.
*
* LICENSE:      This software is provided 'as-is', without any express
*               or implied warranty.
*
*               In no event will the author be held liable for any damages
*               arising from the use of this software.
*
*               Permission is granted to anyone to use this software
*               for any purpose and to alter it and redistribute it freely,
*               subject to the following restrictions:
*
*               - The origin of this software must not be misrepresented;
*                 you must not claim that you wrote the original software.
*                 If you use this software, an acknowledgment is requested
*                 but not required.
*
*               - Altered source versions must be plainly marked as such,
*                 and must not be misrepresented as being the original software.
*                 Altered source is encouraged to be submitted back to
*                 the original author so it can be shared with the community.
*                 Please share your changes.
*
*               - This notice may not be removed or altered from any
*                 source distribution.
*/

require_once( dirname(__FILE__) . '/AwasuApiUtils.php' ) ;

class AwasuApiException extends Exception { }

/* -------------------------------------------------------------------- */

class AwasuApi
{

private $mApiUrl ;
private $mApiToken ;

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function AwasuApi( $apiUrl=null , $apiToken=null )
{
    // initialize 
    if ( @$apiUrl != '' )
        $this->mApiUrl = $apiUrl ;
    else 
        $this->mApiUrl = 'http://127.0.0.1:2604' ;
    $this->mApiToken = $apiToken ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

// This is the main entry point for calling the Awasu API.
// Most of the time, you won't need to call this method directly, helper methods 
// are provided for the most common operations.
//
// In raw mode, this function will return the raw response returned by Awasu
// (typically XML, but it can be something else if you pass in a "format=" parameter).
// 
// Otherwise, it will parse the response and return an SimpleXMLElement tree (for XML) 
// or a JSON dictionary. You can then do things like:
//
//     // print the name of every channel
//     $xml = $awasuApi->callApi( 'channels/list' ) ;
//     foreach ( $xml->xpath('channel/name') as $node )
//         print (string)$node . "\n" ;
//   
//     // dump the contents of the default workpad
//     $json = $awasuApi->callApi( 'workpads/get' , array('id'=>'@','format'=>'json') ) ;
//     foreach ( $json['workpad']['workpadItems'] as $workpadItem )
//         print $workpadItem['title'] . ' => ' . $workpadItem['url'] . "\n" ;

public function callApi( $apiName , $apiArgs=null , $postData=null , $rawMode=false , $returnHttpHeaders=false )
{
    // generate the request URL
    $url = $this->mApiUrl . '/' . $apiName ;
    if ( substr($url,0,7) != 'http://' )
        $url = "http://$url" ;
        
    // initialize the API arguments
    if ( $apiArgs == null )
        $apiArgs = array() ;
    if ( @$this->mApiToken != '' )
        $apiArgs['token'] = $this->mApiToken ;
        
    // add the API arguments to the POST data
    // NOTE: We do this to avoid exposing the token in GET requests.
    if ( count($apiArgs) > 0 )
    {
        $dom = new DOMDocument() ;
        if ( $postData == null )
        {
            // no POST data was supplied - create a new data block
            $apiArgsNode = $dom->appendChild( $dom->createElement( 'apiArgs' ) ) ;
        }
        else 
        {
            // load the POST data
            $dom->loadXML( $postData ) ;
            $rootNode = $dom->documentElement ;
            // NOTE: When parsing the POST data, Awasu stops after it has processed 
            // the <apiArgs> node, so it's advantageous to put it first (to avoid 
            // having to parse the entire XML tree).
            $apiArgsNode = $dom->createElement( 'apiArgs' ) ;
            $apiArgsNode = $rootNode->insertBefore( $apiArgsNode , $rootNode->firstChild ) ;
        }
        // add the API arguments to the POST data (as <apiArgs> attributes)
        foreach ( $apiArgs as $arg => $val )
            $apiArgsNode->setAttribute( $arg , $val ) ;
        // convert the POST data back to a string
        $postData = $dom->saveXML() ;
    }

    // send the request 
    // NOTE: We used to do this using curl, but it's so freaking difficult
    //  to get it working under Windows, we reverted back to the old way :-/
    $params = array(
        'http' => array(
            'method' => 'POST' , 
            'content' => $postData , 
            'header' => "Accept-Encoding: deflate\r\n" ,
        ) 
    ) ;
    $ctx = stream_context_create( $params ) ;
    $fp = @fopen( $url , 'rb' , false , $ctx ) ;
    if ( $fp == null )
        throw new AwasuApiException( "Can't connect to Awasu." ) ;
    $response = stream_get_meta_data($fp) ;
    $responseHeaders = $response[ 'wrapper_data' ] ;
    $responseBody = @stream_get_contents( $fp ) ;
    fclose( $fp ) ;

    // parse the HTTP response headers 
    $responseHeadersDict = array() ;
    foreach ( $responseHeaders as $responseHeader )
    {
        $pos = strpos( $responseHeader , ':' ) ;
        if ( $pos === FALSE )
            $responseHeadersDict[ $responseHeader ] = null ;
        else
            $responseHeadersDict[ substr($responseHeader,0,$pos) ] = trim( substr( $responseHeader , $pos+1 ) ) ;
    }
    
    // check for compressed responses 
    if ( @$responseHeadersDict['Content-Encoding'] == 'deflate' )
        $responseBody = gzinflate( $responseBody ) ;
    
    // return the response
    if ( ! $rawMode )
    {
        switch( AwasuApi::getResponseFormat( $apiArgs ) )
        {
            case 'xml':
                $responseBody = simplexml_load_string( $responseBody ) ;
                break ;
            case 'json':
                $responseBody = json_decode( $responseBody , true ) ;
                break ;
            default:
                break ;
        }
    }
    return $returnHttpHeaders ? array($responseHeadersDict,$responseBody) : $responseBody ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getAwasuBuildInfo()
{
    // get the Awasu build info
    $response = $this->callApiAndCheck( 'buildInfo' , array('format'=>'json') ) ;
    return $response[ 'buildInfo' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getAwasuUserInfo()
{
    // get the Awasu user info
    $response = $this->callApiAndCheck( 'userInfo' , array('format'=>'json') ) ;
    return $response[ 'userInfo' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getAwasuStats()
{
    // get the Awasu status
    $response = $this->callApiAndCheck( 'stats' , array('format'=>'json') ) ;
    return $response[ 'stats' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getAwasuActivityLog( $nLines=null )
{
    // get the Awasu Activity log
    return $this->callApiAndCheck( 'logs/activity' , array('lines'=>$nLines) , null , true ) ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getAwasuErrorLog( $nLines=null )
{
    // get the Awasu Error log
    return $this->callApiAndCheck( 'logs/error' , array('lines'=>$nLines) , null , true ) ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getChannelFolders( $asTree=true )
{
    // get the channel folders
    if ( $asTree ) 
    {
        $response = $this->callApiAndCheck( 'channels/folders/tree' , array('format'=>'json') ) ;
        return $response[ 'channelFolder' ] ; 
    }
    else
    {
        $response = $this->callApiAndCheck( 'channels/folders/list' , array('format'=>'json') ) ;
        return $response[ 'channelFolders' ] ; 
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function createChannelFolder( $folderName , $parentFolder=null , $insertAfterFolder=null )
{
    // create the new channel folder
    $apiArgs = array( 'name' => $folderName , 'format'=>'json' ) ;
    $apiArgs['parent'] = $parentFolder ;
    $apiArgs['after'] = $insertAfterFolder ;
    $response = $this->callApiAndCheck( 'channels/folders/create' , $apiArgs ) ;
    return $response['status']['id'] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function deleteChannelFolder( $folderId )
{
    // delete the channel folder
    $apiArgs = array( 'id' => $folderId , 'format'=>'json' ) ;
    $json = $this->callApiAndCheck( 'channels/folders/delete' , $apiArgs ) ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getChannelFilters()
{
    // get the channel filters
    $response = $this->callApiAndCheck( 'channels/filters/list' , array('format'=>'json') ) ;
    return $response[ 'channelFilters' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getChannels( $channelIds=null , $verbose=false )
{
    // get the configuration details for the specified channels
    $apiArgs = array( 'format'=>'json' , 'verbose'=>$verbose ) ;
    $apiArgs = $this->addIdsToApiArgs( $apiArgs , $channelIds ) ;
    $response = $this->callApiAndCheck( 'channels/list' , $apiArgs ) ;
    return $response[ 'channels' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getChannelStats( $channelIds=null )
{
    // get the statistics for the specified channels
    $apiArgs = $this->addIdsToApiArgs( array('format'=>'json') , $channelIds ) ;
    $response = $this->callApiAndCheck( 'channels/stats' , $apiArgs ) ;
    return $response[ 'channels' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getChannelErrors( $channelIds=null )
{
    // get the error log for the specified channels
    $apiArgs = $this->addIdsToApiArgs( array('format'=>'json') , $channelIds ) ;
    $response = $this->callApiAndCheck( 'channels/errors' , $apiArgs ) ;
    return $response[ 'channels' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getChannelSummary( $channelId ) 
{
    // get the summary for the specified channel
    if ( is_array( $channelId ) )
        throw new AwasuApiException( "Can't get multiple channels." ) ;
    return $this->callApiAndCheck( 'channels/get' , array('id'=>$channelId,'format'=>'html') ) ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function createChannel( $postData ) 
{
    // create a new channel
    $json = $this->callApiAndCheck( 'channels/create' , array('format'=>'json') , $postData ) ;
    return $json['status']['id'] ;
}

public function createChannelByUrl( $url )
{
    // create a new channel (downloaded from the specified URL)
    return $this->createChannel
    (
        '<channel type="standard">'
        . '<feedUrl>' . AwasuApiUtils::safeXmlString($url) . '</feedUrl>'
        . '</channel>' 
    ) ; 
}

public function createPluginChannel( $pluginPath , $pluginParams=null )
{
    // create a new plugin channel
    $paramsXml = array() ;
    if ( $pluginParams != null )
    {
        foreach ( $pluginParams as $key => $val )
            $paramsXml[] = '<param name="' . AwasuApiUtils::safeXmlString($key) . '">' . AwasuApiUtils::safeXmlString($val) . '</param>' ;
    }
    return $this->createChannel
    (
        '<channel type="plugin">'
        . '<pluginChannel path="' . AwasuApiUtils::safeXmlString($pluginPath) . '">'
        . implode( '' , $paramsXml ) 
        . '</pluginChannel>'
        . '</channel>'
    ) ;
}

public function createSearchChannel( $queryString , $searchLocations=null , $advancedSyntax=false )
{
    // create a new search channel
    $attrs = array( 'advancedSyntax="' . AwasuApiUtils::boolString($advancedSyntax) . '"' ) ;
    if ( $searchLocations != null )
    {
        $attrs[] = 'searchInTitles="' 
                   . AwasuApiUtils::boolString( in_array('title',$searchLocations) || in_array('titles',$searchLocations) )
                   . '"' ;
        $attrs[] = 'searchInDescriptions="'
                   . AwasuApiUtils::boolString( in_array('description',$searchLocations) || in_array('descriptions',$searchLocations) )
                   . '"' ;
    }
    return $this->createChannel
    (
        '<channel type="search">'
        . '<searchQuery ' . implode(' ',$attrs) . '>'
        . AwasuApiUtils::safeXmlString( $queryString )
        . '</searchQuery>'
        . '</channel>'
    ) ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function deleteChannels( $channelIds ) 
{
    // delete the specified channels
    $apiArgs = $this->addIdsToApiArgs( array('format'=>'json') , $channelIds ) ;
    $json = $this->callApiAndCheck( 'channels/delete' , $apiArgs ) ; 
    foreach ( $json['channels'] as $channelStatus )
    {
        if ( $channelStatus['status'] != 'OK' )
            throw new AwasuApiException( 'Can\'t delete channel "' . $channelStatus['name'] . '" (' . $channelStatus['id'] . '): ' . $channelStatus['status'] ) ;
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getReports( $reportIds=null , $verbose=false )
{
    // get the configuration details for the specified reports
    $apiArgs = array( 'format'=>'json' , 'verbose'=>$verbose ) ;
    $apiArgs = $this->addIdsToApiArgs( $apiArgs , $reportIds ) ;
    $response = $this->callApiAndCheck( 'reports/list' , $apiArgs ) ;
    return $response[ 'channelReports' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function runReports( $reportIds ) 
{
    // run the specified reports
    $apiArgs = $this->addIdsToApiArgs( array('format'=>'json') , $reportIds ) ;
    $json = $this->callApiAndCheck( 'reports/run' , $apiArgs ) ; 
    foreach ( $json['channelReports'] as $reportStatus )
    {
        if ( $reportStatus['status'] != 'OK' )
            throw new AwasuApiException( 'Can\'t run report "' . $reportStatus['name'] . '" (' . $reportStatus['id'] . '): ' . $reportStatus['status'] ) ;
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getReport( $reportId ) 
{
    // run the specified report and return the result
    if ( is_array( $reportId ) )
        throw new AwasuApiException( "Can't get multiple reports." ) ;
    return $this->callApiAndCheck( 'reports/get' , array('id'=>$reportId,'format'=>'html') ) ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function createReport( $postData )
{
    // create a new report
    $json = $this->callApiAndCheck( 'reports/create' , array('format'=>'json') , $postData ) ;
    return $json['status']['id'] ;
}

public function createChannelFilterReport( $reportName , $channelFilterName , $reportDescription=null )
{
    // create a new report, based on the specified channel filter
    return $this->createReport
    (
        '<channelReport>'
        . '<name>' . AwasuApiUtils::safeXmlString($reportName) . '</name>'
        . '<description>' . AwasuApiUtils::safeXmlString($reportDescription) . '</description>'
        . '<dataSource type="channelFilter"><channelFilterName>' . AwasuApiUtils::safeXmlString($channelFilterName) . '</channelFilterName></dataSource>'
        . '</channelReport>'
    ) ;
}

public function createChannelFoldersReport( $reportName , $channelFolderIds , $includeSubFolders , $reportDescription=null )
{
    // create a new report, based on the specified channel folders
    $channelFoldersXml = array() ;
    foreach ( $channelFolderIds as $channelFolderId )
        $channelFoldersXml[] = '<channelFolder id="' . AwasuApiUtils::safeXmlString($channelFolderId) . '" />' ;
    return $this->createReport
    (
        '<channelReport>'
        . '<name>' . AwasuApiUtils::safeXmlString($reportName) . '</name>'
        . '<description>' . AwasuApiUtils::safeXmlString($reportDescription) . '</description>'
        . '<dataSource type="channelFolders" includeSubFolders="' . ($includeSubFolders?'yes':'no') . '">' . implode('',$channelFoldersXml) . '</dataSource>'
        . '</channelReport>'
    ) ;
}

public function createWorkpadReport( $reportName , $workpadId , $reportDescription=null )
{
    // create a new report, based on the specified workpad
    return $this->createReport
    (
        '<channelReport>'
        . '<name>' . AwasuApiUtils::safeXmlString($reportName) . '</name>'
        . '<description>' . AwasuApiUtils::safeXmlString($reportDescription) . '</description>'
        . '<dataSource type="workpad"><workpad id="' . AwasuApiUtils::safeXmlString($workpadId) . '" /></dataSource>'
        . '</channelReport>'
    ) ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function deleteReports( $reportIds ) 
{
    // delete the specified reports
    $apiArgs = $this->addIdsToApiArgs( array('format'=>'json') , $reportIds ) ;
    $json = $this->callApiAndCheck( 'reports/delete' , $apiArgs ) ; 
    foreach ( $json['channelReports'] as $reportStatus )
    {
        if ( $reportStatus['status'] != 'OK' )
            throw new AwasuApiException( 'Can\'t delete report "' . $reportStatus['name'] . '" (' . $reportStatus['id'] . '): ' . $reportStatus['status'] ) ;
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getWorkpads( $workpadIds=null )
{
    // get the configuration details for the specified workpads
    $apiArgs = $this->addIdsToApiArgs( array('format'=>'json') , $workpadIds ) ;
    $response = $this->callApiAndCheck( 'workpads/list' , $apiArgs ) ;
    return $response[ 'workpads' ] ; 
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getWorkpad( $workpadId )
{
    // get the contents of the specified workpad
    if ( is_array( $workpadId ) )
        throw new AwasuApiException( "Can't get multiple workpads." ) ;
    $response = $this->callApiAndCheck( 'workpads/get' , array('id'=>$workpadId,'format'=>'json') ) ;
    return $response[ 'workpad' ] ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getDefaultWorkpad()
{
    // get the default workpad 
    try
    {
        return $this->getWorkpad( '@' ) ;
    }
    catch( AwasuApiException $e )
    {
        if ( $e->getMessage() == 'No workpads were selected.' )
            return null ;
        throw $e ;
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function getWorkpadFeed( $workpadId ) 
{
    // get the feed XML for the specified workpad
    $xml = $this->callApiAndCheck( 'workpads/feed' , array('id'=>$workpadId) ) ;
    $errorMsgNodes = $xml->xpath( 'errorMsg' ) ;
    if ( count( $errorMsgNodes ) > 0 )
        throw new AwasuApiException( (string)$errorMsgNodes[0] ) ;
    return $xml->asXML() ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function addWorkpadItem( $workpadIds , $url , $title=null , $customFields=null )
{
    // add a new item to the specified workpads
    $apiArgs = array( 'url'=>$url , 'title'=>$title , 'format'=>'json' ) ;
    if ( $customFields != null )
        $apiArgs = array_merge( $apiArgs , $customFields ) ;
    $apiArgs = $this->addIdsToApiArgs( $apiArgs , $workpadIds ) ;
    $json = $this->callApiAndCheck( 'workpads/addItem' , $apiArgs ) ; 
    foreach ( $json['workpads'] as $workpadStatus )
    {
        if ( $workpadStatus['status'] != 'OK' )
            throw new AwasuApiException( 'Can\'t add item to workpad "' . $workpadStatus['name'] . '" (' . $workpadStatus['id'] . '): ' . $workpadStatus['status'] ) ;
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function createWorkpad( $workpadName , $workpadDescription=null )
{
    // create a new workpad
    $postData = '<workpad>'
                . '<name>' . AwasuApiUtils::safeXmlString($workpadName) . '</name>'
                . '<description>' . AwasuApiUtils::safeXmlString($workpadDescription) . '</description>'
                . '</workpad>' ;
    $json = $this->callApiAndCheck( 'workpads/create' , array('format'=>'json') , $postData ) ;
    return $json['status']['id'] ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function deleteWorkpads( $workpadIds ) 
{
    // delete the specified workpads
    $apiArgs = $this->addIdsToApiArgs( array('format'=>'json') , $workpadIds ) ;
    $json = $this->callApiAndCheck( 'workpads/delete' , $apiArgs ) ; 
    foreach ( $json['workpads'] as $workpadStatus )
    {
        if ( $workpadStatus['status'] != 'OK' )
            throw new AwasuApiException( 'Can\'t delete workpad "' . $workpadStatus['name'] . '" (' . $workpadStatus['id'] . '): ' . $workpadStatus['status'] ) ;
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function runSearchQuery( $queryString , $searchLocations=null , $resultsFormat='excerpt' , $advancedSyntax=false , $pageNo=1 , $pageSize=10 )
{
    // run the specified search query
    $apiArgs = array( 'query' => $queryString , 'fidf'=>$resultsFormat , 'advsyn'=>$advancedSyntax , 'page'=>$pageNo , 'pageSize'=>$pageSize , 'format'=>'json' ) ;
    if ( $searchLocations != null )
        $apiArgs['locations'] = is_array($searchLocations) ? implode(',',$searchLocations) : $searchLocations ;
    $response = $this->callApiAndCheck( 'search/query' , $apiArgs ) ;
    return $response[ 'searchResults' ] ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

protected function callApiAndCheck( $apiName , $apiArgs=null , $postData=null , $rawMode=false )
{
    // call the Awasu API and check for errors
    $apiArgs['quiet'] = false ;
    $response = $this->callApi( $apiName , $apiArgs , $postData , $rawMode , true ) ;
    foreach ( $response[0] as $key => $val )
    {
        if ( $val == null )
        {
            $tmp = substr( $key , 9 ) ; // nb: skip over "HTTP/1.x "
            if ( $tmp != '200 OK' && $tmp != '204 OK' )
                throw new AwasuApiException( $key ) ;
            break ;
        }
    }
    if ( ! $rawMode )
    {
        switch( AwasuApi::getResponseFormat($apiArgs) )
        {
            case 'json':
                if ( @$response[1]['status']['errorMsg'] != '' )
                    throw new AwasuApiException( @$response[1]['status']['errorMsg'] ) ;
                break ;
            case 'xml':
                $errorMsgNode = $response[1]->xpath( 'errorMsg' ) ;
                if ( $errorMsgNode != null )
                    throw new AwasuApiException( (string)$errorMsgNode[0] ) ;
                break ;
            case 'html':
                if ( preg_match( "/<td class=\"error-msg value\">(.+?)<\/td>/" , @$response[1] , $matches ) )
                    throw new AwasuApiException( trim( $matches[1] ) ) ;
                break ;
            default:
                break ;
        }
    }
    return $response[1] ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public function __toString() { return 'AwasuApi @ ' . $this->mApiUrl ; }

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public static function addIdsToApiArgs( $apiArgs , $idList )
{
    // add the specified ID's to the argument list 
    if ( $idList != null )
    {
        if ( is_array( $idList ) )
            $apiArgs['id'] = AwasuApiUtils::safeXmlString( implode( ',' , $idList ) ) ;
        else 
            $apiArgs['id'] = AwasuApiUtils::safeXmlString( $idList ) ;
    }
    return $apiArgs ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public static function getResponseFormat( $apiArgs )
{
    // determine what the response format will be
    if ( isset( $apiArgs['format'] ) )
        return $apiArgs['format'] ;
    if ( isset( $apiArgs['f'] ) )
        return $apiArgs['f'] ;
    return 'xml' ;
}

/* -------------------------------------------------------------------- */

}
    
?>
