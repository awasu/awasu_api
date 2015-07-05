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
#                 If you use this software, an acknowledgment is requested
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

""" Provides a command-line interface to the Awasu API.
"""

import sys
import os
import urllib2
import BaseHTTPServer
from xml.etree import ElementTree
import json

from api import AwasuApi
from api import convert_api_args

# ---------------------------------------------------------------------

def print_help() :
    script_name = os.path.split(sys.argv[0])[ 1 ]
    #pylint: disable=line-too-long
    print "{} [options] [api-name] [arg1] [arg2] ...".format( script_name )
    print "  Calls the Awasu API."
    print
    print "Options:"
    print "  -u --url       Invocation URL (default={})".format( AwasuApi.DEFAULT_API_URL )
    print "  -t --token     Awasu API token."
    print "  -h --headers   Output the HTTP response headers."
    print "  -r --raw       Output the raw response."
    print
    print """The arguments following [api-name] are passed on to Awasu via the API call and are specified as they would normally be in a URL (i.e. "key=val" pairs).

For API calls that expect POST data, pipe the data into stdin.

Examples:
  Get a full list of channels and their configuration:
    {script_name} channels/list verbose=1

  Get the summary page for a channel:
    {script_name} --raw channels/get name=... sfim=all

  Update the configuration for a report:
    {script_name} reports/update id=... <newReportConfig.xml

  Add an item to the default workpad:
    {script_name} workpads/addItem id=@ url=http://awasu.com title=Awasu
""".format( script_name=script_name )
    #pylint: enable=line-too-long

# ---------------------------------------------------------------------

# initialize
sys.tracebacklimit = 0

# parse the command-line arguments
url = None
token = None
dump_headers = False
raw_mode = False
import getopt
try :
    opts , args = getopt.getopt(
        sys.argv[1:] ,
        "u:t:hr?" ,
        ["url=","token=","headers","raw","help"]
    )
except getopt.GetoptError , err :
    raise Exception( "Can't parse arguments: {}".format( err ) )
for opt,val in opts :
    if opt in ("-u","--url") :
        url = val
    elif opt in ("-t","--token") :
        token = val
    elif opt in ("-h","--headers") :
        dump_headers = True
    elif opt in ("-r","--raw") :
        raw_mode = True
    elif opt in ("-?","--help") :
        print_help()
        sys.exit()
    else :
        raise Exception( "Invalid command line option: {}".format( opt ) )
if len(args) == 0 :
    print_help()
    sys.exit()

# call the Awasu API
awasu_api = AwasuApi( url , token )
post_data = None if os.isatty(0) else sys.stdin.read()
try :
    api_args = convert_api_args( args[1:] )
    hdrs , body = awasu_api.call_api(
        args[0] , api_args , post_data , raw_mode , True
    )
except urllib2.HTTPError , xcptn :
    print "HTTP {}: {}".format( xcptn.code , BaseHTTPServer.BaseHTTPRequestHandler.responses[xcptn.code][0] )
    print xcptn.read()
    hdrs = {}
    body = None

# output the results
if dump_headers :
    print "Response headers:"
    if len(hdrs) > 0 :
        max_key_len = max( [ len(k) for k in hdrs.keys() ] )
        for key,val in hdrs.items() :
            print "  {} {}".format( (str(key)+":").ljust(1+max_key_len) , val )
    print
if isinstance( body , ElementTree.Element ) :
    print ElementTree.tostring( body )
elif isinstance( body , dict ) :
    print json.dumps( body )
elif body :
    print body
