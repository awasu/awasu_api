from distutils.core import setup

setup( 
    name = "awasu-api" ,
    version = "1.0" ,
    description = "Tools for automating Awasu via its API." ,
    long_description = open("README.rst","r").read() ,
    url = "http://awasu.com/awasu_api" ,
    author = "Awasu" ,
    author_email = "support@awasu.com" ,
    packages = ["awasu_api"]
)
