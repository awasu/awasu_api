""" This class provides access to the Awasu API.

https://awasu.com/api
"""

# COPYRIGHT:    (c) Awasu Pty. Ltd. 2015 (all rights reserved).
#               Unauthorized use of this code is prohibited.
#
# LICENSE:      This software is provided 'as-is', without any express
#               or implied warranty.
#
#               In no event will the author be held liable for any damages
#               arising from the use of this software.
#
#               Permission is granted to anyone to use this software
#               for any purpose and to alter it and redistribute it freely,
#               subject to the following restrictions:
#
#               - The origin of this software must not be misrepresented;
#                 you must not claim that you wrote the original software.
#                 If you use this software, an acknowledgement is requested
#                 but not required.
#
#               - Altered source versions must be plainly marked as such,
#                 and must not be misrepresented as being the original software.
#                 Altered source is encouraged to be submitted back to
#                 the original author so it can be shared with the community.
#                 Please share your changes.
#
#               - This notice may not be removed or altered from any
#                 source distribution.

from xml.etree import ElementTree
import json
import zlib
import re

try:
    from urllib2 import Request, urlopen
except ImportError:
    from urllib.request import Request, urlopen
try:
    from StringIO import StringIO
except ImportError:
    from io import StringIO

from awasu_api.utils import safe_xml_string, bool_string

# ---------------------------------------------------------------------

class AwasuApiException( Exception ):
    """Exception class for AwasuApi."""
    def __init__( self, msg ):
        # NOTE: We often receive error messages from Awasu as part of
        # an XML/JSON response, so we auto-convert it to Unicode here.
        if isinstance( msg, str ):
            super().__init__( msg )
        else:
            super().__init__( msg.decode( "utf-8" ) )

# ---------------------------------------------------------------------

class AwasuApi: #pylint: disable=too-many-public-methods
    """Provides access to the Awasu API."""

    # NOTE: Since we're dealing with XML/JSON/HTML responses from Awasu,
    # strings are generally encoded bytes, not Unicode.

    DEFAULT_API_URL = "http://localhost:2604"

    def __init__( self, url=None, token=None ):
        self.api_url = url if url else AwasuApi.DEFAULT_API_URL
        self.api_token = token

    def call_api( self, api_name, api_args=None, post_data=None, raw=False, return_headers=False ): #pylint: disable=too-many-arguments,too-many-locals,too-many-branches
        #pylint: disable=line-too-long
        """ This is the main entry point for calling the Awasu API.
        Most of the time, you won't need to call this method directly, since helper methods are provided for the most common operations.

        In raw mode, this function will return the raw response returned by Awasu (typically XML, but it can be something else if you pass in a "format=" parameter).

        Otherwise, it will parse the response and return an ElementTree tree (for XML) or a JSON dictionary. You can then do things like:
            # print the name of every channel
            xml = api.call_api( "channels/list" )
            for node in xml.findall( "./channel" ):
                print node.find( "name" ).text
            # dump the contents of the default workpad
            resp = api.call_api( "workpads/get", { "id": "@", "format": "json" } )
            for item in resp["workpad"]["workpadItems"]:
                print "%s => %s" % (item["title"], item["url"])
        """
        #pylint: enable=line-too-long
        # initialize the API arguments
        if not api_args:
            api_args = {}
        if self.api_token:
            api_args["token"] = self.api_token
        # generate the request URL
        url = "{}/{}".format( self.api_url, api_name )
        if not url.startswith( "http://" ):
            url = "http://" + url
        # add the API arguments to the POST data
        # NOTE: We do this to avoid exposing the token in GET request URL's.
        if len(api_args) > 0:
            if not post_data:
                # no POST data was supplied - create a new data block
                post_data = ElementTree.Element( "apiArgs" )
                api_args_node = post_data
            else:
                # load the POST data
                post_data = ElementTree.fromstring( post_data )
                # NOTE: When parsing the POST data, Awasu stops after it has processed
                # the <apiArgs> node, so it's advantageous to put it first (to avoid
                # having to parse the entire XML tree).
                api_args_node = ElementTree.Element( "apiArgs" )
                post_data.insert( 0, api_args_node )
            # add the API arguments to the POST data (as <apiArgs> attributes)
            for key, val in api_args.items():
                api_args_node.set( key, str(val) )
            # convert the POST data back to a string
            post_data = ElementTree.tostring( post_data )
        # send the request
        req = Request( url, post_data, {"Accept-Encoding":"deflate"} )
        resp = urlopen( req )
        hdrs = str( resp.info() )
        body = resp.read()
        resp.close() # nb: try to stop socket exhaustion when stress-testing
        # return the response
        hdrs_dict = {} # FIXME! how to get the HTTP status code/message?
        for line_buf in StringIO(hdrs):
            mo = re.match( "^(\\s*[^()<>@,;:\\\"/\\[\\]?={} ]+)\\s*:\\s*(.*)$", line_buf )
            if mo:
                hdrs_dict[ mo.group(1) ] = mo.group(2).strip()
        if hdrs_dict.get( "Content-Encoding" ) == "deflate":
            body = zlib.decompressobj( -zlib.MAX_WBITS ).decompress( body )
        if not raw:
            if get_response_format( api_args ) == "xml":
                body = ElementTree.fromstring( body ) if body.strip() else None
            elif get_response_format( api_args ) == "json":
                body = json.loads( body ) if body.strip() else None
        return ( hdrs_dict, body ) if return_headers else body

    def get_awasu_build_info( self ):
        """Get the Awasu build info."""
        return self.call_api_and_check( "buildInfo", {"format":"json"} )[ "buildInfo" ]

    def get_awasu_user_info( self ):
        """Get the Awasu user info."""
        return self.call_api_and_check( "userInfo", {"format":"json"} )[ "userInfo" ]

    def get_awasu_stats( self ):
        """Get the Awasu stats."""
        return self.call_api_and_check( "stats", {"format":"json"} )[ "stats" ]

    def get_awasu_activity_log( self, nLines=None ):
        """Get the Awasu Activity log."""
        return self.call_api_and_check( "logs/activity", {"lines":nLines}, None, True )

    def get_awasu_error_log( self, nLines=None ):
        """Get the Awasu Error log."""
        return self.call_api_and_check( "logs/error", {"lines":nLines}, None, True )

    def get_channel_folders( self, tree=True ):
        """Get the channel folders."""
        if tree:
            return self.call_api_and_check( "channels/folders/tree", {"format":"json"} )[ "channelFolder" ]
        else:
            return self.call_api_and_check( "channels/folders/list", {"format":"json"} )[ "channelFolders" ]

    def create_channel_folder( self, folderName, parent_folder=None, insert_after=None ):
        """Create a new channel folder."""
        api_args = { "name": folderName, "format": "json" }
        if parent_folder:
            api_args["parent"] = parent_folder
        if insert_after:
            api_args["after"] = insert_after
        return self.call_api_and_check( "channels/folders/create", api_args )["status"]["id"]

    def delete_channel_folder( self, id ): #pylint: disable=redefined-builtin
        """Delete a channel folder."""
        self.call_api_and_check( "channels/folders/delete", {"id":id,"format":"json"} )

    def get_channel_filters( self ):
        """Get the channel filters."""
        return self.call_api_and_check( "channels/filters/list", {"format":"json"} )[ "channelFilters" ]

    def get_channels( self, ids=None, verbose=False ):
        """Get the configuration details for the specified channels."""
        api_args = { "format": "json", "verbose": verbose }
        api_args = add_ids_to_api_args( api_args, ids )
        return self.call_api_and_check( "channels/list", api_args )[ "channels" ]

    def get_channel_stats( self, ids=None ):
        """Get the statistics for the specified channels."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        return self.call_api_and_check( "channels/stats", api_args )[ "channels" ]

    def get_channel_errors( self, ids=None ):
        """Get the error log for the specified channels."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        return self.call_api_and_check( "channels/errors", api_args )[ "channels" ]

    def get_channel_summary( self, id ): #pylint: disable=redefined-builtin
        """Get the summary for the specified channel."""
        if isinstance( id, list ):
            raise AwasuApiException( "Can't get multiple channels." )
        return self.call_api_and_check( "channels/get", {"id":id,"format":"html"} )

    def create_channel( self, post_data ):
        """Create a new channel."""
        resp = self.call_api_and_check( "channels/create", {"format":"json"}, post_data )
        return int( resp["status"]["id"] )
    def create_channel_by_url( self, url ):
        """Create a new channel (downloaded from the specified URL)."""
        return self.create_channel(
            "<channel type='standard'>" \
            "<feedUrl> {} </feedUrl>" \
            "</channel>" \
            .format( safe_xml_string( url ) )
        )
    def create_plugin_channel( self, plugin_path, plugin_params ):
        """Create a new plugin channel."""
        params_xml = "".join( [
            "<param name='{}'> {} </param>".format( safe_xml_string(key), safe_xml_string(val) ) \
                for key,val in plugin_params.items()
        ] )
        return self.create_channel(
            "<channel type='plugin'>" \
              "<pluginChannel path='{}'> {} </pluginChannel>" \
            "</channel>".format(
                safe_xml_string(plugin_path), params_xml
            )
        )
    def create_search_channel( self, query_string, search_locs=None, adv_syntax=False ):
        """Create a new search channel."""
        attrs = { "advancedSyntax": bool_string(adv_syntax) }
        if search_locs:
            attrs["searchInTitles"] = bool_string( "titles" in search_locs )
            attrs["searchInDescriptions"] = bool_string( "descriptions" in search_locs )
        attrs = " ".join( [
            "{}='{}'".format( key, val ) for key, val in attrs.items()
        ] )
        return self.create_channel(
            "<channel type='search'>" \
              "<searchQuery {}> {} </searchQuery>" \
            "</channel>" \
            .format( attrs, safe_xml_string(query_string) )
        )

    def delete_channels( self, ids ):
        """Delete the specified channels."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        resp = self.call_api_and_check( "channels/delete", api_args )
        for channel in resp["channels"]:
            if channel["status"] != "OK":
                raise AwasuApiException( "Can't delete channel \"{name}\" ({id}): {status}".format( **channel ) )

    def get_reports( self, ids=None, verbose=False ):
        """Get the configuration details for the specified reports."""
        api_args = { "format": "json", "verbose": verbose }
        api_args = add_ids_to_api_args( api_args, ids )
        return self.call_api_and_check( "reports/list", api_args )[ "channelReports" ]

    def run_reports( self, ids ):
        """Run the specified reports."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        resp = self.call_api_and_check( "reports/run", api_args )
        for report in resp["channelReports"]:
            if report["status"] != "OK":
                raise AwasuApiException( "Can't run report \"{name}\" ({id}): {status}".format( **report ) )

    def get_report( self, id ): #pylint: disable=redefined-builtin
        """Run the specified report and return the result."""
        if isinstance( id, list ):
            raise AwasuApiException( "Can't get multiple reports." )
        return self.call_api_and_check( "reports/get", {"id":id,"format":"html"} )

    def create_report( self, post_data ):
        """Create a new report."""
        resp = self.call_api_and_check( "reports/create", {"format":"json"}, post_data )
        return resp["status"]["id"]
    def create_channel_filter_report( self, name, cf_name, descrip=None ):
        """Create a new report, based on the specified channel filter."""
        return self.create_report(
            "<channelReport>" \
              "<name> {} </name>" \
              "<description> {} </description>" \
              "<dataSource type='channelFilter'>" \
                "<channelFilterName> {} </channelFilterName>" \
              "</dataSource>" \
            "</channelReport>".format(
                safe_xml_string(name), safe_xml_string(descrip), safe_xml_string(cf_name)
            )
        )
    def create_channel_folders_report( self, name, cf_ids, include_subfolders, descrip=None ):
        """Create a new report, based on the specified channel folders."""
        if cf_ids:
            channel_folders_xml = "".join( [ #pylint: disable=redefined-builtin
                "<channelFolder id='{}'/>".format( safe_xml_string(id) ) \
                    for id in cf_ids \
            ] )
        else:
            channel_folders_xml = ""
        return self.create_report(
            "<channelReport>" \
              "<name> {} </name>" \
              "<description> {} </description>" \
              "<dataSource type='channelFolders' includeSubFolders='{}'> {} </dataSource>" \
            "</channelReport>".format(
                safe_xml_string(name), safe_xml_string(descrip), "true" if include_subfolders \
                    else "false", channel_folders_xml
            )
        )
    def create_workpad_report( self, name, workpad_id, descrip=None ):
        """Create a new report, based on the specified workpad."""
        return self.create_report(
            "<channelReport>" \
              "<name> {} </name>" \
              "<description> {} </description>" \
              "<dataSource type='workpad'>" \
                "<workpad id='{}'/>" \
              "</dataSource>" \
            "</channelReport>".format(
                safe_xml_string(name), safe_xml_string(descrip), safe_xml_string(workpad_id)
            )
        )

    def delete_reports( self, ids ):
        """Delete the specified reports."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        resp = self.call_api_and_check( "reports/delete", api_args )
        for report in resp["channelReports"]:
            if report["status"] != "OK":
                raise AwasuApiException( "Can't delete report \"{name}\" ({id}): {status}".format( **report ) )

    def get_workpads( self, ids=None ):
        """Get the configuration details for the specified workpads."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        return self.call_api_and_check( "workpads/list", api_args )[ "workpads" ]

    def get_workpad( self, id ): #pylint: disable=redefined-builtin
        """Get the contents of the specified workpad."""
        if isinstance( id, list ):
            raise AwasuApiException("Can't get multiple workpads.")
        return self.call_api_and_check( "workpads/get", {"id":id,"format":"json"} )[ "workpad" ]

    def get_default_workpad( self ):
        """Get the default workpad."""
        try:
            return self.get_workpad( "@" )
        except AwasuApiException as xcptn:
            if str( xcptn ) == "No workpads were selected.":
                return None
            raise

    def get_workpad_feed( self, id ): #pylint: disable=redefined-builtin
        """Get the feed XML for the specified workpad."""
        xml = self.call_api_and_check( "workpads/feed", {"id":id} )
        nodes = xml.findall( "./errorMsg" )
        if len(nodes) > 0:
            raise AwasuApiException( nodes[0].text )
        return ElementTree.tostring( xml )

    def add_workpad_item( self, workpad_ids, url, title=None, custom_fields=None ):
        """Add a new item to the specified workpads."""
        api_args = { "url": url, "title": title, "format": "json" }
        if custom_fields:
            api_args.update( custom_fields )
        api_args = add_ids_to_api_args( api_args, workpad_ids )
        resp = self.call_api_and_check( "workpads/addItem", api_args )
        for workpad in resp["workpads"]:
            if workpad["status"] != "OK":
                raise AwasuApiException( "Can't add item to workpad \"{name}\" ({id}): {status}".format( **workpad ) )

    def create_workpad( self, name, descrip=None ):
        """Create a new workpad."""
        post_data = \
            "<workpad>" \
              "<name> {} </name>" \
              "<description> {} </description>" \
            "</workpad>".format(
                safe_xml_string(name), safe_xml_string(descrip)
            )
        resp = self.call_api_and_check( "workpads/create", {"format":"json"}, post_data )
        return resp["status"]["id"]

    def delete_workpads( self, ids ):
        """Delete the specified workpads."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        resp = self.call_api_and_check( "workpads/delete", api_args )
        for workpad in resp["workpads"]:
            if workpad["status"] != "OK":
                raise AwasuApiException( "Can't delete workpad \"{name}\" ({id}): {status}".format( **workpad ) )

    def get_feed_items( self, ids=None ):
        """Get the specified feed items."""
        api_args = add_ids_to_api_args( {"format":"json"}, ids )
        return self.call_api_and_check( "feedItems/get", api_args )[ "feedItems" ]

    def run_search_query( self, query_string, search_locs=None, results_fmt="excerpt", adv_syntax=False, page_no=1, page_size=10 ): #pylint: disable=line-too-long,too-many-arguments
        """Run the specified search query."""
        api_args = {
            "query": query_string,
            "fidf": results_fmt,
            "advsyn": adv_syntax,
            "page": page_no,
            "pageSize": page_size,
            "format": "json"
        }
        if search_locs:
            api_args["locations"] = ",".join(search_locs) if isinstance(search_locs,list) else search_locs
        return self.call_api_and_check( "search/query", api_args )[ "searchResults" ]

    def call_api_and_check( self, api_name, api_args=None, post_data=None, raw=False ):
        """Call the Awasu API and check for errors."""
        if api_args is None:
            api_args = {}
        api_args["quiet"] = False
        response = self.call_api( api_name, api_args, post_data, raw, True )
        # FIXME! Since we can't get the HTTP status code, we can't check it:-/
        if not raw:
            fmt = get_response_format( api_args )
            if fmt == "json":
                if response[1] and "status" in response[1] and "errorMsg" in response[1]["status"]:
                    raise AwasuApiException( response[1]["status"]["errorMsg"] )
            elif fmt == "xml":
                if response[1] is not None:
                    node = response[1].find( "./errorMsg" )
                    if node is not None:
                        raise AwasuApiException( node.text )
            elif fmt == "html":
                mo = re.search( b"<td class=\"error-msg value\">(.+?)</td>", response[1] )
                if mo:
                    raise AwasuApiException( mo.group(1).strip() )
        return response[1]

    def __str__( self ):
        return "AwasuApi @ {}".format( self.api_url )

# ---------------------------------------------------------------------

def add_ids_to_api_args( api_args, ids ):
    """Add the specified ID's to the argument list."""
    if isinstance( ids, list ):
        api_args["id"] = ",".join( [ str(x) for x in ids ] )
    elif ids:
        api_args["id"] = ids
    return api_args

# - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

def convert_api_args( api_args ):
    """Convert a list of "key=val" arguments to a dictionary."""
    api_args_dict = {}
    for arg in api_args:
        pos = arg.find( "=" )
        if pos > 0:
            api_args_dict[ arg[:pos] ] = arg[pos+1:]
    return api_args_dict

# - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

def get_response_format( api_args ):
    """Determine what the response format will be."""
    if "format" in api_args:
        return api_args["format"]
    if "f" in api_args:
        return api_args["f"]
    return "xml"
