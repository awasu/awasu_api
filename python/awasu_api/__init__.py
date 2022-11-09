""" Tools for automating Awasu via its API.

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

# NOTE: We bring these into the top-level namespace as a convenience (so that
# callers can use "awasu_api.doodad" instead of "awasu_api.api.doodad").
from awasu_api.api import AwasuApi , AwasuApiException
