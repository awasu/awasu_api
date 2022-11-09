""" This is a simple example of how to call the Awasu API. """

import pprint
from xml.etree import ElementTree

from awasu_api import AwasuApi
from awasu_api.utils import dump_xml_tree

# ---------------------------------------------------------------------

# initialize
API_URL = "http://localhost:2604"
API_TOKEN = None
api_args = {} # nb: list of "key=val" values to pass into the API call
post_data = None # some API calls expect data to be passed in
raw_mode = False # set this to True if you want to see the raw response sent back by Awasu

# call the Awasu API
awasu_api = AwasuApi( API_URL, API_TOKEN )
hdrs, body = awasu_api.call_api( "stats", api_args, post_data, raw_mode, True )

# output the response
print( "Response headers:" )
for key, val in hdrs.items():
    print( "  {} = {}".format( key, val ) )
print( "" )
if raw_mode:
    # just output the raw response
    print( body )
else:
    # output a formatted dump of the XML response
    if isinstance( body, ElementTree.Element ):
        dump_xml_tree( body )
    elif isinstance( body, dict ):
        pprint.pprint( body )
    else:
        print( body )
