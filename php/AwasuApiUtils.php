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

/* -------------------------------------------------------------------- */

class AwasuApiUtils
{

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

// NOTE: print_r() is unreliable - use this if you want to dump SimpleXMLElement's.
public static function dumpXmlTree( $xmlTree, $prefix='' ) {
    AwasuApiUtils::__doDumpXmlTree( $xmlTree, $prefix, -1 ) ;
}

protected static function __doDumpXmlTree( $xmlNode, $prefix, $tagFieldWidth )
{
    if ( $xmlNode == null )
        return ;

    // dump the XML node
    print $prefix . $xmlNode->getName() . ':' ;
    if ( $tagFieldWidth > 0 )
        print str_repeat( ' ', $tagFieldWidth - strlen($xmlNode->getName()) + 1 ) ;
    if ( trim( (string)$xmlNode ) != '' )
        print utf8_encode( (string)$xmlNode ) ;
    print "\n" ;

    // dump any attributes
    foreach ( $xmlNode->attributes() as $attrName => $attrVal )
        print "$prefix  @$attrName=" . utf8_encode($attrVal) . "\n" ;

    // dump any child nodes
    if ( count( $xmlNode->children() ) > 0 )
    {
        $maxTagLen = 0 ;
        foreach ( $xmlNode->children() as $childNode )
            $maxTagLen = max( strlen( $childNode->getName() ), $maxTagLen ) ;
        foreach ( $xmlNode->children() as $childNode )
            AwasuApiUtils::__doDumpXmlTree( $childNode, "$prefix    ", $maxTagLen ) ;
    }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public static function safeXmlString( $val )
{
    // convert the string into something that's safe for XML
    return str_replace(
        array('&','<','>','"'),
        array('&amp;','&lt;','&gt;','&quot;'),
        $val
    ) ;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

public static function boolString( $val ) { return $val ? 'true' : 'false ' ; }

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

} ;

/* -------------------------------------------------------------------- */

?>
