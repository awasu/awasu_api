""" Miscellaneous utilities.
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

import xml.sax.saxutils

# ---------------------------------------------------------------------

def dump_xml_tree( elem, prefix="" ):
    """Dump a tree of xml.etree.ElementTree nodes."""
    _do_dump_xml_tree( elem, prefix, 0 )

def _do_dump_xml_tree( node, prefix, tag_field_width ):
    if node is None:
        return
    # dump the XML node
    fmt = "{}{:<%d} {}" % (1+tag_field_width)
    print( fmt.format(
        prefix,
        str(node.tag) + ":",
        node.text.strip() if node.text else ""
    ) )
    # dump any attributes
    for attr in node.items():
        print( "{}  @{} = {}".format( prefix, attr[0], attr[1] ) )
    # dump any child nodes
    child_nodes = list( node )
    if len(child_nodes) > 0:
        max_tag_len = max( len(cn.tag) for cn in child_nodes )
        for cn in child_nodes:
            _do_dump_xml_tree( cn, prefix+"    ", max_tag_len )

# ---------------------------------------------------------------------

def safe_xml_string( val ):
    """Convert a value into something that's safe for inclusion in XML."""
    if val is None:
        return ""
    return xml.sax.saxutils.escape( str(val) )

# - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

def bool_string( val ):
    """Convert a value to a boolean string."""
    return "true" if val else "false"
