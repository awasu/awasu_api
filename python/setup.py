""" Set up the awasu_api module. """

from distutils.core import setup

setup(
    name = "awasu-api",
    version = "1.2",
    description = "Tools for automating Awasu via its API.",
    url = "https://awasu.com/awasu_api",
    author = "Awasu",
    author_email = "support@awasu.com",
    packages = [ "awasu_api" ],
    entry_points = {
        "console_scripts": "awasu-api = awasu_api.console:main",
    }
)
